<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Entry;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FamilyController extends Controller
{
    /** Suggested categories for shared family expenses (free text allowed). */
    private const CATEGORIES = ['Groceries', 'Housing', 'Utilities', 'Education', 'Healthcare', 'Transport', 'Entertainment', 'Other'];

    /**
     * Family hub: create screen, or the shared dashboard when in a household.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $household = $user->currentHousehold();

        if ($household === null) {
            return Inertia::render('family/Index', ['household' => null, 'categories' => self::CATEGORIES]);
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

        return Inertia::render('family/Index', [
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

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->hasHousehold(), 409, 'You already belong to a family.');

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $household = Household::create(['owner_id' => $user->id, 'name' => $validated['name']]);
        $household->members()->attach($user->id, ['role' => 'owner']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Family created!']);

        return redirect()->route('family.index');
    }

    /**
     * Invite someone by email; returns a shareable join link (no email sent).
     */
    public function invite(Request $request): RedirectResponse
    {
        $household = $this->ownedHousehold($request);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:partner,member'],
        ]);

        if ($household->members()->where('email', $validated['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'That person is already in your family.']);
        }

        $household->invitations()->updateOrCreate(
            ['email' => $validated['email'], 'accepted_at' => null],
            ['invited_by' => $request->user()->id, 'role' => $validated['role'], 'token' => Str::random(48)],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invite ready — share the link.']);

        return back();
    }

    public function cancelInvite(Request $request, HouseholdInvitation $invitation): RedirectResponse
    {
        $household = $this->ownedHousehold($request);
        abort_unless($invitation->household_id === $household->id, 403);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation cancelled.']);

        return back();
    }

    /**
     * Show the accept screen for a valid, unaccepted invitation token.
     */
    public function showJoin(Request $request, string $token): Response
    {
        $invitation = HouseholdInvitation::whereNull('accepted_at')->where('token', $token)->firstOrFail();

        return Inertia::render('family/Accept', [
            'token' => $token,
            'household' => $invitation->household->name,
            'email' => $invitation->email,
            'email_matches' => strcasecmp($invitation->email, (string) $request->user()->email) === 0,
            'already_in_family' => $request->user()->hasHousehold(),
        ]);
    }

    public function join(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();
        $invitation = HouseholdInvitation::whereNull('accepted_at')->where('token', $token)->firstOrFail();

        abort_if($user->hasHousehold(), 409, 'You already belong to a family.');

        if (strcasecmp($invitation->email, (string) $user->email) !== 0) {
            throw ValidationException::withMessages(['token' => 'This invitation was sent to a different email address.']);
        }

        $invitation->household->members()->syncWithoutDetaching([$user->id => ['role' => $invitation->role]]);
        $invitation->update(['accepted_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'You joined '.$invitation->household->name.'!']);

        return redirect()->route('family.index');
    }

    public function removeMember(Request $request, User $member): RedirectResponse
    {
        $household = $this->ownedHousehold($request);
        abort_if($member->id === $household->owner_id, 403, 'The owner cannot be removed.');

        $household->members()->detach($member->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Member removed.']);

        return back();
    }

    /**
     * Leave the family (owners must delete it instead).
     */
    public function leave(Request $request): RedirectResponse
    {
        $user = $request->user();
        $household = $user->currentHousehold();
        abort_if($household === null, 404);
        abort_if($household->owner_id === $user->id, 403, 'Owners must delete the family instead of leaving.');

        $household->members()->detach($user->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'You left the family.']);

        return redirect()->route('family.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $household = $this->ownedHousehold($request);

        // Detach shared items back to personal before deleting the household.
        DB::transaction(function () use ($household) {
            $household->entries()->update(['household_id' => null]);
            $household->budgets()->delete();
            $household->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Family deleted.']);

        return redirect()->route('family.index');
    }

    /**
     * Add a shared expense visible to the whole family.
     */
    public function storeExpense(Request $request): RedirectResponse
    {
        $household = $this->memberHousehold($request);

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'description' => ['nullable', 'string', 'max:255'],
            'occurred_on' => ['required', 'date'],
        ]);

        $request->user()->entries()->create([
            'household_id' => $household->id,
            'type' => 'expense',
            'category' => $validated['category'],
            'amount_cents' => Money::toCents($validated['amount']),
            'description' => $validated['description'] ?? null,
            'occurred_on' => $validated['occurred_on'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shared expense added.']);

        return back();
    }

    public function destroyExpense(Request $request, Entry $entry): RedirectResponse
    {
        $household = $this->memberHousehold($request);
        abort_unless($entry->household_id === $household->id, 403);
        // Only the person who logged it, or the owner, may delete it.
        abort_unless($entry->user_id === $request->user()->id || $household->owner_id === $request->user()->id, 403);

        $entry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shared expense removed.']);

        return back();
    }

    public function storeBudget(Request $request): RedirectResponse
    {
        $household = $this->ownedHousehold($request);

        $validated = $request->validate([
            'category' => [
                'required', 'string', 'max:60',
                Rule::unique('budgets', 'category')->where(fn ($q) => $q->where('household_id', $household->id)),
            ],
            'limit' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $household->budgets()->create([
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'limit_cents' => Money::toCents($validated['limit']),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Family budget set.']);

        return back();
    }

    public function destroyBudget(Request $request, Budget $budget): RedirectResponse
    {
        $household = $this->ownedHousehold($request);
        abort_unless($budget->household_id === $household->id, 403);

        $budget->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Family budget removed.']);

        return back();
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
