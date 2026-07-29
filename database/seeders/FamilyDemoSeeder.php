<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Puts the demo user in a household with a second member, a pending invite and
 * this month's shared expenses/budgets, so every /api/family endpoint returns
 * populated data instead of `household: null`.
 *
 * Runs after {@see FinanceDemoSeeder}, which wipes the demo user's entries.
 */
class FamilyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'test@example.com')->first();

        if (! $owner) {
            return;
        }

        $partner = $this->partner();

        $this->purge($owner, $partner);

        $household = Household::create(['owner_id' => $owner->id, 'name' => 'Sharma Family']);
        $household->members()->attach($owner->id, ['role' => 'owner']);
        $household->members()->attach($partner->id, ['role' => 'partner']);

        // A pending invite so GET /api/family lists one, and the join-by-token
        // endpoints have a token to accept.
        $household->invitations()->create([
            'invited_by' => $owner->id,
            'email' => 'arjun@example.com',
            'role' => 'member',
            'token' => Str::random(48),
        ]);

        $this->seedSharedExpenses($household->id, $owner, $partner);
        $this->seedSharedBudgets($household, $owner->id);
    }

    /** The second household member — a real account so they can log in too. */
    private function partner(): User
    {
        $partner = User::firstOrNew(['email' => 'priya@example.com']);
        $partner->name = 'Priya Sharma';
        $partner->password = Hash::make('password');
        $partner->email_verified_at = Carbon::now();
        $partner->currency = 'INR';
        $partner->onboarded = true;
        $partner->save();

        return $partner;
    }

    /**
     * Shared expenses dated inside the current month — the family summary only
     * counts the running month.
     */
    private function seedSharedExpenses(int $householdId, User $owner, User $partner): void
    {
        $month = Carbon::now()->startOfMonth();
        $lastDay = $month->copy()->endOfMonth()->day;

        $expenses = [
            ['by' => 'owner', 'category' => 'Groceries', 'description' => 'DMart monthly stock-up', 'amount_cents' => 845000, 'day' => 2],
            ['by' => 'partner', 'category' => 'Groceries', 'description' => 'Vegetables & milk', 'amount_cents' => 172000, 'day' => 7],
            ['by' => 'partner', 'category' => 'Education', 'description' => "Aarav's school fees", 'amount_cents' => 1250000, 'day' => 5],
            ['by' => 'owner', 'category' => 'Education', 'description' => 'Maths tuition', 'amount_cents' => 300000, 'day' => 12],
            ['by' => 'owner', 'category' => 'Utilities', 'description' => 'Electricity + water', 'amount_cents' => 268000, 'day' => 10],
            ['by' => 'partner', 'category' => 'Healthcare', 'description' => 'Pharmacy — monthly medicines', 'amount_cents' => 154000, 'day' => 14],
            ['by' => 'owner', 'category' => 'Housing', 'description' => 'Society maintenance', 'amount_cents' => 350000, 'day' => 3],
            ['by' => 'partner', 'category' => 'Entertainment', 'description' => 'Family movie night', 'amount_cents' => 128000, 'day' => 16],
            ['by' => 'owner', 'category' => 'Transport', 'description' => 'School van fee', 'amount_cents' => 240000, 'day' => 6],
        ];

        foreach ($expenses as $e) {
            $user = $e['by'] === 'owner' ? $owner : $partner;

            $user->entries()->create([
                'household_id' => $householdId,
                'type' => 'expense',
                'category' => $e['category'],
                'amount_cents' => $e['amount_cents'],
                'description' => $e['description'],
                'occurred_on' => $month->copy()->day(min($e['day'], $lastDay)),
            ]);
        }

        // One shared income line so the family summary's net figure is positive.
        $partner->entries()->create([
            'household_id' => $householdId,
            'type' => 'income',
            'category' => 'Salary',
            'amount_cents' => 6200000,
            'description' => 'Salary — Wipro',
            'occurred_on' => $month->copy()->day(1),
        ]);
    }

    /** One family budget exceeded, the rest comfortably inside their limit. */
    private function seedSharedBudgets(Household $household, int $ownerId): void
    {
        $budgets = [
            ['category' => 'Groceries', 'limit_cents' => 1200000],
            ['category' => 'Education', 'limit_cents' => 1400000], // exceeded by the fees above
            ['category' => 'Utilities', 'limit_cents' => 400000],
            ['category' => 'Healthcare', 'limit_cents' => 300000],
        ];

        foreach ($budgets as $b) {
            $household->budgets()->create($b + ['user_id' => $ownerId, 'currency' => 'INR']);
        }
    }

    /**
     * Tear down the previous run: the demo users' households (invitations,
     * memberships and family budgets cascade) plus the partner's entries.
     */
    private function purge(User $owner, User $partner): void
    {
        $partner->entries()->delete();

        $householdIds = DB::table('household_user')
            ->whereIn('user_id', [$owner->id, $partner->id])
            ->pluck('household_id')->unique();

        foreach (Household::whereIn('id', $householdIds)->get() as $household) {
            $household->entries()->update(['household_id' => null]);
            $household->budgets()->delete();
            $household->delete();
        }
    }
}
