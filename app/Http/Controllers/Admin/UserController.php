<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Paginated, searchable list of platform users.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $filter = $request->query('filter', 'all');

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filter === 'admins', fn ($q) => $q->where('is_admin', true))
            ->when($filter === 'verified', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($filter === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($filter === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'suspended_at' => $user->suspended_at,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'filter' => $filter,
            ],
        ]);
    }

    /**
     * Show a single user with support actions.
     */
    public function show(User $user): Response
    {
        return Inertia::render('admin/users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'suspended_at' => $user->suspended_at,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Update a user's basic profile details.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $emailChanged = $validated['email'] !== $user->email;

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return back();
    }

    /**
     * Toggle a user's administrator role.
     */
    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'You cannot change your own admin role.']);

            return back();
        }

        $user->is_admin = ! $user->is_admin;
        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_admin
                ? "{$user->name} is now an administrator."
                : "{$user->name} is no longer an administrator.",
        ]);

        return back();
    }

    /**
     * Manually mark a user's email as verified or unverified.
     */
    public function toggleVerified(User $user): RedirectResponse
    {
        $user->email_verified_at = $user->email_verified_at ? null : now();
        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->email_verified_at ? 'Email marked as verified.' : 'Email marked as unverified.',
        ]);

        return back();
    }

    /**
     * Email the user a password reset link.
     */
    public function sendPasswordReset(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', $status === Password::RESET_LINK_SENT
            ? ['type' => 'success', 'message' => 'Password reset link sent.']
            : ['type' => 'error', 'message' => __($status)]);

        return back();
    }

    /**
     * Suspend or reinstate a user account.
     */
    public function toggleSuspend(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'You cannot suspend your own account.']);

            return back();
        }

        $user->suspended_at = $user->suspended_at ? null : now();
        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->suspended_at ? "{$user->name} has been suspended." : "{$user->name} has been reinstated.",
        ]);

        return back();
    }

    /**
     * Log in as the given user for support purposes.
     */
    public function impersonate(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'You cannot impersonate yourself.']);

            return back();
        }

        $request->session()->put('impersonator_id', $request->user()->id);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    /**
     * Return to the original admin account after impersonating.
     */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        if (! $impersonatorId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($impersonatorId);

        if ($admin) {
            Auth::login($admin);

            return redirect()->route('admin.users.index');
        }

        Auth::logout();

        return redirect()->route('login');
    }

    /**
     * Remove a user account from the platform.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'You cannot delete your own account here.']);

            return back();
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User deleted.']);

        return to_route('admin.users.index');
    }
}
