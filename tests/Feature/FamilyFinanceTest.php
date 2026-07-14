<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Entry;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeFamily(User $owner, string $name = 'The Sharmas'): Household
    {
        $household = Household::create(['owner_id' => $owner->id, 'name' => $name]);
        $household->members()->attach($owner->id, ['role' => 'owner']);

        return $household;
    }

    public function test_user_can_create_a_family_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('family.store'), ['name' => 'The Sharmas'])->assertRedirect();

        $household = Household::firstOrFail();
        $this->assertSame($user->id, $household->owner_id);
        $this->assertSame('owner', (string) $household->members()->first()->pivot->role);
    }

    public function test_cannot_create_a_second_family(): void
    {
        $user = User::factory()->create();
        $this->makeFamily($user);

        $this->actingAs($user)->post(route('family.store'), ['name' => 'Another'])->assertStatus(409);
        $this->assertSame(1, Household::count());
    }

    public function test_invite_flow_and_matching_email_can_join(): void
    {
        $owner = User::factory()->create();
        $household = $this->makeFamily($owner);
        $partner = User::factory()->create(['email' => 'wife@example.com']);

        $this->actingAs($owner)->post(route('family.invite'), ['email' => 'wife@example.com', 'role' => 'partner'])
            ->assertRedirect();
        $invitation = HouseholdInvitation::firstOrFail();

        // Wrong user cannot accept.
        $this->actingAs(User::factory()->create(['email' => 'stranger@example.com']))
            ->post(route('family.join.accept', $invitation->token))
            ->assertSessionHasErrors('token');

        // The invited partner joins successfully.
        $this->actingAs($partner)->post(route('family.join.accept', $invitation->token))->assertRedirect();
        $this->assertTrue($household->fresh()->members()->where('users.id', $partner->id)->exists());
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_shared_expense_is_visible_to_the_family_and_counts_education(): void
    {
        $owner = User::factory()->create();
        $household = $this->makeFamily($owner);

        $this->actingAs($owner)->post(route('family.expenses.store'), [
            'category' => 'Education', 'amount' => 12000, 'description' => 'School fees', 'occurred_on' => now()->toDateString(),
        ])->assertRedirect();

        $entry = Entry::firstOrFail();
        $this->assertSame($household->id, $entry->household_id);
        $this->assertSame(1200000, $entry->amount_cents);

        $this->actingAs($owner)->get(route('family.index'))
            ->assertInertia(fn ($p) => $p->component('family/Index')
                ->where('household.name', 'The Sharmas')
                ->where('summary.education', 12000)
                ->has('expenses', 1));
    }

    public function test_only_owner_manages_members_and_budgets(): void
    {
        $owner = User::factory()->create();
        $household = $this->makeFamily($owner);
        $member = User::factory()->create();
        $household->members()->attach($member->id, ['role' => 'member']);

        // Member cannot invite or set a family budget.
        $this->actingAs($member)->post(route('family.invite'), ['email' => 'x@example.com', 'role' => 'member'])->assertForbidden();
        $this->actingAs($member)->post(route('family.budgets.store'), ['category' => 'Food', 'limit' => 5000])->assertForbidden();

        // Owner can.
        $this->actingAs($owner)->post(route('family.budgets.store'), ['category' => 'Food', 'limit' => 5000])->assertRedirect();
        $this->assertSame($household->id, Budget::firstOrFail()->household_id);

        // Member can leave; owner cannot.
        $this->actingAs($member)->post(route('family.leave'))->assertRedirect();
        $this->assertFalse($household->fresh()->members()->where('users.id', $member->id)->exists());
        $this->actingAs($owner)->post(route('family.leave'))->assertForbidden();
    }

    public function test_deleting_family_detaches_shared_entries(): void
    {
        $owner = User::factory()->create();
        $household = $this->makeFamily($owner);
        $entry = $owner->entries()->create([
            'household_id' => $household->id, 'type' => 'expense', 'amount_cents' => 5000, 'occurred_on' => now(),
        ]);

        $this->actingAs($owner)->delete(route('family.destroy'))->assertRedirect();

        $this->assertModelMissing($household);
        $this->assertNull($entry->fresh()->household_id); // entry kept, now personal
    }
}
