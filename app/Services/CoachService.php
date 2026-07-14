<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Budget;
use App\Models\Debt;
use App\Models\Entry;
use App\Models\User;
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
        $priority = $this->priorityPayment($debts, $bills);

        return [
            'currency' => 'INR',
            'user' => ['name' => $user->name],
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
                'name' => $emergency->name,
                'target' => $this->rupees($emergency->target_cents),
                'saved' => $this->rupees($emergency->saved_cents),
                'progress' => $emergency->progress(),
            ] : null,
            'goals' => $goals->where('type', '!=', 'emergency_fund')->map(fn ($g) => [
                'name' => $g->name,
                'target' => $this->rupees($g->target_cents),
                'saved' => $this->rupees($g->saved_cents),
                'progress' => $g->progress(),
            ])->values(),
            'budgets' => $this->budgetStatus($budgets, $monthEntries),
            'upcoming' => $this->upcomingBills($bills),
            'spending' => $this->spendingBreakdown($monthEntries),
            'trend' => $this->monthlyTrend($user),
            'tips' => $this->tips($incomeCents, $expenseCents, $emiCents, $avgUtilisation, $cards, $overdueCount, $user),
            'debts' => $debts->sortByDesc('interest_rate')->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'institution' => $d->institution,
                'kind' => $d->kind,
                'balance' => $this->rupees($d->balance_cents),
                'interest_rate' => (float) $d->interest_rate,
                'emi' => $this->rupees($d->emi_cents),
                'utilisation' => $d->isCard() ? $d->utilisation() : null,
                'limit' => $d->credit_limit_cents ? $this->rupees($d->credit_limit_cents) : null,
            ])->values(),
        ];
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
    private function priorityPayment(Collection $debts, Collection $bills): ?array
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
                'It costs about ₹%s in interest every month. Clearing it first saves the most money overall.',
                number_format($monthlyInterestCents / 100),
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
                    'name' => $bill->name,
                    'kind' => $bill->kind,
                    'category' => $bill->category,
                    'amount' => $this->rupees($bill->amount_cents),
                    'due_date' => $bill->due_date->format('d M'),
                    'days' => $days,
                    'when' => $this->relativeDay($days),
                    'overdue' => $bill->status === 'overdue' || $days < 0,
                ];
            })->values()->all();
    }

    /**
     * Current-month expense breakdown by category with percentages.
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
                'amount' => (int) $rows->sum('amount_cents'),
            ])
            ->sortByDesc('amount')
            ->values()
            ->map(fn ($s) => [
                'category' => $s['category'],
                'amount' => $this->rupees($s['amount']),
                'percent' => $totalCents > 0 ? round($s['amount'] / $totalCents * 100) : 0,
            ])->all();

        return ['total' => $this->rupees($totalCents), 'slices' => $slices];
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
                'You have spent ₹%s more than the same point last month. Review your top categories.',
                number_format(($expenseCents - $lastMonth) / 100),
            )];
        }

        // Savings encouragement / target.
        $savingsCents = $incomeCents - $expenseCents;
        if ($incomeCents > 0 && $savingsCents > 0) {
            $rate = round($savingsCents / $incomeCents * 100);
            $tips[] = ['tone' => 'teal', 'icon' => 'piggy-bank', 'text' => sprintf(
                'Nice — you are saving ₹%s this month (%d%% of income). Automating a transfer keeps it consistent.',
                number_format($savingsCents / 100),
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
