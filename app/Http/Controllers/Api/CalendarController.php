<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Debt;
use App\Models\Entry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    /**
     * A month grid of everything money leaves the account for: bill, EMI and
     * subscription reminders, credit-card statement dues, and any recurring
     * transaction whose schedule lands in the month. (calendar screen)
     */
    public function index(Request $request): JsonResponse
    {
        $month = Carbon::parse(((string) $request->query('month', Carbon::now()->format('Y-m'))).'-01')->startOfMonth();
        $user = $request->user();

        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        // Every dated item on the grid, keyed by day.
        $items = collect([
            ...$this->billItems($user, $gridStart, $gridEnd),
            ...$this->cardDueItems($user, $month),
            ...$this->recurringEntryItems($user, $gridStart, $gridEnd),
            ...$this->loggedEntryItems($user, $gridStart, $gridEnd),
        ])->groupBy('date');

        $cursor = $gridStart->copy();
        $today = Carbon::now()->format('Y-m-d');
        $days = [];

        for ($i = 0; $i < 42; $i++) {
            $key = $cursor->format('Y-m-d');
            $dayItems = ($items[$key] ?? collect())->values();

            $days[] = [
                'date' => $key,
                'day' => $cursor->day,
                'in_month' => $cursor->month === $month->month,
                'today' => $key === $today,
                'has_entries' => $dayItems->isNotEmpty(),
                'entries' => $dayItems->all(),
                // `items` is the older key for the same list.
                'items' => $dayItems->all(),
                'total' => Money::toRupees((int) $dayItems->sum('amount_cents')),
            ];
            $cursor->addDay();
        }

        // Everything falling inside the month itself, as a flat agenda.
        $due = collect($days)
            ->where('in_month', true)
            ->flatMap(fn (array $d) => $d['entries'])
            ->sortBy('date')
            ->values();

        return response()->json([
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'prev' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'next' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'weekdays' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'days' => $days,
            'due_items' => $due->all(),
            'summary' => [
                'due_total' => Money::toRupees((int) $due->where('direction', 'out')->sum('amount_cents')),
                'due_count' => $due->where('direction', 'out')->count(),
            ],
        ]);
    }

    /**
     * Bill / EMI / subscription reminders due inside the grid.
     *
     * @return list<array<string, mixed>>
     */
    private function billItems(User $user, Carbon $from, Carbon $to): array
    {
        return $user->bills()
            ->whereBetween('due_date', [$from, $to])->get()
            ->map(fn (Bill $b) => $this->item(
                date: $b->due_date,
                source: 'bill',
                kind: $b->kind,
                id: $b->id,
                name: $b->name,
                amountCents: $b->amount_cents,
                status: $b->status,
                route: '/reminders',
                repeat: $b->repeat,
            ))->values()->all();
    }

    /**
     * Credit-card statement dues for this month, derived from each card's
     * due day — these are not bills, so they would otherwise be invisible.
     *
     * @return list<array<string, mixed>>
     */
    private function cardDueItems(User $user, Carbon $month): array
    {
        return $user->debts()->where('status', 'active')->where('kind', 'credit_card')->get()
            ->filter(fn (Debt $d) => $d->due_day !== null)
            ->map(function (Debt $d) use ($month) {
                $day = min($d->due_day, $month->daysInMonth);
                $date = $month->copy()->startOfMonth()->addDays($day - 1);

                return $this->item(
                    date: $date,
                    source: 'card_due',
                    kind: 'credit_card',
                    id: $d->id,
                    name: $d->name.' statement due',
                    amountCents: $d->currentDueCents(),
                    status: $date->isPast() ? 'overdue' : 'upcoming',
                    route: '/debts',
                    repeat: 'monthly',
                );
            })->values()->all();
    }

    /**
     * Future occurrences of recurring transactions that land inside the grid.
     *
     * @return list<array<string, mixed>>
     */
    private function recurringEntryItems(User $user, Carbon $from, Carbon $to): array
    {
        $items = [];

        $recurring = $user->entries()
            ->whereNotIn('repeat', ['none', 'one_time'])
            ->where('occurred_on', '<=', $to)
            ->get();

        foreach ($recurring as $entry) {
            /** @var Entry $entry */
            $cursor = $entry->occurred_on->copy()->subDay();
            $guard = 0;

            while (($next = $entry->nextOccurrenceAfter($cursor)) !== null && $next->lte($to) && $guard++ < 60) {
                $cursor = $next;

                if ($next->lt($from)) {
                    continue;
                }

                $items[] = $this->item(
                    date: $next,
                    source: 'recurring_'.$entry->type,
                    kind: $entry->type,
                    id: $entry->id,
                    name: $entry->description ?? $entry->payee ?? $entry->category ?? ucfirst($entry->type),
                    amountCents: $entry->amount_cents,
                    status: 'scheduled',
                    route: '/transactions',
                    repeat: $entry->repeat,
                    direction: $entry->type === 'income' ? 'in' : 'out',
                );
            }
        }

        return $items;
    }

    /**
     * Transactions already logged in the grid, so a day the user actually
     * spent on is never shown as empty.
     *
     * @return list<array<string, mixed>>
     */
    private function loggedEntryItems(User $user, Carbon $from, Carbon $to): array
    {
        return $user->entries()
            ->whereBetween('occurred_on', [$from, $to])->get()
            ->map(fn (Entry $e) => $this->item(
                date: $e->occurred_on,
                source: $e->type,
                kind: $e->type,
                id: $e->id,
                name: $e->description ?? $e->payee ?? $e->category ?? ucfirst($e->type),
                amountCents: $e->amount_cents,
                status: 'logged',
                route: '/transactions',
                repeat: $e->repeat ?? 'none',
                direction: $e->type === 'income' ? 'in' : 'out',
            ))->values()->all();
    }

    /**
     * One shape for every calendar item, whatever it was derived from.
     *
     * `amount_cents` stays on the payload so the day/month totals can be
     * summed without re-reading the source rows.
     *
     * @return array<string, mixed>
     */
    private function item(
        Carbon $date,
        string $source,
        string $kind,
        int $id,
        string $name,
        int $amountCents,
        string $status,
        string $route,
        string $repeat,
        string $direction = 'out',
    ): array {
        return [
            'id' => $source.':'.$id.':'.$date->format('Y-m-d'),
            'source_id' => $id,
            'source' => $source,
            'kind' => $kind,
            'name' => $name,
            'title' => $name,
            'amount' => Money::toRupees($amountCents),
            'amount_cents' => $amountCents,
            'date' => $date->format('Y-m-d'),
            'label' => $date->format('d M'),
            'status' => $status,
            'repeat' => $repeat,
            'direction' => $direction,
            'route' => $route,
        ];
    }
}
