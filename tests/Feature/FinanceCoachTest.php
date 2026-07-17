<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CoachService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FinanceCoachTest extends TestCase
{
    use RefreshDatabase;

    private function seedUser(): User
    {
        $user = User::factory()->create();

        $user->financeAccounts()->create([
            'name' => 'Bank', 'type' => 'bank', 'balance_cents' => 50000000,
        ]);

        $card = $user->debts()->create([
            'name' => 'Test Card', 'kind' => 'credit_card', 'balance_cents' => 9000000,
            'interest_rate' => 42, 'emi_cents' => 400000, 'credit_limit_cents' => 10000000,
            'min_due_cents' => 400000, 'due_day' => 22, 'status' => 'active',
        ]);
        $user->debts()->create([
            'name' => 'Home Loan', 'kind' => 'loan', 'category' => 'home', 'principal_cents' => 80000000,
            'balance_cents' => 20000000, 'interest_rate' => 8.4, 'emi_cents' => 1500000,
            'due_day' => 5, 'status' => 'active',
        ]);

        $user->entries()->create([
            'type' => 'income', 'category' => 'Salary', 'amount_cents' => 8500000,
            'occurred_on' => Carbon::now()->startOfMonth(),
        ]);
        $user->entries()->create([
            'type' => 'expense', 'category' => 'Food', 'amount_cents' => 300000,
            'occurred_on' => Carbon::now()->startOfMonth()->addDays(2),
        ]);

        $user->goals()->create([
            'name' => 'Emergency Fund', 'type' => 'emergency_fund',
            'target_cents' => 30000000, 'saved_cents' => 15000000,
        ]);
        $user->budgets()->create(['category' => 'Food', 'limit_cents' => 500000]);
        $user->bills()->create([
            'name' => 'Card Bill', 'kind' => 'emi', 'amount_cents' => 400000, 'debt_id' => $card->id,
            'due_date' => Carbon::now()->addDay(), 'status' => 'upcoming',
        ]);
        $user->bills()->create([
            'name' => 'Old Bill', 'kind' => 'bill', 'amount_cents' => 50000,
            'due_date' => Carbon::now()->subDays(2), 'status' => 'overdue',
        ]);

        return $user;
    }

    public function test_dashboard_returns_coach_snapshot(): void
    {
        $this->actingAs($this->seedUser())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('kpis.net_worth', 500000 - 290000) // assets 5,00,000 - debt 2,90,000
                ->where('kpis.income', 85000)
                ->has('health.score')
                ->has('health.status')
                ->where('priority.name', 'Test Card') // highest APR is prioritised
                ->has('emergency_fund')
                ->has('tips')
                ->has('upcoming')
                ->has('trend', 6)
            );
    }

    public function test_priority_targets_highest_interest_debt(): void
    {
        $snapshot = app(CoachService::class)->snapshot($this->seedUser());

        $this->assertSame('Test Card', $snapshot['priority']['name']);
        $this->assertSame('Home Loan', $snapshot['debts'][1]['name']);
        $this->assertGreaterThan(0, $snapshot['debt_free']['months']);
    }

    public function test_transactions_page_renders(): void
    {
        $this->actingAs($this->seedUser())
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('transactions/Index')
                ->has('groups')
                ->where('totals.income', 85000)
            );
    }

    public function test_debts_page_renders(): void
    {
        $this->actingAs($this->seedUser())
            ->get(route('debts.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('debts/Index')
                ->has('loans', 1)
                ->has('cards', 1)
                ->has('payoff_order', 2)
            );
    }

    public function test_reminders_page_renders(): void
    {
        $this->actingAs($this->seedUser())
            ->get(route('reminders.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reminders/Index')
                ->has('overdue', 1)
                ->has('upcoming', 1)
            );
    }
}
