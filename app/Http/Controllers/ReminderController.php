<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    /**
     * Bills, EMIs and subscriptions with due/overdue status and monthly cost.
     */
    public function index(Request $request): Response
    {
        $bills = $request->user()->bills()->orderBy('due_date')->get();
        $today = Carbon::now()->startOfDay();

        $subscriptions = $bills->where('kind', 'subscription');

        return Inertia::render('reminders/Index', [
            'overdue' => $bills->where('status', 'overdue')->map($this->present($today))->values(),
            'upcoming' => $bills->where('status', 'upcoming')->where('kind', '!=', 'subscription')->map($this->present($today))->values(),
            'subscriptions' => $subscriptions->map($this->present($today))->values(),
            'subscription_monthly' => Money::toRupees((int) $subscriptions->sum('amount_cents')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->bills()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reminder added.']);

        return back();
    }

    public function update(Request $request, Bill $bill): RedirectResponse
    {
        abort_unless($bill->user_id === $request->user()->id, 403);

        $bill->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reminder updated.']);

        return back();
    }

    public function destroy(Request $request, Bill $bill): RedirectResponse
    {
        abort_unless($bill->user_id === $request->user()->id, 403);

        $bill->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reminder deleted.']);

        return back();
    }

    /**
     * Mark a bill paid. Recurring bills roll forward to their next due date.
     */
    public function markPaid(Request $request, Bill $bill): RedirectResponse
    {
        abort_unless($bill->user_id === $request->user()->id, 403);

        if ($bill->repeat !== 'none') {
            $next = match ($bill->repeat) {
                'weekly' => $bill->due_date->copy()->addWeek(),
                'yearly' => $bill->due_date->copy()->addYear(),
                default => $bill->due_date->copy()->addMonthNoOverflow(),
            };
            $bill->update(['status' => 'upcoming', 'paid_on' => Carbon::now(), 'due_date' => $next]);
        } else {
            $bill->update(['status' => 'paid', 'paid_on' => Carbon::now()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marked as paid. Great job! ✅']);

        return back();
    }

    private function present(Carbon $today): callable
    {
        return fn (Bill $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'kind' => $b->kind,
            'category' => $b->category,
            'amount' => Money::toRupees($b->amount_cents),
            'due_date' => $b->due_date->format('D, d M'),
            'due_on' => $b->due_date->format('Y-m-d'),
            'repeat' => $b->repeat,
            'remind_days_before' => $b->remind_days_before,
            'days' => (int) round($today->diffInDays($b->due_date, false)),
            'status' => $b->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:bill,subscription,emi'],
            'category' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'due_date' => ['required', 'date'],
            'repeat' => ['required', 'in:none,weekly,monthly,yearly'],
            'remind_days_before' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        $due = Carbon::parse($v['due_date'])->startOfDay();

        return [
            'name' => $v['name'],
            'kind' => $v['kind'],
            'category' => $v['category'] ?? null,
            'amount_cents' => Money::toCents($v['amount']),
            'due_date' => $due,
            'repeat' => $v['repeat'],
            'remind_days_before' => $v['remind_days_before'],
            'status' => $due->lt(Carbon::now()->startOfDay()) ? 'overdue' : 'upcoming',
        ];
    }
}
