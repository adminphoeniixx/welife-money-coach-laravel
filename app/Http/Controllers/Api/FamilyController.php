<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Entry;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FamilyController extends Controller
{
    /** Suggested categories for shared family expenses (free text allowed). */
    private const CATEGORIES = ['Groceries', 'Housing', 'Utilities', 'Education', 'Healthcare', 'Transport', 'Entertainment', 'Other'];

    /**
     * Family hub: null household when the user hasn't joined one. (family screen)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $household = $user->currentHousehold();

        if ($household === null) {
            return response()->json(['household' => null, 'categories' => self::CATEGORIES]);
        }

        $now = Carbon::now();
        $role = $this->roleIn($household, $user->id);

        $entries = $household->entries()
            ->with('user:id,name')
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->orderByDesc('occurred_on')->get();

        $incomeCents = (int) $entries->where('type', 'income')->sum('amount_cents');
        $expenseCents = (int) $entries->where('type', 'expense')->sum('amount_cents');
        $educationCents = (int) $entries->where('type', 'expense')
            ->filter(fn (Entry $e) => strcasecmp((string) $e->category, 'Education') === 0)
            ->sum('amount_cents');

        $spentByCategory = $entries->where('type', 'expense')->groupBy('category')
            ->map(fn ($rows) => (int) $rows->sum('amount_cents'));

        return response()->json([
            'categories' => self::CATEGORIES,
            'can_manage' => $role === 'owner',
            'my_role' => $role,
            'household' => [
                'id' => $household->id,
                'name' => $household->name,
                'members' => DB::table('household_user')
                    ->join('users', 'users.id', '=', 'household_user.user_id')
                    ->where('household_user.household_id', $household->id)
                    ->orderByRaw("household_user.role = 'owner' desc")
                    ->get(['users.id', 'users.name', 'users.email', 'household_user.role'])
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'email' => $m->email,
                        'role' => $m->role,
                        'is_you' => $m->id === $user->id,
                    ])->values(),
                'invitations' => $household->invitations()->whereNull('accepted_at')->get()->map(fn (HouseholdInvitation $i) => [
                    'id' => $i->id,
                    'email' => $i->email,
                    'role' => $i->role,
                    'token' => $i->token,
                    'link' => route('family.join', $i->token),
                ])->values(),
            ],
            'summary' => [
                'income' => Money::toRupees($incomeCents),
                'expense' => Money::toRupees($expenseCents),
                'net' => Money::toRupees($incomeCents - $expenseCents),
                'education' => Money::toRupees($educationCents),
            ],
            'expenses' => $entries->where('type', 'expense')->take(20)->map(fn (Entry $e) => [
                'id' => $e->id,
                'category' => $e->category,
                'description' => $e->description,
                'amount' => Money::toRupees($e->amount_cents),
                'by' => $e->user?->name,
                'mine' => $e->user_id === $user->id,
                'date' => $e->occurred_on->format('d M'),
            ])->values(),
            'budgets' => $household->budgets()->orderBy('category')->get()->map(function (Budget $b) use ($spentByCategory) {
                $spent = (int) ($spentByCategory[$b->category] ?? 0);

                return [
                    'id' => $b->id,
                    'category' => $b->category,
                    'limit' => Money::toRupees($b->limit_cents),
                    'spent' => Money::toRupees($spent),
                    'percent' => $b->limit_cents > 0 ? round($spent / $b->limit_cents * 100) : 0,
                    'exceeded' => $spent > $b->limit_cents,
                ];
            })->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasHousehold(), 409, 'You already belong to a family.');

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $household = Household::create(['owner_id' => $user->id, 'name' => $validated['name']]);
        $household->members()->attach($user->id, ['role' => 'owner']);

        return response()->json(['message' => 'Family created!', 'household_id' => $household->id], 201);
    }

    /**
     * Invite someone by email; returns a shareable join link (no email sent).
     */
    public function invite(Request $request): JsonResponse
    {
        $household = $this->ownedHousehold($request);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:partner,member'],
        ]);

        if ($household->members()->where('email', $validated['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'That person is already in your family.']);
        }

        $invitation = $household->invitations()->updateOrCreate(
            ['email' => $validated['email'], 'accepted_at' => null],
            ['invited_by' => $request->user()->id, 'role' => $validated['role'], 'token' => Str::random(48)],
        );

        return response()->json([
            'message' => 'Invite ready — share the link.',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'token' => $invitation->token,
                'link' => route('family.join', $invitation->token),
            ],
        ], 201);
    }

    public function cancelInvite(Request $request, HouseholdInvitation $invitation): JsonResponse
    {
        $household = $this->ownedHousehold($request);
        abort_unless($invitation->household_id === $household->id, 403);

        $invitation->delete();

        return response()->json(['message' => 'Invitation cancelled.']);
    }

    /**
     * Details for a valid, unaccepted invitation token (accept screen).
     */
    public function showJoin(Request $request, string $token): JsonResponse
    {
        $invitation = HouseholdInvitation::whereNull('accepted_at')->where('token', $token)->firstOrFail();

        return response()->json([
            'token' => $token,
            'household' => $invitation->household->name,
            'email' => $invitation->email,
            'email_matches' => strcasecmp($invitation->email, (string) $request->user()->email) === 0,
            'already_in_family' => $request->user()->hasHousehold(),
        ]);
    }

    public function join(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        $invitation = HouseholdInvitation::whereNull('accepted_at')->where('token', $token)->firstOrFail();

        abort_if($user->hasHousehold(), 409, 'You already belong to a family.');

        if (strcasecmp($invitation->email, (string) $user->email) !== 0) {
            throw ValidationException::withMessages(['token' => 'This invitation was sent to a different email address.']);
        }

        $invitation->household->members()->syncWithoutDetaching([$user->id => ['role' => $invitation->role]]);
        $invitation->update(['accepted_at' => now()]);

        return response()->json([
            'message' => 'You joined '.$invitation->household->name.'!',
            'household_id' => $invitation->household_id,
        ]);
    }

    public function removeMember(Request $request, User $member): JsonResponse
    {
        $household = $this->ownedHousehold($request);
        abort_if($member->id === $household->owner_id, 403, 'The owner cannot be removed.');

        $household->members()->detach($member->id);

        return response()->json(['message' => 'Member removed.']);
    }

    /**
     * Leave the family (owners must delete it instead).
     */
    public function leave(Request $request): JsonResponse
    {
        $user = $request->user();
        $household = $user->currentHousehold();
        abort_if($household === null, 404);
        abort_if($household->owner_id === $user->id, 403, 'Owners must delete the family instead of leaving.');

        $household->members()->detach($user->id);

        return response()->json(['message' => 'You left the family.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $household = $this->ownedHousehold($request);

        DB::transaction(function () use ($household) {
            $household->entries()->update(['household_id' => null]);
            $household->budgets()->delete();
            $household->delete();
        });

        return response()->json(['message' => 'Family deleted.']);
    }

    /**
     * Add a shared expense visible to the whole family.
     */
    public function storeExpense(Request $request): JsonResponse
    {
        $household = $this->memberHousehold($request);

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'description' => ['nullable', 'string', 'max:255'],
            'occurred_on' => ['required', 'date'],
        ]);

        $entry = $request->user()->entries()->create([
            'household_id' => $household->id,
            'type' => 'expense',
            'category' => $validated['category'],
            'amount_cents' => Money::toCents($validated['amount']),
            'description' => $validated['description'] ?? null,
            'occurred_on' => $validated['occurred_on'],
        ]);

        return response()->json(['message' => 'Shared expense added.', 'id' => $entry->id], 201);
    }

    public function destroyExpense(Request $request, Entry $entry): JsonResponse
    {
        $household = $this->memberHousehold($request);
        abort_unless($entry->household_id === $household->id, 403);
        abort_unless($entry->user_id === $request->user()->id || $household->owner_id === $request->user()->id, 403);

        $entry->delete();

        return response()->json(['message' => 'Shared expense removed.']);
    }

    public function storeBudget(Request $request): JsonResponse
    {
        $household = $this->ownedHousehold($request);

        $validated = $request->validate([
            'category' => [
                'required', 'string', 'max:60',
                Rule::unique('budgets', 'category')->where(fn ($q) => $q->where('household_id', $household->id)),
            ],
            'limit' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $budget = $household->budgets()->create([
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'limit_cents' => Money::toCents($validated['limit']),
        ]);

        return response()->json(['message' => 'Family budget set.', 'id' => $budget->id], 201);
    }

    public function destroyBudget(Request $request, Budget $budget): JsonResponse
    {
        $household = $this->ownedHousehold($request);
        abort_unless($budget->household_id === $household->id, 403);

        $budget->delete();

        return response()->json(['message' => 'Family budget removed.']);
    }

    private function roleIn(Household $household, int $userId): ?string
    {
        $role = DB::table('household_user')
            ->where('household_id', $household->id)
            ->where('user_id', $userId)
            ->value('role');

        return $role !== null ? (string) $role : null;
    }

    private function memberHousehold(Request $request): Household
    {
        $household = $request->user()->currentHousehold();
        abort_if($household === null, 403, 'You are not in a family.');

        return $household;
    }

    private function ownedHousehold(Request $request): Household
    {
        $household = $this->memberHousehold($request);
        abort_unless($household->owner_id === $request->user()->id, 403, 'Only the family owner can do that.');

        return $household;
    }
}
