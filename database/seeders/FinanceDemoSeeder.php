<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Debt;
use App\Models\DebtDocument;
use App\Models\User;
use Database\Seeders\Support\DemoFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds a rich, realistic finance dataset for the demo user so the coach
 * dashboard has something meaningful to show. Idempotent: re-running wipes
 * the target user's finance rows and rebuilds them.
 *
 * Between this seeder, {@see VaultDemoSeeder} and {@see FamilyDemoSeeder},
 * every authenticated endpoint in routes/api.php answers with real data.
 */
class FinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Rahul Sharma', 'password' => bcrypt('password')],
        );

        // Clean slate. Debt payments + debt documents cascade with the debts.
        $user->financeAccounts()->delete();
        $this->purgeDebtDocuments($user);
        $user->debts()->delete();
        $user->entries()->delete();
        $user->budgets()->delete();
        $user->goals()->delete();
        $user->bills()->delete();
        $user->challenges()->delete();

        $this->seedPreferences($user);
        $this->seedAssets($user);
        $debts = $this->seedDebts($user);
        $this->seedGoals($user);
        $this->seedBudgets($user);
        $this->seedEntries($user);
        $this->seedBills($user, $debts);
        $this->seedDebtPayments($user, $debts);
        $this->seedDebtDocuments($user, $debts);
        $this->seedChallenges($user);
    }

    /**
     * Onboarding answers + region and notification preferences, so the
     * onboarding / settings / profile endpoints return a configured account.
     */
    private function seedPreferences(User $user): void
    {
        $user->forceFill([
            'currency' => 'INR',
            'locale' => 'en-IN',
            'country' => 'IN',
            'primary_goal' => 'get_out_of_debt',
            'notifications_enabled' => true,
            'notification_prefs' => [
                'bill_reminders' => true,
                'budget_alerts' => true,
                'goal_milestones' => true,
                'weekly_summary' => false,
                'debt_tips' => true,
            ],
            'onboarded' => true,
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
        ])->save();
    }

    private function seedAssets(User $user): void
    {
        $assets = [
            ['name' => 'HDFC Savings', 'type' => 'bank', 'balance_cents' => 24035000],
            ['name' => 'Cash in hand', 'type' => 'cash', 'balance_cents' => 1500000],
            ['name' => 'Gold (sovereign)', 'type' => 'gold', 'balance_cents' => 32000000],
            ['name' => 'SBI Fixed Deposit', 'type' => 'fixed_deposit', 'balance_cents' => 50000000],
            ['name' => 'Nippon Mutual Fund', 'type' => 'mutual_fund', 'balance_cents' => 38500000],
            ['name' => 'Zerodha Stocks', 'type' => 'stocks', 'balance_cents' => 21750000],
        ];

        foreach ($assets as $a) {
            $user->financeAccounts()->create($a + ['currency' => 'INR']);
        }
    }

    /**
     * @return array<string, Debt>
     */
    private function seedDebts(User $user): array
    {
        $debts = [];

        $debts['home'] = $user->debts()->create([
            'name' => 'Home Loan', 'institution' => 'HDFC', 'kind' => 'loan', 'category' => 'home',
            'principal_cents' => 84000000, 'balance_cents' => 21000000, 'interest_rate' => 8.40,
            'emi_cents' => 1560000, 'total_emis' => 240, 'emis_paid' => 180, 'due_day' => 23, 'status' => 'active',
            'opened_on' => Carbon::now()->subYears(6),
        ]);

        $debts['car'] = $user->debts()->create([
            'name' => 'Car Loan', 'institution' => 'SBI', 'kind' => 'loan', 'category' => 'vehicle',
            'principal_cents' => 12000000, 'balance_cents' => 4800000, 'interest_rate' => 9.20,
            'emi_cents' => 520000, 'total_emis' => 36, 'emis_paid' => 22, 'due_day' => 28, 'status' => 'active',
            'opened_on' => Carbon::now()->subYears(2),
        ]);

        $debts['hdfc_card'] = $user->debts()->create([
            'name' => 'HDFC Millennia', 'institution' => 'HDFC', 'kind' => 'credit_card',
            'balance_cents' => 8400000, 'interest_rate' => 42.00, 'emi_cents' => 420000,
            'credit_limit_cents' => 10000000, 'min_due_cents' => 420000, 'due_day' => 22, 'status' => 'active',
        ]);

        $debts['icici_card'] = $user->debts()->create([
            'name' => 'Amazon ICICI', 'institution' => 'ICICI', 'kind' => 'credit_card',
            'balance_cents' => 4160000, 'interest_rate' => 38.00, 'emi_cents' => 610000,
            'credit_limit_cents' => 15000000, 'min_due_cents' => 610000, 'due_day' => 25, 'status' => 'active',
        ]);

        return $debts;
    }

    private function seedGoals(User $user): void
    {
        $user->goals()->create([
            'name' => 'Emergency Fund', 'type' => 'emergency_fund',
            'target_cents' => 30000000, 'saved_cents' => 17500000,
            'target_date' => Carbon::now()->addMonths(8),
        ]);

        $user->goals()->create([
            'name' => 'Goa Vacation', 'type' => 'savings',
            'target_cents' => 12000000, 'saved_cents' => 4500000,
            'target_date' => Carbon::now()->addMonths(5),
        ]);
    }

    private function seedBudgets(User $user): void
    {
        $budgets = [
            ['category' => 'Food', 'limit_cents' => 1500000],
            ['category' => 'Transport', 'limit_cents' => 800000],
            ['category' => 'Entertainment', 'limit_cents' => 400000],
            ['category' => 'Shopping', 'limit_cents' => 1000000],
            ['category' => 'Utilities', 'limit_cents' => 600000],
        ];

        foreach ($budgets as $b) {
            $user->budgets()->create($b + ['currency' => 'INR']);
        }
    }

    private function seedEntries(User $user): void
    {
        // 6 months of income + expenses so the trend chart is populated.
        for ($m = 5; $m >= 0; $m--) {
            $month = Carbon::now()->startOfMonth()->subMonths($m);

            // Income: salary every month, freelance most months.
            $user->entries()->create([
                'type' => 'income', 'category' => 'Salary', 'amount_cents' => 8500000,
                'description' => 'Salary — Infosys', 'payee' => 'Infosys', 'method' => 'Bank transfer',
                'occurred_on' => $month->copy()->day(1),
            ]);
            if ($m % 2 === 0) {
                $user->entries()->create([
                    'type' => 'income', 'category' => 'Freelance', 'amount_cents' => 1200000,
                    'description' => 'Freelance — Acme', 'payee' => 'Acme Corp', 'method' => 'Bank transfer',
                    'occurred_on' => $month->copy()->day(15),
                ]);
            }

            foreach ($this->monthlyExpenses($m) as $e) {
                $day = min($e['day'], $month->copy()->endOfMonth()->day);
                $user->entries()->create([
                    'type' => 'expense', 'category' => $e['category'], 'amount_cents' => $e['amount_cents'],
                    'description' => $e['description'], 'payee' => $e['payee'] ?? null, 'method' => $e['method'] ?? 'UPI',
                    'occurred_on' => $month->copy()->day($day),
                ]);
            }
        }
    }

    /**
     * A month's worth of expenses. The current month (offset 0) intentionally
     * runs the Entertainment budget over to exercise the "exceeded" state.
     *
     * @return array<int, array<string, mixed>>
     */
    private function monthlyExpenses(int $monthOffset): array
    {
        $base = [
            ['category' => 'Loans', 'description' => 'Home Loan EMI', 'amount_cents' => 1560000, 'day' => 5, 'method' => 'Auto-debit'],
            ['category' => 'Loans', 'description' => 'Car Loan EMI', 'amount_cents' => 520000, 'day' => 6, 'method' => 'Auto-debit'],
            ['category' => 'Housing', 'description' => 'Maintenance', 'amount_cents' => 350000, 'day' => 3],
            ['category' => 'Food', 'description' => 'BigBasket groceries', 'amount_cents' => 312000, 'day' => 4, 'payee' => 'BigBasket'],
            ['category' => 'Food', 'description' => 'Swiggy', 'amount_cents' => 214000, 'day' => 12, 'payee' => 'Swiggy'],
            ['category' => 'Transport', 'description' => 'HP Petrol', 'amount_cents' => 360000, 'day' => 8, 'payee' => 'HP'],
            ['category' => 'Utilities', 'description' => 'Electricity', 'amount_cents' => 189000, 'day' => 10],
            ['category' => 'Utilities', 'description' => 'Jio Postpaid', 'amount_cents' => 59900, 'day' => 14, 'payee' => 'Jio'],
            ['category' => 'Entertainment', 'description' => 'Netflix', 'amount_cents' => 64900, 'day' => 9, 'payee' => 'Netflix', 'method' => 'Credit Card'],
            ['category' => 'Shopping', 'description' => 'Amazon order', 'amount_cents' => 289000, 'day' => 16, 'payee' => 'Amazon'],
        ];

        if ($monthOffset === 0) {
            // Push the current month over its Entertainment budget.
            $base[] = ['category' => 'Entertainment', 'description' => 'Concert tickets', 'amount_cents' => 450000, 'day' => 11, 'payee' => 'BookMyShow'];
        }

        return $base;
    }

    /**
     * @param  array<string, Debt>  $debts
     */
    private function seedBills(User $user, array $debts): void
    {
        $today = Carbon::now()->startOfDay();

        $bills = [
            ['name' => 'HDFC Millennia', 'kind' => 'emi', 'category' => 'Credit Card', 'amount_cents' => 420000, 'due_offset' => 1, 'debt' => 'hdfc_card'],
            ['name' => 'Home Loan EMI', 'kind' => 'emi', 'category' => 'Loan', 'amount_cents' => 1560000, 'due_offset' => 5, 'debt' => 'home'],
            ['name' => 'Amazon ICICI', 'kind' => 'emi', 'category' => 'Credit Card', 'amount_cents' => 610000, 'due_offset' => 8, 'debt' => 'icici_card'],
            ['name' => 'Jio Postpaid', 'kind' => 'bill', 'category' => 'Mobile', 'amount_cents' => 59900, 'due_offset' => 6],
            ['name' => 'Car Loan EMI', 'kind' => 'emi', 'category' => 'Loan', 'amount_cents' => 520000, 'due_offset' => 12, 'debt' => 'car'],
            ['name' => 'Electricity Bill', 'kind' => 'bill', 'category' => 'Utilities', 'amount_cents' => 189000, 'due_offset' => 9],
            ['name' => 'Vodafone Broadband', 'kind' => 'bill', 'category' => 'Internet', 'amount_cents' => 49900, 'due_offset' => -2, 'status' => 'overdue'],
            // Subscriptions.
            ['name' => 'Netflix', 'kind' => 'subscription', 'category' => 'Entertainment', 'amount_cents' => 64900, 'due_offset' => 10],
            ['name' => 'Amazon Prime', 'kind' => 'subscription', 'category' => 'Entertainment', 'amount_cents' => 149900, 'due_offset' => 18],
            ['name' => 'Spotify', 'kind' => 'subscription', 'category' => 'Entertainment', 'amount_cents' => 11900, 'due_offset' => 14],
        ];

        foreach ($bills as $b) {
            $user->bills()->create([
                'debt_id' => isset($b['debt']) ? $debts[$b['debt']]->id : null,
                'name' => $b['name'],
                'kind' => $b['kind'],
                'category' => $b['category'],
                'amount_cents' => $b['amount_cents'],
                'due_date' => $today->copy()->addDays($b['due_offset']),
                'repeat' => 'monthly',
                'remind_days_before' => 3,
                'status' => $b['status'] ?? 'upcoming',
            ]);
        }
    }

    /**
     * Six months of payment history per debt so GET /api/debts/{debt} shows a
     * populated timeline. Balances walk backwards from today's balance.
     *
     * @param  array<string, Debt>  $debts
     */
    private function seedDebtPayments(User $user, array $debts): void
    {
        foreach ($debts as $debt) {
            $balance = $debt->balance_cents;
            $emiNumber = (int) ($debt->emis_paid ?? 0); // Cards track no EMI number.

            for ($m = 0; $m < 6; $m++) {
                $amount = $debt->emi_cents ?: (int) round($balance * 0.05);

                if ($amount <= 0) {
                    continue;
                }

                $debt->payments()->create([
                    'user_id' => $user->id,
                    'amount_cents' => $amount,
                    'balance_after_cents' => max(0, $balance),
                    'emi_number' => $debt->isCard() ? null : ($emiNumber > 0 ? $emiNumber : null),
                    'paid_on' => Carbon::now()->startOfMonth()->subMonths($m)->day(min(5, $debt->due_day ?? 5)),
                ]);

                // Step backwards: the balance before this payment was higher.
                $balance += $amount;
                $emiNumber--;
            }
        }
    }

    /**
     * Attach an encrypted sanction letter / statement to each debt so the
     * debt-documents view + download endpoints have something to serve.
     *
     * @param  array<string, Debt>  $debts
     */
    private function seedDebtDocuments(User $user, array $debts): void
    {
        $attachments = [
            'home' => [['Home Loan Sanction Letter.pdf', 'pdf'], ['Home Loan Statement.pdf', 'pdf']],
            'car' => [['Car Loan Agreement.pdf', 'pdf']],
            'hdfc_card' => [['HDFC Millennia Card.png', 'png']],
            'icici_card' => [['ICICI Statement Jan.pdf', 'pdf']],
        ];

        foreach ($attachments as $key => $files) {
            if (! isset($debts[$key])) {
                continue;
            }

            foreach ($files as [$name, $kind]) {
                $contents = $kind === 'pdf'
                    ? DemoFile::pdf(pathinfo($name, PATHINFO_FILENAME))
                    : DemoFile::png();

                $path = 'debt-documents/'.$user->id.'/'.Str::uuid()->toString().'.enc';
                Storage::disk(DebtDocument::DISK)->put($path, Crypt::encryptString($contents));

                $debts[$key]->documents()->create([
                    'user_id' => $user->id,
                    'original_name' => $name,
                    'mime_type' => $kind === 'pdf' ? 'application/pdf' : 'image/png',
                    'size_bytes' => strlen($contents),
                    'path' => $path,
                ]);
            }
        }
    }

    /**
     * Remove the encrypted blobs behind the user's debt attachments before the
     * debts (and their rows) are wiped, so re-seeding doesn't orphan files.
     */
    private function purgeDebtDocuments(User $user): void
    {
        DebtDocument::where('user_id', $user->id)->get()
            ->each(fn (DebtDocument $d) => Storage::disk(DebtDocument::DISK)->delete($d->path));
    }

    /**
     * Two challenges in flight and one already won, plus the untouched presets
     * the challenges endpoint offers alongside them.
     */
    private function seedChallenges(User $user): void
    {
        $now = Carbon::now();

        $challenges = [
            ['key' => 'save_10000', 'progress_cents' => 620000, 'status' => 'active',
                'started_on' => $now->copy()->startOfMonth(), 'ends_on' => $now->copy()->endOfMonth()],
            ['key' => 'no_spend_7', 'progress_cents' => 400, 'status' => 'active',
                'started_on' => $now->copy()->subDays(4), 'ends_on' => $now->copy()->addDays(3)],
            ['key' => 'save_5000', 'progress_cents' => 500000, 'status' => 'completed',
                'started_on' => $now->copy()->subMonth()->startOfMonth(), 'ends_on' => $now->copy()->subMonth()->endOfMonth()],
        ];

        foreach ($challenges as $c) {
            $preset = Challenge::PRESETS[$c['key']];

            $user->challenges()->create([
                'key' => $c['key'],
                'title' => $preset['title'],
                'description' => $preset['description'],
                'target_cents' => $preset['target'],
                'progress_cents' => $c['progress_cents'],
                'status' => $c['status'],
                'started_on' => $c['started_on'],
                'ends_on' => $c['ends_on'],
            ]);
        }
    }
}
