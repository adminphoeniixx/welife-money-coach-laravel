<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanceInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithData(): User
    {
        $user = User::factory()->create();
        $user->debts()->create(['name' => 'Home Loan', 'kind' => 'loan', 'balance_cents' => 100000, 'interest_rate' => 8, 'emi_cents' => 5000, 'status' => 'active']);
        $user->bills()->create(['name' => 'Electricity', 'kind' => 'bill', 'amount_cents' => 50000, 'due_date' => Carbon::now()->addDays(2), 'status' => 'upcoming']);
        $user->entries()->create(['type' => 'expense', 'category' => 'Food', 'amount_cents' => 30000, 'description' => 'Swiggy dinner', 'occurred_on' => Carbon::now()]);
        $user->financeAccounts()->create(['name' => 'HDFC Savings', 'type' => 'bank', 'balance_cents' => 500000]);

        return $user;
    }

    public function test_calendar_renders_month_grid(): void
    {
        $this->actingAs($this->userWithData())
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('calendar/Index')->has('days', 42));
    }

    public function test_search_finds_across_resources(): void
    {
        $this->actingAs($this->userWithData())
            ->get(route('search.index', ['q' => 'Swiggy']))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Search')->where('count', 1)->has('results.transactions', 1));

        $this->actingAs($this->userWithData())
            ->get(route('search.index', ['q' => 'Home']))
            ->assertInertia(fn ($p) => $p->has('results.debts', 1));
    }

    public function test_achievements_and_notifications_render(): void
    {
        $user = $this->userWithData();

        $this->actingAs($user)->get(route('achievements.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('insights/Achievements')->has('achievements')->where('earned', fn ($e) => $e >= 1));

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('insights/Notifications')->has('notifications'));
    }

    public function test_report_renders_and_exports_csv(): void
    {
        $user = $this->userWithData();
        $month = Carbon::now()->format('Y-m');

        $this->actingAs($user)->get(route('reports.index', ['month' => $month]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('reports/Index')->where('summary.expense', 300));

        $response = $this->actingAs($user)->get(route('reports.export', ['month' => $month]));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Swiggy dinner', $response->streamedContent());
    }

    public function test_challenge_join_progress_and_completion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('challenges.store'), ['key' => 'save_5000'])->assertRedirect();
        $challenge = Challenge::firstOrFail();
        $this->assertSame(500000, $challenge->target_cents);
        $this->assertSame('active', $challenge->status);

        $this->actingAs($user)->post(route('challenges.progress', $challenge), ['amount' => 5000])->assertRedirect();
        $this->assertSame('completed', $challenge->fresh()->status);
        $this->assertSame(500000, $challenge->fresh()->progress_cents);
    }

    public function test_challenge_ownership_enforced(): void
    {
        $owner = User::factory()->create();
        $owner->challenges()->create([
            'key' => 'save_5000', 'title' => 'x', 'target_cents' => 100, 'progress_cents' => 0,
            'status' => 'active', 'started_on' => now(), 'ends_on' => now()->endOfMonth(),
        ]);
        $challenge = Challenge::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->delete(route('challenges.destroy', $challenge))
            ->assertForbidden();
    }
}
