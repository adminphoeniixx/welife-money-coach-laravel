<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Derives achievements and smart notifications from a user's finance data.
 * Everything is computed with deterministic rules from what already exists,
 * so no extra tracking tables are needed.
 */
class InsightService
{
    /**
     * Earned + locked milestone badges.
     *
     * @return array<int, array<string, mixed>>
     */
    public function achievements(User $user): array
    {
        $now = Carbon::now();
        $assets = (int) $user->financeAccounts()->sum('balance_cents');
        $liabilities = (int) $user->debts()->where('status', 'active')->sum('balance_cents');
        $closedDebts = $user->debts()->where('status', 'closed')->count();
        $bestSavingMonth = $this->bestMonthlySavings($user);

        $emergency = $user->goals()->where('type', 'emergency_fund')->get();
        $emergencyReady = $emergency->isNotEmpty()
            && $emergency->every(fn ($g) => $g->target_cents > 0 && $g->saved_cents >= $g->target_cents);

        $goalReached = $user->goals()->get()->contains(fn ($g) => $g->target_cents > 0 && $g->saved_cents >= $g->target_cents);

        $cards = $user->debts()->where('kind', 'credit_card')->where('status', 'active')->get();
        $cardsHealthy = $cards->isNotEmpty()
            && $cards->every(fn ($c) => $c->utilisation() < 30);

        $definitions = [
            ['key' => 'first_step', 'icon' => '🌱', 'title' => 'First Step', 'desc' => 'Logged your first transaction', 'earned' => $user->entries()->exists()],
            ['key' => 'net_positive', 'icon' => '📈', 'title' => 'In the Green', 'desc' => 'Positive net worth', 'earned' => $assets - $liabilities > 0],
            ['key' => 'debt_slayer', 'icon' => '⚔️', 'title' => 'Debt Slayer', 'desc' => 'Fully paid off a debt', 'earned' => $closedDebts > 0],
            ['key' => 'big_saver', 'icon' => '💰', 'title' => 'Big Saver', 'desc' => 'Saved '.$user->money(5000000).' in a month', 'earned' => $bestSavingMonth >= 5000000],
            ['key' => 'emergency_ready', 'icon' => '🛡️', 'title' => 'Emergency Ready', 'desc' => 'Fully funded emergency fund', 'earned' => $emergencyReady],
            ['key' => 'goal_getter', 'icon' => '🎯', 'title' => 'Goal Getter', 'desc' => 'Reached a savings goal', 'earned' => $goalReached],
            ['key' => 'card_master', 'icon' => '💳', 'title' => 'Card Master', 'desc' => 'All cards under 30% utilisation', 'earned' => $cardsHealthy],
            ['key' => 'vault_keeper', 'icon' => '🔐', 'title' => 'Vault Keeper', 'desc' => 'Secured a document in the vault', 'earned' => $user->documents()->exists()],
        ];

        return array_map(fn ($d) => [
            'key' => $d['key'],
            'icon' => $d['icon'],
            'title' => $d['title'],
            'description' => $d['desc'],
            'earned' => (bool) $d['earned'],
        ], $definitions);
    }

    /**
     * Smart, prioritised notifications from the current state.
     *
     * Each item carries a deterministic `id`: the same situation always
     * produces the same key, so a read receipt sticks, and a bill that rolls
     * forward to a new due date becomes a new (unread) notification on its own.
     *
     * @return array<int, array<string, mixed>>
     */
    public function notifications(User $user): array
    {
        $now = Carbon::now();
        $items = [];

        // Overdue bills.
        foreach ($user->bills()->where('status', 'overdue')->get() as $bill) {
            $items[] = [
                'id' => 'bill_overdue:'.$bill->id.':'.$bill->due_date->format('Y-m-d'),
                'type' => 'bill_overdue', 'tone' => 'red', 'icon' => 'alert', 'title' => 'Payment overdue',
                'text' => $bill->name.' ('.$this->money($user, $bill->amount_cents).') was due '.$bill->due_date->diffForHumans().'.',
                'route' => '/reminders', 'created_at' => $bill->due_date->copy()->startOfDay()->toIso8601String(),
            ];
        }

        // Due soon (next 3 days).
        foreach ($user->bills()->where('status', 'upcoming')->get() as $bill) {
            $days = (int) round($now->copy()->startOfDay()->diffInDays($bill->due_date, false));
            if ($days >= 0 && $days <= 3) {
                $items[] = [
                    'id' => 'bill_due:'.$bill->id.':'.$bill->due_date->format('Y-m-d'),
                    'type' => 'bill_due', 'tone' => 'amber', 'icon' => 'clock', 'title' => 'Due soon',
                    'text' => $bill->name.' — '.$this->money($user, $bill->amount_cents).' due '.($days === 0 ? 'today' : 'in '.$days.' day'.($days === 1 ? '' : 's')).'.',
                    'route' => '/reminders', 'created_at' => $now->copy()->startOfDay()->toIso8601String(),
                ];
            }
        }

        // High credit utilisation.
        foreach ($user->debts()->where('kind', 'credit_card')->where('status', 'active')->get() as $card) {
            if ($card->utilisation() >= 80) {
                $items[] = [
                    'id' => 'card_utilisation:'.$card->id.':'.$now->format('Y-m'),
                    'type' => 'card_utilisation', 'tone' => 'red', 'icon' => 'credit-card', 'title' => 'High card usage',
                    'text' => $card->name.' is at '.$card->utilisation().'% utilisation. Paying it down lifts your credit score.',
                    'route' => '/debts', 'created_at' => $now->toIso8601String(),
                ];
            }
        }

        // Budgets exceeded.
        $spent = $user->entries()->where('type', 'expense')
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->selectRaw('category, sum(amount_cents) as s')->groupBy('category')->pluck('s', 'category');
        foreach ($user->budgets()->whereNull('household_id')->get() as $budget) {
            $used = (int) ($spent[$budget->category] ?? 0);
            if ($budget->limit_cents > 0 && $used > $budget->limit_cents) {
                $items[] = [
                    'id' => 'budget_exceeded:'.$budget->id.':'.$now->format('Y-m'),
                    'type' => 'budget_exceeded', 'tone' => 'amber', 'icon' => 'trending-up', 'title' => 'Budget exceeded',
                    'text' => 'You have gone over your '.$budget->category.' budget ('.$this->money($user, $used).' of '.$this->money($user, $budget->limit_cents).').',
                    'route' => '/planning', 'created_at' => $now->toIso8601String(),
                ];
            }
        }

        // Positive nudge — on-time streak / savings.
        $income = (int) $user->entries()->where('type', 'income')->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->sum('amount_cents');
        $expense = (int) $spent->sum();
        if ($income > 0 && $income - $expense > 0) {
            $items[] = [
                'id' => 'on_track:'.$now->format('Y-m'),
                'type' => 'on_track', 'tone' => 'teal', 'icon' => 'check', 'title' => 'On track',
                'text' => 'Nice — you have saved '.$this->money($user, $income - $expense).' so far this month. Keep it up!',
                'route' => '/insights', 'created_at' => $now->toIso8601String(),
            ];
        }

        $readKeys = $user->notificationReads()->pluck('key')->flip();

        // `message` is what the mobile app reads; `text` stays for the web UI.
        return array_map(fn (array $item) => $item + [
            'message' => $item['text'],
            'read' => $readKeys->has($item['id']),
        ], $items);
    }

    /**
     * How many notifications this user has not yet opened — the home red dot.
     */
    public function unreadCount(User $user): int
    {
        return count(array_filter($this->notifications($user), fn (array $n) => ! $n['read']));
    }

    /**
     * The read-receipt key for a reminder. It includes the due date, so a
     * recurring bill that rolls forward becomes unread again by itself.
     */
    public static function reminderKey(Bill $bill): string
    {
        return 'reminder:'.$bill->id.':'.$bill->due_date->format('Y-m-d');
    }

    /**
     * How many reminders are asking for attention right now: overdue, or
     * inside their "remind me N days before" window, and not yet opened.
     */
    public function remindersUnreadCount(User $user): int
    {
        $today = Carbon::now()->startOfDay();
        $readKeys = $user->notificationReads()->pluck('key')->flip();

        return $user->bills()->where('status', '!=', 'paid')->get()
            ->filter(function (Bill $bill) use ($today, $readKeys) {
                $days = (int) round($today->diffInDays($bill->due_date, false));

                return $days <= $bill->remind_days_before && ! $readKeys->has(self::reminderKey($bill));
            })->count();
    }

    private function bestMonthlySavings(User $user): int
    {
        $entries = $user->entries()->get(['type', 'amount_cents', 'occurred_on']);

        $byMonth = [];
        foreach ($entries as $e) {
            $key = $e->occurred_on->format('Y-m');
            $byMonth[$key] ??= 0;
            $byMonth[$key] += $e->type === 'income' ? $e->amount_cents : -$e->amount_cents;
        }

        return empty($byMonth) ? 0 : (int) max($byMonth);
    }

    /** Render an amount in the currency this user signed up with. */
    private function money(User $user, int $cents): string
    {
        return $user->money($cents);
    }
}
