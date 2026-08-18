<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\NotificationRead;
use App\Services\InsightService;
use App\Support\Money;
use App\Support\Options;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReminderController extends Controller
{
    public function __construct(private readonly InsightService $insights) {}

    /**
     * Bills, EMIs and subscriptions with due/overdue status and monthly cost.
     * (reminders / subs screens)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bills = $user->bills()->orderBy('due_date')->get();
        $today = Carbon::now()->startOfDay();

        $subscriptions = $bills->where('kind', 'subscription');
        $readKeys = $user->notificationReads()->pluck('key')->flip();

        return response()->json([
            'kinds' => Options::reminderKinds(),
            'repeat_options' => Options::repeatOptions(),
            'remind_days_before_options' => Options::remindDaysBefore(),
            'overdue' => $bills->where('status', 'overdue')->map($this->present($today, $readKeys))->values(),
            'upcoming' => $bills->where('status', 'upcoming')->where('kind', '!=', 'subscription')->map($this->present($today, $readKeys))->values(),
            'done' => $bills->where('status', 'paid')->sortByDesc('paid_on')->map($this->present($today, $readKeys))->values(),
            'subscriptions' => $subscriptions->map($this->present($today, $readKeys))->values(),
            'subscription_monthly' => Money::toRupees((int) $subscriptions->sum('amount_cents')),
            'unread' => $this->insights->remindersUnreadCount($user),
            'unread_count' => $this->insights->remindersUnreadCount($user),
        ]);
    }

    /**
     * Mark a single reminder as seen.
     */
    public function markRead(Request $request, Bill $bill): JsonResponse
    {
        abort_unless($bill->user_id === $request->user()->id, 403);

        $this->recordRead($request, [InsightService::reminderKey($bill)]);

        return response()->json([
            'message' => 'Reminder marked as read.',
            'bill' => ($this->present(Carbon::now()->startOfDay(), $request->user()->notificationReads()->pluck('key')->flip()))($bill),
            'unread' => $this->insights->remindersUnreadCount($request->user()),
            'unread_count' => $this->insights->remindersUnreadCount($request->user()),
        ]);
    }

    /**
     * Mark every reminder as seen — clears the home red dot.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $this->recordRead(
            $request,
            $request->user()->bills()->get()->map(InsightService::reminderKey(...))->all(),
        );

        return response()->json([
            'message' => 'All reminders marked as read.',
            'unread' => 0,
            'unread_count' => 0,
        ]);
    }

    /**
     * Push a reminder's due date back — either by `days` or to an explicit
     * `date`. The reminder becomes unread again on its new date.
     */
    public function snooze(Request $request, Bill $bill): JsonResponse
    {
        abort_unless($bill->user_id === $request->user()->id, 403);

        $v = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'date' => ['nullable', 'date', 'after:today'],
        ]);

        if (! isset($v['days']) && ! isset($v['date'])) {
            throw ValidationException::withMessages([
                'days' => 'Provide either a number of days or a new date to snooze to.',
            ]);
        }

        $due = isset($v['date'])
            ? Carbon::parse($v['date'])->startOfDay()
            : Carbon::now()->startOfDay()->addDays((int) $v['days']);

        $bill->update([
            'due_date' => $due,
            'status' => $due->lt(Carbon::now()->startOfDay()) ? 'overdue' : 'upcoming',
        ]);

        return response()->json([
            'message' => 'Snoozed until '.$due->format('d M Y').'.',
            'bill' => ($this->present(Carbon::now()->startOfDay(), $request->user()->notificationReads()->pluck('key')->flip()))($bill->fresh()),
            'unread' => $this->insights->remindersUnreadCount($request->user()),
            'unread_count' => $this->insights->remindersUnreadCount($request->user()),
        ]);
    }

    /**
     * @param  list<string>  $keys
     */
    private function recordRead(Request $request, array $keys): void
    {
        foreach (array_unique($keys) as $key) {
            NotificationRead::updateOrCreate(
                ['user_id' => $request->user()->id, 'key' => $key],
                ['read_at' => Carbon::now()],
            );
        }
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

        $deletedId = $bill->id;
        $bill->delete();

        return response()->json([
            'message' => 'Reminder deleted.',
            'deleted_id' => $deletedId,
            'subscription_monthly' => Money::toRupees(
                (int) $request->user()->bills()->where('kind', 'subscription')->sum('amount_cents')
            ),
        ]);
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

    /**
     * @param  Collection<string, int>|null  $readKeys
     */
    private function present(CarbonInterface $today, mixed $readKeys = null): callable
    {
        return function (Bill $b) use ($today, $readKeys) {
            $days = (int) round($today->diffInDays($b->due_date, false));

            return [
                'id' => $b->id,
                'title' => $b->name,
                'read' => $readKeys !== null && $readKeys->has(InsightService::reminderKey($b)),
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
