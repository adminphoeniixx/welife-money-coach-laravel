<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds a rich, realistic finance dataset for the demo user so the coach
 * dashboard has something meaningful to show. Idempotent: re-running wipes
 * the target user's finance rows and rebuilds them.
 */
class FinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Rahul Sharma', 'password' => bcrypt('password')],
        );

        // Clean slate.
        $user->financeAccounts()->delete();
        $user->debts()->delete();
        $user->entries()->delete();
        $user->budgets()->delete();
        $user->goals()->delete();
        $user->bills()->delete();

        $this->seedAssets($user);
        $debts = $this->seedDebts($user);
        $this->seedGoals($user);
        $this->seedBudgets($user);
        $this->seedEntries($user);
        $this->seedBills($user, $debts);
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
}
