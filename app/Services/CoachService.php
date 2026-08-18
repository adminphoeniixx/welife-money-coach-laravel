<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Budget;
use App\Models\Debt;
use App\Models\Entry;
use App\Models\User;
use App\Support\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rule-based financial coaching engine.
 *
 * Turns a user's raw finance data (assets, debts, entries, budgets, goals,
 * bills) into the derived numbers, scores and recommendations the dashboard
 * needs. All money is handled internally as integer minor units (cents /
 * paise) and exposed to the UI as major-unit numbers (rupees).
 *
 * The recommendation text is generated deterministically from thresholds so
 * it works offline and free. The output shape is intentionally stable so a
 * real LLM coaching layer can replace {@see self::tips()} later without
 * touching the dashboard.
 */
class CoachService
{
    public function __construct(private readonly InsightService $insights) {}

    /**
     * Build the full dashboard snapshot for a user.
     *
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        $accounts = $user->financeAccounts()->get();
        $debts = $user->debts()->where('status', 'active')->get();
        $budgets = $user->budgets()->whereNull('household_id')->get();
        $goals = $user->goals()->get();
        $bills = $user->bills()->orderBy('due_date')->get();

        $now = Carbon::now();
        $monthEntries = $user->entries()
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->get();

        $assetsCents = (int) $accounts->sum('balance_cents');
        $liabilitiesCents = (int) $debts->sum('balance_cents');
        $incomeCents = (int) $monthEntries->where('type', 'income')->sum('amount_cents');
        $expenseCents = (int) $monthEntries->where('type', 'expense')->sum('amount_cents');
        $emiCents = (int) $debts->sum('emi_cents');
        $savingsCents = $incomeCents - $expenseCents;

        $cards = $debts->where('kind', 'credit_card');
        $avgUtilisation = $this->averageUtilisation($cards);
        $emergencyGoals = $goals->where('type', 'emergency_fund');
        $emergency = $emergencyGoals->first();
        $overdueCount = $bills->where('status', 'overdue')->count();

        $health = $this->healthScore(
            incomeCents: $incomeCents,
            expenseCents: $expenseCents,
            emiCents: $emiCents,
            utilisation: $avgUtilisation,
            emergencyMonths: $this->emergencyMonths((int) $emergencyGoals->sum('saved_cents'), $expenseCents),
            overdueCount: $overdueCount,
        );

        $payoff = $this->simulatePayoff($debts, $emiCents);
        $priority = $this->priorityPayment($debts, $bills, $user);

        $remindersUnread = $this->insights->remindersUnreadCount($user);
        $notificationsUnread = $this->insights->unreadCount($user);

        return [
            'currency' => $user->currency,
            'currency_symbol' => $user->currencySymbol(),
            'user' => ['id' => $user->id, 'name' => $user->name],
            'health' => $health,
            'kpis' => [
                'net_worth' => $this->rupees($assetsCents - $liabilitiesCents),
                'assets' => $this->rupees($assetsCents),
                'liabilities' => $this->rupees($liabilitiesCents),
                'income' => $this->rupees($incomeCents),
                'expense' => $this->rupees($expenseCents),
                'savings' => $this->rupees($savingsCents),
                'savings_rate' => $incomeCents > 0 ? round($savingsCents / $incomeCents * 100) : 0,
                'total_debt' => $this->rupees($liabilitiesCents),
                'monthly_emi' => $this->rupees($emiCents),
                'emi_to_income' => $incomeCents > 0 ? round($emiCents / $incomeCents * 100) : 0,
            ],
            'priority' => $priority,
            'debt_free' => [
                'months' => $payoff['months'],
                'label' => $this->durationLabel($payoff['months']),
                'date' => $payoff['date'],
                'interest_left' => $this->rupees($payoff['interest_cents']),
                'progress' => $this->debtProgress($debts),
            ],
            'emergency_fund' => $emergency ? [
                'id' => $emergency->id,
                'name' => $emergency->name,
                'type' => $emergency->type,
                'target' => $this->rupees($emergency->target_cents),
                'saved' => $this->rupees($emergency->saved_cents),
                'progress' => $emergency->progress(),
                'target_date' => $emergency->target_date?->format('Y-m-d'),
            ] : null,
            'goals' => $goals->where('type', '!=', 'emergency_fund')->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type,
                'target' => $this->rupees($g->target_cents),
                'saved' => $this->rupees($g->saved_cents),
                'progress' => $g->progress(),
                'target_date' => $g->target_date?->format('Y-m-d'),
            ])->values(),
            'budgets' => $this->budgetStatus($budgets, $monthEntries),
            'upcoming' => $this->upcomingBills($bills),
            'spending' => $this->spendingBreakdown($monthEntries),
            'trend' => $this->monthlyTrend($user),
            // Route metadata only — what each tile shows is the live data above.
            'shortcuts' => Options::shortcuts(),
            'features' => Options::features(),
            'recent_transactions' => $this->recentTransactions($monthEntries, $user),
            'counts' => [
                'reminders_unread' => $remindersUnread,
                'notifications_unread' => $notificationsUnread,
                'transactions_this_month' => $monthEntries->count(),
                'active_debts' => $debts->count(),
                'overdue_bills' => $overdueCount,
            ],
            // Same numbers, hoisted for a client that reads them flat.
            'reminders_unread' => $remindersUnread,
            'notifications_unread' => $notificationsUnread,
            'tips' => $this->tips($incomeCents, $expenseCents, $emiCents, $avgUtilisation, $cards, $overdueCount, $user),
            'debts' => $debts->sortByDesc('interest_rate')->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'institution' => $d->institution,
                'kind' => $d->kind,
                'category' => $d->category,
                'balance' => $this->rupees($d->balance_cents),
                'interest_rate' => (float) $d->interest_rate,
                'emi' => $this->rupees($d->emi_cents),
                'utilisation' => $d->isCard() ? $d->utilisation() : null,
                // Same key as GET /api/debts — never `limit`.
                'credit_limit' => $d->credit_limit_cents ? $this->rupees($d->credit_limit_cents) : null,
                'min_due' => $d->min_due_cents ? $this->rupees($d->min_due_cents) : null,
                'due_day' => $d->due_day,
            ])->values(),
        ];
    }

    /**
     * Answer a free-text question ("which loan should I close first?",
     * "how much did I spend on food?") from the user's own finance data.
     *
     * Intent is matched on keywords and every figure in the reply is read
     * live from the ledger — there is no canned answer path. When nothing
     * matches, the reply is a real summary of where the user stands rather
     * than a placeholder.
     *
     * @return array{question:string, answer:string, tone:string}
     */
    public function answer(User $user, string $question): array
    {
        $q = mb_strtolower(trim($question));

        if ($q === '') {
            return $this->reply($question, 'Ask me anything about your money — spending, savings, loans, cards, budgets, reminders or net worth.', 'general');
        }

        foreach ($this->intents() as $tone => $keywords) {
            if (! $this->mentions($q, $keywords)) {
                continue;
            }

            return $this->reply($question, match ($tone) {
                'debt' => $this->answerDebt($user, $q),
                'card' => $this->answerCard($user, $q),
                'reminder' => $this->answerReminder($user, $q),
                'budget' => $this->answerBudget($user, $q),
                'goal' => $this->answerGoal($user, $q),
                'networth' => $this->answerNetworth($user, $q),
                'saving' => $this->answerSaving($user, $q),
                'spend' => $this->answerSpend($user, $q),
                default => $this->answerIncome($user, $q),
            }, $tone);
        }

        return $this->reply($question, $this->answerOverview($user), 'general');
    }

    /**
     * Question keywords per topic, most specific topic first — a question
     * about a "credit card APR" should land on cards, not on spending.
     *
     * @return array<string, list<string>>
     */
    private function intents(): array
    {
        return [
            'debt' => ['loan', 'debt', 'emi', 'payoff', 'pay off', 'close first', 'interest rate', 'apr', 'borrow', 'repay'],
            'card' => ['credit card', 'card', 'utilisation', 'utilization', 'statement', 'minimum due', 'limit'],
            'reminder' => ['reminder', 'bill', 'due', 'subscription', 'overdue', 'renewal', 'upcoming'],
            'budget' => ['budget', 'limit', 'overspend', 'over budget', 'category limit'],
            'goal' => ['goal', 'emergency fund', 'target', 'saving for'],
            'networth' => ['net worth', 'networth', 'asset', 'wealth', 'portfolio'],
            'saving' => ['save', 'saving', 'savings', 'surplus', 'left over', 'leftover'],
            'spend' => ['spend', 'spent', 'spending', 'expense', 'expenses', 'cost', 'where did my money'],
            'income' => ['income', 'earn', 'earned', 'salary'],
        ];
    }

    /**
     * @param  list<string>  $keywords
     */
    private function mentions(string $question, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($question, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{question:string, answer:string, tone:string}
     */
    private function reply(string $question, string $answer, string $tone): array
    {
        return ['question' => $question, 'answer' => $answer, 'tone' => $tone];
    }

    private function answerDebt(User $user, string $q): string
    {
        $debts = $user->debts()->where('status', 'active')->get();

        if ($debts->isEmpty()) {
            return 'You have no active loans or cards recorded, so there is nothing to pay down. Add a debt and I can build a payoff plan for it.';
        }

        $focus = $debts->sortByDesc('interest_rate')->first();
        $smallest = $debts->sortBy('balance_cents')->first();
        $totalCents = (int) $debts->sum('balance_cents');
        $emiCents = (int) $debts->sum('emi_cents');
        $plan = $this->simulatePayoff($debts, $emiCents);

        $answer = sprintf(
            'Close %s first — at %s%% it is your most expensive debt, costing about %s in interest a month. You owe %s across %d debt%s, with %s going out in EMIs.',
            $focus->name,
            $this->rate($focus->interest_rate),
            $user->money((int) round($focus->balance_cents * ($focus->interest_rate / 100) / 12)),
            $user->money($totalCents),
            $debts->count(),
            $debts->count() === 1 ? '' : 's',
            $user->money($emiCents),
        );

        if ($plan['months'] > 0) {
            $answer .= sprintf(' At this rate you are debt-free in %s (around %s).', $this->durationLabel($plan['months']), $plan['date']);
        }

        if ($smallest && $smallest->id !== $focus->id && $this->mentions($q, ['snowball', 'motivat', 'quick win', 'smallest'])) {
            $answer .= sprintf(' If you would rather get a quick win first, %s is your smallest balance at %s.', $smallest->name, $user->money($smallest->balance_cents));
        }

        return $answer;
    }

    private function answerCard(User $user, string $q): string
    {
        $cards = $user->debts()->where('kind', 'credit_card')->where('status', 'active')->get();

        if ($cards->isEmpty()) {
            return 'You have no credit cards recorded yet. Add one and I can track its utilisation, statement date and minimum due.';
        }

        $hottest = $cards->sortByDesc(fn (Debt $c) => $c->utilisation())->first();
        $dueTotal = (int) $cards->sum(fn (Debt $c) => $c->currentDueCents());

        $answer = sprintf(
            'You have %d card%s with %s currently due in total. %s is at %s%% utilisation%s.',
            $cards->count(),
            $cards->count() === 1 ? '' : 's',
            $user->money($dueTotal),
            $hottest->name,
            $this->rate($hottest->utilisation()),
            $hottest->utilisation() >= 30 ? ' — above the healthy 30% mark, so paying it down will lift your credit score' : ', which is healthy',
        );

        $nextDue = $cards->map(fn (Debt $c) => $c->nextDateForDay($c->due_day))->filter()->sort()->first();
        if ($nextDue !== null) {
            $answer .= ' Your next card payment falls on '.$nextDue->format('d M Y').'.';
        }

        if ($hottest->min_due_cents) {
            $answer .= sprintf(' Paying only the %s minimum on %s keeps the interest running — clear the full statement if you can.', $user->money($hottest->min_due_cents), $hottest->name);
        }

        return $answer;
    }

    private function answerReminder(User $user, string $q): string
    {
        $today = Carbon::now()->startOfDay();
        $bills = $user->bills()->where('status', '!=', 'paid')->orderBy('due_date')->get();
        $overdue = $bills->where('status', 'overdue');
        $subs = $user->bills()->where('kind', 'subscription')->get();

        if ($bills->isEmpty() && $subs->isEmpty()) {
            return 'Nothing is due — you have no open bills, EMIs or subscriptions recorded.';
        }

        $parts = [];

        if ($overdue->isNotEmpty()) {
            $parts[] = sprintf(
                'You have %d overdue payment%s totalling %s — clear %s first to avoid late fees.',
                $overdue->count(),
                $overdue->count() === 1 ? '' : 's',
                $user->money((int) $overdue->sum('amount_cents')),
                $overdue->count() === 1 ? $overdue->first()->name : 'them',
            );
        }

        $next = $bills->where('status', 'upcoming')->first();
        if ($next !== null) {
            $days = (int) round($today->diffInDays($next->due_date, false));
            $parts[] = sprintf(
                'Next up is %s at %s, %s.',
                $next->name,
                $user->money($next->amount_cents),
                strtolower($this->relativeDay($days)),
            );
        }

        if ($subs->isNotEmpty()) {
            $parts[] = sprintf(
                'Your %d subscription%s cost %s a month.',
                $subs->count(),
                $subs->count() === 1 ? '' : 's',
                $user->money((int) $subs->sum('amount_cents')),
            );
        }

        return implode(' ', $parts);
    }

    private function answerBudget(User $user, string $q): string
    {
        $now = Carbon::now();
        $budgets = $user->budgets()->whereNull('household_id')->get();

        if ($budgets->isEmpty()) {
            return 'You have not set any budgets yet. Set a monthly limit on a category and I will track what you have spent against it.';
        }

        $entries = $user->entries()
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->get();
        $rows = collect($this->budgetStatus($budgets, $entries));

        $exceeded = $rows->where('exceeded', true);
        $answer = sprintf('You are tracking %d budget%s this month.', $rows->count(), $rows->count() === 1 ? '' : 's');

        if ($exceeded->isNotEmpty()) {
            $answer .= ' Over limit: '.$exceeded->map(fn ($r) => sprintf('%s (%s of %s)', $r['category'], $user->money((int) round($r['spent'] * 100)), $user->money((int) round($r['limit'] * 100)))
            )->implode(', ').'.';
        } else {
            $answer .= ' Every one of them is still within its limit — nice work.';
        }

        $tightest = $rows->reject(fn ($r) => $r['exceeded'])->sortByDesc('percent')->first();
        if ($tightest !== null) {
            $answer .= sprintf(' The closest to its cap is %s at %d%% used.', $tightest['category'], $tightest['percent']);
        }

        return $answer;
    }

    private function answerGoal(User $user, string $q): string
    {
        $goals = $user->goals()->get();

        if ($goals->isEmpty()) {
            return 'You have no savings goals yet. Create one — an emergency fund is the usual first step — and I will track your progress toward it.';
        }

        return 'Goals: '.$goals->map(fn ($g) => sprintf(
            '%s — %s of %s saved (%d%%)',
            $g->name,
            $user->money($g->saved_cents),
            $user->money($g->target_cents),
            (int) round($g->progress()),
        ))->implode('; ').'.';
    }

    private function answerNetworth(User $user, string $q): string
    {
        $assetsCents = (int) $user->financeAccounts()->sum('balance_cents');
        $liabilitiesCents = (int) $user->debts()->where('status', 'active')->sum('balance_cents');
        $net = $assetsCents - $liabilitiesCents;

        if ($assetsCents === 0 && $liabilitiesCents === 0) {
            return 'I do not have any assets or debts on file yet, so I cannot work out your net worth. Add your accounts and any loans to see it.';
        }

        return sprintf(
            'Your net worth is %s — %s in assets minus %s in debts. %s',
            $user->money($net),
            $user->money($assetsCents),
            $user->money($liabilitiesCents),
            $net >= 0
                ? 'You are in the green; growing assets faster than debt keeps it that way.'
                : 'You owe more than you own right now, so clearing your highest-rate debt is the fastest way to turn this positive.',
        );
    }

    private function answerSaving(User $user, string $q): string
    {
        $now = Carbon::now();
        $entries = $user->entries()
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->get();

        $incomeCents = (int) $entries->where('type', 'income')->sum('amount_cents');
        $expenseCents = (int) $entries->where('type', 'expense')->sum('amount_cents');
        $savedCents = $incomeCents - $expenseCents;

        if ($incomeCents === 0 && $expenseCents === 0) {
            return 'You have not logged any income or expenses this month yet, so there is nothing to work out savings from.';
        }

        $answer = sprintf(
            'This month you have brought in %s and spent %s, so you are %s %s%s.',
            $user->money($incomeCents),
            $user->money($expenseCents),
            $savedCents >= 0 ? 'saving' : 'over by',
            $user->money(abs($savedCents)),
            $incomeCents > 0 ? sprintf(' (%d%% of income)', (int) round($savedCents / $incomeCents * 100)) : '',
        );

        $top = $entries->where('type', 'expense')->groupBy('category')
            ->map(fn ($rows) => (int) $rows->sum('amount_cents'))
            ->sortDesc()->take(1);

        if ($top->isNotEmpty()) {
            $category = (string) $top->keys()->first();
            $amount = (int) $top->first();
            $answer .= sprintf(
                ' Your biggest lever is %s at %s — trimming it by 20%% would free up about %s a month.',
                $category !== '' ? $category : 'uncategorised spending',
                $user->money($amount),
                $user->money((int) round($amount * 0.2)),
            );
        }

        return $answer;
    }

    private function answerSpend(User $user, string $q): string
    {
        $now = Carbon::now();
        $entries = $user->entries()->where('type', 'expense')
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->get();

        if ($entries->isEmpty()) {
            return 'You have not logged any expenses this month, so there is nothing to break down yet.';
        }

        $totalCents = (int) $entries->sum('amount_cents');

        // If the question names one of the user's own categories, answer about
        // that category specifically instead of the whole month.
        $categories = $entries->pluck('category')->filter()->unique();
        $named = $categories->first(fn ($c) => str_contains($q, mb_strtolower((string) $c)));

        if ($named !== null) {
            $catCents = (int) $entries->where('category', $named)->sum('amount_cents');
            $budget = $user->budgets()->whereNull('household_id')->where('category', $named)->first();

            $answer = sprintf(
                'You have spent %s on %s this month — %d%% of your %s total spend.',
                $user->money($catCents),
                $named,
                $totalCents > 0 ? (int) round($catCents / $totalCents * 100) : 0,
                $user->money($totalCents),
            );

            if ($budget !== null && $budget->limit_cents > 0) {
                $answer .= $catCents > $budget->limit_cents
                    ? sprintf(' That is over your %s budget by %s.', $user->money($budget->limit_cents), $user->money($catCents - $budget->limit_cents))
                    : sprintf(' You have %s left of your %s budget.', $user->money($budget->limit_cents - $catCents), $user->money($budget->limit_cents));
            }

            return $answer;
        }

        $top = $entries->groupBy('category')
            ->map(fn ($rows) => (int) $rows->sum('amount_cents'))
            ->sortDesc()->take(3);

        $breakdown = $top->map(fn (int $cents, $cat) => sprintf(
            '%s %s (%d%%)',
            $cat !== '' ? $cat : 'Other',
            $user->money($cents),
            $totalCents > 0 ? (int) round($cents / $totalCents * 100) : 0,
        ))->implode(', ');

        $lastMonthCents = (int) $user->entries()->where('type', 'expense')
            ->whereBetween('occurred_on', [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ])->sum('amount_cents');

        $answer = sprintf('You have spent %s this month across %d transactions. Top categories: %s.', $user->money($totalCents), $entries->count(), $breakdown);

        if ($lastMonthCents > 0) {
            $delta = $totalCents - $lastMonthCents;
            $answer .= $delta >= 0
                ? sprintf(' That is %s more than all of last month.', $user->money($delta))
                : sprintf(' That is %s less than all of last month — good direction.', $user->money(abs($delta)));
        }

        return $answer;
    }

    private function answerIncome(User $user, string $q): string
    {
        $now = Carbon::now();
        $entries = $user->entries()->where('type', 'income')
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->get();

        if ($entries->isEmpty()) {
            return 'No income is logged for this month yet. Add it and I can work out your savings rate and how much of it your EMIs take.';
        }

        $incomeCents = (int) $entries->sum('amount_cents');
        $emiCents = (int) $user->debts()->where('status', 'active')->sum('emi_cents');

        $answer = sprintf('You have logged %s of income this month across %d entries.', $user->money($incomeCents), $entries->count());

        if ($emiCents > 0) {
            $answer .= sprintf(
                ' EMIs take %s of that (%d%%)%s.',
                $user->money($emiCents),
                (int) round($emiCents / $incomeCents * 100),
                $emiCents / $incomeCents > 0.4 ? ' — above 40% is stretched' : '',
            );
        }

        return $answer;
    }

    /**
     * The catch-all reply: a real position summary, never a canned line.
     */
    private function answerOverview(User $user): string
    {
        $snapshot = $this->snapshot($user);
        $kpis = $snapshot['kpis'];

        return sprintf(
            'Here is where you stand: net worth %s, %s in and %s out this month (%s saved), %s of debt with %s in EMIs, and a financial health score of %d/100. Ask me about your spending, savings, loans, cards, budgets or reminders for a closer look.',
            $user->money((int) round($kpis['net_worth'] * 100)),
            $user->money((int) round($kpis['income'] * 100)),
            $user->money((int) round($kpis['expense'] * 100)),
            $user->money((int) round($kpis['savings'] * 100)),
            $user->money((int) round($kpis['total_debt'] * 100)),
            $user->money((int) round($kpis['monthly_emi'] * 100)),
            $snapshot['health']['score'],
        );
    }

    /** "18.5" / "18" — a rate without trailing zeros. */
    private function rate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * Build an interactive Debt Coach plan for a payoff strategy and an
     * optional extra monthly payment. Shows the ordered payoff queue and the
     * interest / time saved versus paying only the minimums.
     *
     * @return array<string, mixed>
     */
    public function coachPlan(User $user, string $strategy, int $extraCents): array
    {
        $strategy = in_array($strategy, ['snowball', 'avalanche'], true) ? $strategy : 'avalanche';
        $debts = $user->debts()->where('status', 'active')->get();

        $emiCents = (int) $debts->sum('emi_cents');
        $totalCents = (int) $debts->sum('balance_cents');
        $extraCents = max(0, $extraCents);

        $base = $this->simulatePayoff($debts, $emiCents, $strategy);
        $projected = $this->simulatePayoff($debts, $emiCents + $extraCents, $strategy);

        $ordered = $strategy === 'snowball'
            ? $debts->sortBy('balance_cents')
            : $debts->sortByDesc('interest_rate');

        return [
            'strategy' => $strategy,
            'extra' => $this->rupees($extraCents),
            'summary' => [
                'total' => $this->rupees($totalCents),
                'monthly_emi' => $this->rupees($emiCents),
                'progress' => $this->debtProgress($debts),
                'avg_apr' => $totalCents > 0
                    ? round($debts->sum(fn ($d) => $d->balance_cents * $d->interest_rate) / $totalCents, 1)
                    : 0.0,
            ],
            'base' => $this->planStats($base),
            'projected' => $this->planStats($projected),
            'interest_saved' => $this->rupees(max(0, $base['interest_cents'] - $projected['interest_cents'])),
            'months_saved' => max(0, $base['months'] - $projected['months']),
            'order' => $ordered->values()->map(fn ($d, $i) => [
                'position' => $i + 1,
                'name' => $d->name,
                'kind' => $d->kind,
                'balance' => $this->rupees($d->balance_cents),
                'interest_rate' => (float) $d->interest_rate,
                'emi' => $this->rupees($d->emi_cents),
                'focus' => $i === 0,
            ])->all(),
        ];
    }

    /**
     * @param  array{months:int, interest_cents:int, date:?string}  $plan
     * @return array{months:int, label:string, date:?string, interest:float}
     */
    private function planStats(array $plan): array
    {
        return [
            'months' => $plan['months'],
            'label' => $this->durationLabel($plan['months']),
            'date' => $plan['date'],
            'interest' => $this->rupees($plan['interest_cents']),
        ];
    }

    /**
     * Composite 0-100 financial health score with a coloured status band.
     *
     * @return array{score:int, status:string, tone:string, factors:array<int, array<string, mixed>>}
     */
    private function healthScore(
        int $incomeCents,
        int $expenseCents,
        int $emiCents,
        float $utilisation,
        float $emergencyMonths,
        int $overdueCount,
    ): array {
        $savingsRate = $incomeCents > 0 ? ($incomeCents - $expenseCents) / $incomeCents : 0;
        $emiRatio = $incomeCents > 0 ? $emiCents / $incomeCents : 1;

        // Savings rate — up to 30 pts (>= 20% saved is full marks).
        $savingsPts = (int) round($this->scale($savingsRate, 0, 0.20) * 30);
        // Debt burden — up to 25 pts (<= 20% EMI-to-income is full marks).
        $debtPts = (int) round((1 - $this->scale($emiRatio, 0.20, 0.60)) * 25);
        // Credit utilisation — up to 20 pts (<= 10% is full marks).
        $utilPts = (int) round((1 - $this->scale($utilisation / 100, 0.10, 0.80)) * 20);
        // Emergency fund — up to 15 pts (>= 6 months of expenses is full marks).
        $efPts = (int) round($this->scale($emergencyMonths, 0, 6) * 15);
        // On-time bills — up to 10 pts, minus 5 per overdue bill.
        $billPts = max(0, 10 - $overdueCount * 5);

        $score = max(0, min(100, $savingsPts + $debtPts + $utilPts + $efPts + $billPts));

        [$status, $tone] = match (true) {
            $score >= 80 => ['Excellent', 'green'],
            $score >= 65 => ['Good', 'teal'],
            $score >= 45 => ['Needs improvement', 'amber'],
            default => ['Critical', 'red'],
        };

        return [
            'score' => $score,
            'status' => $status,
            'tone' => $tone,
            'factors' => [
                ['label' => 'Savings rate', 'points' => $savingsPts, 'max' => 30],
                ['label' => 'Debt burden', 'points' => $debtPts, 'max' => 25],
                ['label' => 'Credit utilisation', 'points' => $utilPts, 'max' => 20],
                ['label' => 'Emergency fund', 'points' => $efPts, 'max' => 15],
                ['label' => 'On-time bills', 'points' => $billPts, 'max' => 10],
            ],
        ];
    }

    /**
     * Pick the single most valuable next payment: highest-APR active debt,
     * with the interest saved by clearing a month early.
     *
     * @param  Collection<int, Debt>  $debts
     * @param  Collection<int, Bill>  $bills
     * @return array<string, mixed>|null
     */
    private function priorityPayment(Collection $debts, Collection $bills, User $user): ?array
    {
        $focus = $debts->sortByDesc('interest_rate')->first();

        if (! $focus || $focus->balance_cents <= 0) {
            return null;
        }

        // Rough monthly interest on the balance — the recurring cost of delay.
        $monthlyInterestCents = (int) round($focus->balance_cents * ($focus->interest_rate / 100) / 12);
        $dueBill = $bills->whereIn('status', ['upcoming', 'overdue'])
            ->firstWhere('debt_id', $focus->id);

        return [
            'id' => $focus->id,
            'name' => $focus->name,
            'institution' => $focus->institution,
            'kind' => $focus->kind,
            'interest_rate' => (float) $focus->interest_rate,
            'balance' => $this->rupees($focus->balance_cents),
            'monthly_interest' => $this->rupees($monthlyInterestCents),
            'due_in_days' => $dueBill ? Carbon::now()->startOfDay()->diffInDays($dueBill->due_date, false) : null,
            'headline' => sprintf(
                'Pay down %s first — %s%% is your most expensive debt',
                $focus->name,
                rtrim(rtrim(number_format((float) $focus->interest_rate, 2), '0'), '.'),
            ),
            'reason' => sprintf(
                'It costs about %s in interest every month. Clearing it first saves the most money overall.',
                $user->money($monthlyInterestCents),
            ),
        ];
    }

    /**
     * Simulate paying off all debts using the given monthly budget and payoff
     * strategy. Returns months to zero, total interest and date.
     *
     * @param  Collection<int, Debt>  $debts
     * @return array{months:int, interest_cents:int, date:?string}
     */
    private function simulatePayoff(Collection $debts, int $monthlyBudgetCents, string $strategy = 'avalanche'): array
    {
        $ordered = $strategy === 'snowball'
            ? $debts->sortBy('balance_cents')
            : $debts->sortByDesc('interest_rate');

        $balances = $ordered
            ->map(fn ($d) => [
                'balance' => (float) $d->balance_cents,
                'rate' => (float) $d->interest_rate / 100 / 12,
            ])->values()->all();

        if (empty($balances) || $monthlyBudgetCents <= 0) {
            return ['months' => 0, 'interest_cents' => 0, 'date' => null];
        }

        $interest = 0.0;
        $months = 0;

        while ($months < 600) {
            $total = array_sum(array_column($balances, 'balance'));
            if ($total <= 1) {
                break;
            }

            $budget = (float) $monthlyBudgetCents;

            // Accrue interest, then throw the whole budget at balances in order.
            foreach ($balances as $i => $d) {
                $accrued = $d['balance'] * $d['rate'];
                $interest += $accrued;
                $balances[$i]['balance'] += $accrued;
            }
            foreach ($balances as $i => $d) {
                if ($budget <= 0) {
                    break;
                }
                $pay = min($budget, $balances[$i]['balance']);
                $balances[$i]['balance'] -= $pay;
                $budget -= $pay;
            }

            $months++;

            // Budget can't even cover interest — treat as never-ending.
            if (array_sum(array_column($balances, 'balance')) >= $total) {
                return ['months' => 0, 'interest_cents' => 0, 'date' => null];
            }
        }

        return [
            'months' => $months,
            'interest_cents' => (int) round($interest),
            'date' => Carbon::now()->addMonths($months)->format('M Y'),
        ];
    }

    /**
     * Percentage of original principal already paid off across all debts.
     *
     * @param  Collection<int, Debt>  $debts
     */
    private function debtProgress(Collection $debts): float
    {
        $principal = (int) $debts->sum('principal_cents');
        $balance = (int) $debts->sum('balance_cents');

        if ($principal <= 0) {
            return 0.0;
        }

        return max(0, min(100, round(($principal - $balance) / $principal * 100)));
    }

    /**
     * @param  Collection<int, Budget>  $budgets
     * @param  Collection<int, Entry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function budgetStatus(Collection $budgets, Collection $entries): array
    {
        $spentByCategory = $entries->where('type', 'expense')
            ->groupBy('category')
            ->map(fn ($rows) => (int) $rows->sum('amount_cents'));

        return $budgets->map(function ($budget) use ($spentByCategory) {
            $spent = (int) ($spentByCategory[$budget->category] ?? 0);
            $pct = $budget->limit_cents > 0 ? round($spent / $budget->limit_cents * 100) : 0;

            return [
                'id' => $budget->id,
                'category' => $budget->category,
                'spent' => $this->rupees($spent),
                'limit' => $this->rupees($budget->limit_cents),
                'percent' => $pct,
                'exceeded' => $spent > $budget->limit_cents,
            ];
        })->sortByDesc('percent')->values()->all();
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array<int, array<string, mixed>>
     */
    private function upcomingBills(Collection $bills): array
    {
        return $bills->whereIn('status', ['upcoming', 'overdue'])
            ->sortBy('due_date')
            ->take(6)
            ->map(function ($bill) {
                $days = (int) round(Carbon::now()->startOfDay()->diffInDays($bill->due_date, false));

                return [
                    'id' => $bill->id,
                    'name' => $bill->name,
                    'kind' => $bill->kind,
                    'category' => $bill->category,
                    'amount' => $this->rupees($bill->amount_cents),
                    // Machine-readable; `label`/`when` carry the display copy.
                    'due_date' => $bill->due_date->format('Y-m-d'),
                    'label' => $bill->due_date->format('d M'),
                    'days' => $days,
                    'when' => $this->relativeDay($days),
                    'overdue' => $bill->status === 'overdue' || $days < 0,
                    'repeat' => $bill->repeat,
                    'remind_days_before' => $bill->remind_days_before,
                    'status' => $bill->status,
                ];
            })->values()->all();
    }

    /**
     * The last few entries of the current month, for the home screen's
     * "recent activity" list.
     *
     * @param  Collection<int, Entry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function recentTransactions(Collection $entries, User $user): array
    {
        return $entries
            ->sortByDesc(fn (Entry $e) => [$e->occurred_on->timestamp, $e->id])
            ->take(5)
            ->map(fn (Entry $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'category' => $e->category,
                'description' => $e->description,
                'payee' => $e->payee,
                'amount' => $this->rupees($e->amount_cents),
                'amount_label' => $user->money($e->amount_cents),
                'occurred_on' => $e->occurred_on->format('Y-m-d'),
                'label' => $e->occurred_on->format('d M'),
                'repeat' => $e->repeat ?? 'none',
                'recurring' => $e->repeats(),
            ])->values()->all();
    }

    /**
     * Current-month expense breakdown by category with percentages.
     *
     * Percentages use largest-remainder rounding so the slices add up to
     * exactly 100 — a naive round() per slice does not, and the app draws a
     * pie chart straight from these numbers.
     *
     * @param  Collection<int, Entry>  $entries
     * @return array{total:float, slices:array<int, array<string, mixed>>}
     */
    private function spendingBreakdown(Collection $entries): array
    {
        $expenses = $entries->where('type', 'expense');
        $totalCents = (int) $expenses->sum('amount_cents');

        $slices = $expenses->groupBy('category')
            ->map(fn ($rows, $cat) => [
                'category' => $cat ?: 'Other',
                'amount_cents' => (int) $rows->sum('amount_cents'),
            ])
            ->sortByDesc('amount_cents')
            ->values()
            ->all();

        $percents = $this->distributePercent(array_column($slices, 'amount_cents'), $totalCents);

        $slices = array_map(fn (array $s, int $percent) => [
            'category' => $s['category'],
            'amount' => $this->rupees($s['amount_cents']),
            'percent' => $percent,
        ], $slices, $percents);

        return ['total' => $this->rupees($totalCents), 'slices' => $slices];
    }

    /**
     * Split 100% across the given parts using largest-remainder rounding, so
     * the whole-number percentages sum to exactly 100 (or all zero when there
     * is nothing to split).
     *
     * @param  list<int>  $parts
     * @return list<int>
     */
    private function distributePercent(array $parts, int $total): array
    {
        if ($total <= 0 || $parts === []) {
            return array_fill(0, count($parts), 0);
        }

        $exact = array_map(fn (int $p) => $p / $total * 100, $parts);
        $floors = array_map(intval(...), $exact);
        $shortfall = 100 - array_sum($floors);

        // Hand the leftover points to the parts with the biggest remainders.
        $remainders = [];
        foreach ($exact as $i => $value) {
            $remainders[$i] = $value - $floors[$i];
        }
        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, max(0, $shortfall)) as $i) {
            $floors[$i]++;
        }

        return array_values($floors);
    }

    /**
     * Income vs expense totals for the last 6 months.
     *
     * @return array<int, array<string, mixed>>
     */
    private function monthlyTrend(User $user): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        $rows = $user->entries()
            ->where('occurred_on', '>=', $start)
            ->get(['type', 'amount_cents', 'occurred_on']);

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $slice = $rows->filter(fn ($r) => $r->occurred_on->format('Y-m') === $key);
            $months[] = [
                'month' => $key,
                'label' => $m->format('M'),
                'income' => $this->rupees((int) $slice->where('type', 'income')->sum('amount_cents')),
                'expense' => $this->rupees((int) $slice->where('type', 'expense')->sum('amount_cents')),
            ];
        }

        return $months;
    }

    /**
     * Deterministic, personalised coaching tips derived from thresholds.
     *
     * @param  Collection<int, Debt>  $cards
     * @return array<int, array<string, string>>
     */
    private function tips(
        int $incomeCents,
        int $expenseCents,
        int $emiCents,
        float $utilisation,
        Collection $cards,
        int $overdueCount,
        User $user,
    ): array {
        $tips = [];

        if ($overdueCount > 0) {
            $tips[] = ['tone' => 'red', 'icon' => 'alert', 'text' => sprintf(
                'You have %d overdue %s. Clear %s now to avoid late fees and interest.',
                $overdueCount,
                $overdueCount === 1 ? 'payment' : 'payments',
                $overdueCount === 1 ? 'it' : 'them',
            )];
        }

        $hotCard = $cards->first(fn ($c) => $c->utilisation() >= 80);
        if ($hotCard) {
            $tips[] = ['tone' => 'red', 'icon' => 'credit-card', 'text' => sprintf(
                'Your %s utilisation is %s%% — above the healthy 30%%. Paying it down will lift your credit score.',
                $hotCard->name,
                rtrim(rtrim(number_format($hotCard->utilisation(), 1), '0'), '.'),
            )];
        } elseif ($utilisation >= 30) {
            $tips[] = ['tone' => 'amber', 'icon' => 'credit-card', 'text' => sprintf(
                'Overall card utilisation is %d%%. Keeping it under 30%% helps your credit profile.',
                (int) round($utilisation),
            )];
        }

        // Overspending vs last month.
        $lastMonth = $user->entries()
            ->where('type', 'expense')
            ->whereBetween('occurred_on', [
                Carbon::now()->subMonthNoOverflow()->startOfMonth(),
                Carbon::now()->subMonthNoOverflow()->endOfMonth(),
            ])->sum('amount_cents');
        if ($lastMonth > 0 && $expenseCents > $lastMonth * 1.1) {
            $tips[] = ['tone' => 'amber', 'icon' => 'trending-up', 'text' => sprintf(
                'You have spent %s more than the same point last month. Review your top categories.',
                $user->money((int) ($expenseCents - $lastMonth)),
            )];
        }

        // Savings encouragement / target.
        $savingsCents = $incomeCents - $expenseCents;
        if ($incomeCents > 0 && $savingsCents > 0) {
            $rate = round($savingsCents / $incomeCents * 100);
            $tips[] = ['tone' => 'teal', 'icon' => 'piggy-bank', 'text' => sprintf(
                'Nice — you are saving %s this month (%d%% of income). Automating a transfer keeps it consistent.',
                $user->money($savingsCents),
                $rate,
            )];
        } elseif ($incomeCents > 0) {
            $tips[] = ['tone' => 'red', 'icon' => 'trending-down', 'text' => 'Spending has matched or exceeded income this month. Trimming one or two categories will rebuild your buffer.'];
        }

        if ($emiCents > 0 && $incomeCents > 0 && $emiCents / $incomeCents > 0.4) {
            $tips[] = ['tone' => 'amber', 'icon' => 'scale', 'text' => sprintf(
                'EMIs take %d%% of your income. Above 40%% is stretched — the Debt Coach can plan a faster payoff.',
                (int) round($emiCents / $incomeCents * 100),
            )];
        }

        // `message` is the key the mobile app reads; `text` is kept for the
        // web dashboard. They always carry identical copy.
        $tips = array_map(fn (array $tip) => $tip + ['message' => $tip['text']], $tips);

        return array_slice($tips, 0, 4);
    }

    /**
     * @param  Collection<int, Debt>  $cards
     */
    private function averageUtilisation(Collection $cards): float
    {
        $withLimit = $cards->filter(fn ($c) => $c->credit_limit_cents > 0);

        if ($withLimit->isEmpty()) {
            return 0.0;
        }

        $balance = (int) $withLimit->sum('balance_cents');
        $limit = (int) $withLimit->sum('credit_limit_cents');

        return $limit > 0 ? round($balance / $limit * 100, 1) : 0.0;
    }

    private function emergencyMonths(int $savedCents, int $monthlyExpenseCents): float
    {
        if ($monthlyExpenseCents <= 0) {
            return $savedCents > 0 ? 6 : 0;
        }

        return round($savedCents / $monthlyExpenseCents, 1);
    }

    /**
     * Clamp and normalise a value to 0..1 between a low and high bound.
     */
    private function scale(float $value, float $low, float $high): float
    {
        if ($high <= $low) {
            return $value >= $high ? 1 : 0;
        }

        return max(0, min(1, ($value - $low) / ($high - $low)));
    }

    private function durationLabel(int $months): string
    {
        if ($months <= 0) {
            return '—';
        }

        $years = intdiv($months, 12);
        $rem = $months % 12;
        $parts = [];
        if ($years > 0) {
            $parts[] = $years.' yr'.($years > 1 ? 's' : '');
        }
        if ($rem > 0) {
            $parts[] = $rem.' mo';
        }

        return implode(' ', $parts) ?: '0 mo';
    }

    private function relativeDay(int $days): string
    {
        return match (true) {
            $days < 0 => abs($days).' day'.(abs($days) === 1 ? '' : 's').' overdue',
            $days === 0 => 'Due today',
            $days === 1 => 'Due tomorrow',
            default => "Due in $days days",
        };
    }

    /**
     * Convert integer minor units to a major-unit number for the UI.
     */
    private function rupees(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
