<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    private const ACTIVE = ['active', 'trialing'];

    /**
     * Subscription overview: revenue metrics, subscription list and transactions.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'all');

        $mrrCents = Subscription::whereIn('status', self::ACTIVE)
            ->get(['price_cents', 'interval'])
            ->sum(fn (Subscription $s) => $s->monthlyCents());

        $subscriptions = Subscription::query()
            ->with(['user:id,name,email', 'plan:id,name'])
            ->when(in_array($status, ['active', 'trialing', 'cancelled', 'expired'], true),
                fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Subscription $s) => [
                'id' => $s->id,
                'user' => $s->user ? ['id' => $s->user->id, 'name' => $s->user->name, 'email' => $s->user->email] : null,
                'plan' => $s->plan?->name,
                'status' => $s->status,
                'price_cents' => $s->price_cents,
                'currency' => $s->currency,
                'interval' => $s->interval,
                'started_at' => $s->started_at,
                'ends_at' => $s->ends_at,
            ]);

        return Inertia::render('admin/subscriptions/Index', [
            'stats' => [
                'active' => Subscription::whereIn('status', self::ACTIVE)->count(),
                'cancelled' => Subscription::where('status', 'cancelled')->count(),
                'mrrCents' => $mrrCents,
                'revenueCents' => (int) Transaction::where('status', 'paid')->sum('amount_cents'),
            ],
            'filters' => ['status' => $status],
            'subscriptions' => $subscriptions,
            'recentTransactions' => Transaction::with('user:id,name')
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (Transaction $t) => [
                    'id' => $t->id,
                    'user' => $t->user?->name,
                    'amount_cents' => $t->amount_cents,
                    'currency' => $t->currency,
                    'status' => $t->status,
                    'reference' => $t->reference,
                    'paid_at' => $t->paid_at,
                ]),
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'price_cents', 'currency', 'interval']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Manually assign a plan to a user (creates a subscription + paid transaction).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'plan_id' => ['required', Rule::exists('plans', 'id')],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        $endsAt = match ($plan->interval) {
            'month' => Carbon::now()->addMonth(),
            'year' => Carbon::now()->addYear(),
            default => null,
        };

        $subscription = Subscription::create([
            'user_id' => $data['user_id'],
            'plan_id' => $plan->id,
            'status' => 'active',
            'price_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
            'started_at' => Carbon::now(),
            'ends_at' => $endsAt,
        ]);

        if ($plan->price_cents > 0) {
            Transaction::create([
                'user_id' => $data['user_id'],
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'status' => 'paid',
                'reference' => 'manual-'.strtoupper(uniqid()),
                'paid_at' => Carbon::now(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subscription created.']);

        return back();
    }

    /**
     * Cancel an active subscription.
     */
    public function cancel(Subscription $subscription): RedirectResponse
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => Carbon::now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subscription cancelled.']);

        return back();
    }

    /**
     * Reactivate a cancelled/expired subscription.
     */
    public function reactivate(Subscription $subscription): RedirectResponse
    {
        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subscription reactivated.']);

        return back();
    }
}
