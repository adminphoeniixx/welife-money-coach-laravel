<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A transaction with a repeat schedule has to survive being presented — the
 * app runs on immutable dates, so anything that narrows a date parameter back
 * to a mutable `Carbon` blows up the moment `repeat` is anything but `none`.
 */
class RecurringTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function recurringExpense(string $repeat = 'monthly'): array
    {
        return [
            'type' => 'expense',
            'amount' => 12000,
            'category' => 'Rent',
            'description' => 'Flat rent',
            'payee' => 'Landlord',
            'method' => 'upi',
            'occurred_on' => '2026-08-03',
            'repeat' => $repeat,
        ];
    }

    public function test_creating_a_recurring_transaction_returns_its_schedule(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/entries', $this->recurringExpense());

        $response->assertCreated()
            ->assertJsonPath('entry.repeat', 'monthly')
            ->assertJsonPath('entry.recurring', true)
            ->assertJsonPath('entry.next_occurrence', '2026-09-03');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function repeatSchedules(): array
    {
        return [
            'weekly' => ['weekly', '2026-08-10'],
            'monthly' => ['monthly', '2026-09-03'],
            'yearly' => ['yearly', '2027-08-03'],
        ];
    }

    #[DataProvider('repeatSchedules')]
    public function test_every_repeat_schedule_resolves_a_next_occurrence(string $repeat, string $expected): void
    {
        $this->travelTo('2026-08-05');
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/entries', $this->recurringExpense($repeat))
            ->assertCreated()
            ->assertJsonPath('entry.next_occurrence', $expected);
    }

    public function test_a_one_time_transaction_has_no_next_occurrence(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/entries', $this->recurringExpense('none'))
            ->assertCreated()
            ->assertJsonPath('entry.recurring', false)
            ->assertJsonPath('entry.next_occurrence', null);
    }

    public function test_transaction_detail_shows_the_recurring_schedule(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/entries', $this->recurringExpense())->json('entry.id');

        $this->getJson("/api/transactions/{$id}")
            ->assertOk()
            ->assertJsonPath('transaction.id', $id)
            ->assertJsonPath('transaction.repeat', 'monthly')
            ->assertJsonPath('transaction.recurring', true)
            ->assertJsonPath('transaction.payee', 'Landlord')
            ->assertJsonPath('transaction.method', 'upi')
            ->assertJsonPath('transaction.description', 'Flat rent');
    }

    public function test_the_transaction_list_carries_the_recurring_fields(): void
    {
        $this->travelTo('2026-08-05');
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/entries', $this->recurringExpense());

        $row = $this->getJson('/api/transactions?month=2026-08')
            ->assertOk()
            ->json('transactions.0');

        $this->assertSame('monthly', $row['repeat']);
        $this->assertTrue($row['recurring']);
        $this->assertSame('2026-09-03', $row['next_occurrence']);
        foreach (['id', 'type', 'amount', 'category', 'description', 'payee', 'method', 'occurred_on', 'attachments'] as $field) {
            $this->assertArrayHasKey($field, $row, "the list row is missing `{$field}`");
        }
    }

    public function test_the_calendar_projects_future_occurrences_of_a_recurring_transaction(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/entries', $this->recurringExpense())->assertCreated();

        $response = $this->getJson('/api/calendar?month=2026-09')->assertOk();

        $sources = collect($response->json('items') ?? [])
            ->pluck('source')
            ->merge(collect($response->json('days') ?? [])->pluck('items')->flatten(1)->pluck('source'));

        $this->assertTrue(
            $sources->contains('recurring_expense'),
            'September should carry the projected occurrence of a monthly expense.'
        );
    }
}
