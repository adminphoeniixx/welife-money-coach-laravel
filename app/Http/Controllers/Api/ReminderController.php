<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ReminderController extends Controller
{
    /**
     * Bills, EMIs and subscriptions with due/overdue status and monthly cost.
     * (reminders / subs screens)
     */
    public function index(Request $request): JsonResponse
    {
        $bills = $request->user()->bills()->orderBy('due_date')->get();
        $today = Carbon::now()->startOfDay();

        $subscriptions = $bills->where('kind', 'subscription');

        return response()->json([
            'kinds' => Options::reminderKinds(),
            'repeat_options' => Options::repeatOptions(),
            'remind_days_before_options' => Options::remindDaysBefore(),
            'overdue' => $bills->where('status', 'overdue')->map($this->present($today))->values(),
            'upcoming' => $bills->where('status', 'upcoming')->where('kind', '!=', 'subscription')->map($this->present($today))->values(),
            'subscriptions' => $subscriptions->map($this->present($today))->values(),
            'subscription_monthly' => Money::toRupees((int) $subscriptions->sum('amount_cents')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $bill = $request->user()->bills()->create($this->validated($request));

        return response()->json([
            'message' => 'Reminder added.',
            'bill' => ($this->present(Carbon::now()->startOfDay()))($bill),
        ], 201);
    }

    public function update(Request $request, Bill $bill): JsonResponse
    {
        abort_unless($bill->user_id === $request->user()->id, 403);

        $bill->update($this->validated($request));

        return response()->json([
            'message' => 'Reminder updated.',
            'bill' => ($this->present(Carbon::now()->startOfDay()))($bill->fresh()),
        ]);
    }

    public function destroy(Request $request, Bill $bill): JsonResponse
    {
        abort_unless($bill->user_id === $request->user()->id, 403);

        $bill->delete();

        return response()->json(['message' => 'Reminder deleted.']);
    }

    /**
     * Mark a bill paid. Recurring bills roll forward to their next due date.
     */
    public function markPaid(Request $request, Bill $bill): JsonResponse
    {
        abort_unless($bill->user_id === $request->user()->id, 403);

        // `none` and `one_time` both mean "does not repeat".
        if (! in_array($bill->repeat, ['none', 'one_time'], true)) {
            $next = match ($bill->repeat) {
                'weekly' => $bill->due_date->copy()->addWeek(),
                'yearly' => $bill->due_date->copy()->addYear(),
                default => $bill->due_date->copy()->addMonthNoOverflow(),
            };
            $bill->update(['status' => 'upcoming', 'paid_on' => Carbon::now(), 'due_date' => $next]);
        } else {
            $bill->update(['status' => 'paid', 'paid_on' => Carbon::now()]);
        }

        return response()->json([
            'message' => 'Marked as paid. Great job! ✅',
            'bill' => ($this->present(Carbon::now()->startOfDay()))($bill->fresh()),
        ]);
    }

    /** "Due today" / "Due in 3 days" / "Overdue by 2 days". */
    private function relativeDay(int $days): string
    {
        return match (true) {
            $days < 0 => abs($days).' day'.(abs($days) === 1 ? '' : 's').' overdue',
            $days === 0 => 'Due today',
            $days === 1 => 'Due tomorrow',
            default => "Due in $days days",
        };
    }

    private function present(Carbon $today): callable
    {
        return function (Bill $b) use ($today) {
            $days = (int) round($today->diffInDays($b->due_date, false));

            return [
                'id' => $b->id,
                'name' => $b->name,
                'kind' => $b->kind,
                'category' => $b->category,
                'amount' => Money::toRupees($b->amount_cents),
                // Machine-readable date; the label is for display only.
                'due_date' => $b->due_date->format('Y-m-d'),
                'due_on' => $b->due_date->format('Y-m-d'),
                'label' => $b->due_date->format('D, d M'),
                'when' => $this->relativeDay($days),
                'repeat' => $b->repeat,
                'remind_days_before' => $b->remind_days_before,
                'days' => $days,
                'overdue' => $b->status === 'overdue' || $days < 0,
                'status' => $b->status,
                'paid_on' => $b->paid_on?->format('Y-m-d'),
            ];
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(Options::reminderKinds())],
            'category' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'due_date' => ['required', 'date'],
            'repeat' => ['required', Rule::in(Options::repeatOptions())],
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
