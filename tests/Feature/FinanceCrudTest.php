<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Budget;
use App\Models\Debt;
use App\Models\Entry;
use App\Models\FinanceAccount;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('entries.store'), [
            'type' => 'expense', 'amount' => 640.50, 'category' => 'Food',
            'description' => 'Swiggy', 'occurred_on' => '2026-07-10',
        ])->assertRedirect();

        $entry = Entry::firstOrFail();
        $this->assertSame(64050, $entry->amount_cents);

        $this->actingAs($user)->put(route('entries.update', $entry), [
            'type' => 'expense', 'amount' => 700, 'occurred_on' => '2026-07-10',
        ])->assertRedirect();
        $this->assertSame(70000, $entry->fresh()->amount_cents);

        $this->actingAs($user)->delete(route('entries.destroy', $entry))->assertRedirect();
        $this->assertModelMissing($entry);
    }

    public function test_recording_a_payment_reduces_balance_and_closes_at_zero(): void
    {
        $user = User::factory()->create();
        $debt = $user->debts()->create([
            'name' => 'Card', 'kind' => 'credit_card', 'balance_cents' => 500000,
            'interest_rate' => 40, 'emi_cents' => 100000, 'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('debts.payment', $debt), ['amount' => 2000])->assertRedirect();
        $this->assertSame(300000, $debt->fresh()->balance_cents);
        $this->assertSame('active', $debt->fresh()->status);

        $this->actingAs($user)->post(route('debts.payment', $debt), ['amount' => 5000])->assertRedirect();
        $this->assertSame(0, $debt->fresh()->balance_cents);
        $this->assertSame('closed', $debt->fresh()->status);
    }

    public function test_loan_emi_tracking_updates_on_payment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('debts.store'), [
            'name' => 'Car Loan', 'kind' => 'loan', 'category' => 'vehicle',
            'interest_rate' => 9, 'balance' => 100000, 'principal' => 100000, 'emi' => 10000,
            'total_emis' => 10, 'emis_paid' => 2,
        ])->assertRedirect();

        $debt = Debt::firstOrFail();
        $this->assertSame(10, $debt->total_emis);
        $this->assertSame(2, $debt->emis_paid);

        // Recording a payment advances the EMI count and reduces the balance.
        $this->actingAs($user)->post(route('debts.payment', $debt), ['amount' => 10000])->assertRedirect();
        $debt->refresh();
        $this->assertSame(3, $debt->emis_paid);
        $this->assertSame(9000000, $debt->balance_cents);

        $this->actingAs($user)->get(route('debts.index'))->assertInertia(fn ($p) => $p
            ->where('loans.0.total_emis', 10)
            ->where('loans.0.emis_paid', 3)
            ->where('loans.0.remaining_emis', 7)
            ->where('loans.0.amount_paid', 10000)
            ->where('loans.0.remaining_amount', 90000)
            ->where('loans.0.repayment_progress', fn ($v) => (float) $v === 30.0));
    }

    public function test_loan_closes_when_tenure_is_complete(): void
    {
        $user = User::factory()->create();
        $debt = $user->debts()->create([
            'name' => 'Loan', 'kind' => 'loan', 'balance_cents' => 5000000, 'interest_rate' => 9,
            'emi_cents' => 1000000, 'total_emis' => 3, 'emis_paid' => 2, 'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('debts.payment', $debt), ['amount' => 5000])->assertRedirect();

        $debt->refresh();
        $this->assertSame(3, $debt->emis_paid);
        $this->assertSame('closed', $debt->status);
    }

    public function test_debt_and_asset_creation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('debts.store'), [
            'name' => 'Home Loan', 'kind' => 'loan', 'category' => 'home',
            'interest_rate' => 8.4, 'balance' => 200000, 'emi' => 15600,
        ])->assertRedirect();
        $this->assertSame(20000000, Debt::firstOrFail()->balance_cents);

        $this->actingAs($user)->post(route('assets.store'), [
            'name' => 'Gold', 'type' => 'gold', 'balance' => 320000,
        ])->assertRedirect();
        $this->assertSame(32000000, FinanceAccount::firstOrFail()->balance_cents);
    }

    public function test_budget_uniqueness_and_goal_contribution(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('budgets.store'), ['category' => 'Food', 'limit' => 5000])->assertRedirect();
        $this->assertSame(500000, Budget::firstOrFail()->limit_cents);

        // Same category again should fail the unique rule.
        $this->actingAs($user)->post(route('budgets.store'), ['category' => 'Food', 'limit' => 9000])
            ->assertSessionHasErrors('category');
        $this->assertSame(1, Budget::count());

        $this->actingAs($user)->post(route('goals.store'), [
            'name' => 'Emergency Fund', 'type' => 'emergency_fund', 'target' => 300000, 'saved' => 100000,
        ])->assertRedirect();
        $goal = Goal::firstOrFail();

        $this->actingAs($user)->post(route('goals.contribute', $goal), ['amount' => 50000])->assertRedirect();
        $this->assertSame(15000000, $goal->fresh()->saved_cents);
    }

    public function test_marking_recurring_bill_paid_rolls_due_date_forward(): void
    {
        $user = User::factory()->create();
        $bill = $user->bills()->create([
            'name' => 'Rent', 'kind' => 'bill', 'amount_cents' => 100000,
            'due_date' => Carbon::parse('2026-07-01'), 'repeat' => 'monthly', 'status' => 'upcoming',
        ]);

        $this->actingAs($user)->post(route('bills.paid', $bill))->assertRedirect();

        $fresh = $bill->fresh();
        $this->assertSame('upcoming', $fresh->status);
        $this->assertSame('2026-08-01', $fresh->due_date->format('Y-m-d'));

        // One-time bill just becomes paid.
        $oneOff = $user->bills()->create([
            'name' => 'Fee', 'kind' => 'bill', 'amount_cents' => 5000,
            'due_date' => Carbon::parse('2026-07-01'), 'repeat' => 'none', 'status' => 'upcoming',
        ]);
        $this->actingAs($user)->post(route('bills.paid', $oneOff))->assertRedirect();
        $this->assertSame('paid', $oneOff->fresh()->status);
    }

    public function test_coach_page_simulates_extra_payment_savings(): void
    {
        $user = User::factory()->create();
        $user->debts()->createMany([
            ['name' => 'Card', 'kind' => 'credit_card', 'balance_cents' => 9000000, 'interest_rate' => 42, 'emi_cents' => 400000, 'status' => 'active'],
            ['name' => 'Loan', 'kind' => 'loan', 'balance_cents' => 20000000, 'interest_rate' => 8.4, 'emi_cents' => 1500000, 'status' => 'active'],
        ]);

        $this->actingAs($user)->get(route('coach.index', ['strategy' => 'avalanche', 'extra' => 5000]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('debts/Coach')
                ->where('plan.strategy', 'avalanche')
                ->where('plan.extra', 5000)
                ->where('plan.order.0.name', 'Card') // highest APR first
                ->has('plan.interest_saved')
                ->has('plan.months_saved')
            );
    }

    public function test_users_cannot_mutate_others_records(): void
    {
        $owner = User::factory()->create();
        $entry = $owner->entries()->create(['type' => 'expense', 'amount_cents' => 1000, 'occurred_on' => now()]);

        $this->actingAs(User::factory()->create())
            ->delete(route('entries.destroy', $entry))
            ->assertForbidden();
    }
}
