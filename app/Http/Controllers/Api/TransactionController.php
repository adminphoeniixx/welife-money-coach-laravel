<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    /**
     * Income and expense ledger grouped by day. Defaults to the current month;
     * pass `month=YYYY-MM` for another one, or `from`/`to` for a custom range.
     * (transactions screen)
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type', 'all'); // all | income | expense
        [$start, $end, $month] = $this->range($request);

        $entries = $request->user()->entries()
            ->with('attachments')
            ->whereBetween('occurred_on', [$start, $end])
            ->when(in_array($type, ['income', 'expense'], true), fn ($q) => $q->where('type', $type))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $incomeCents = (int) $entries->where('type', 'income')->sum('amount_cents');
        $expenseCents = (int) $entries->where('type', 'expense')->sum('amount_cents');

        return response()->json([
            'filter' => $type,
            'month' => $month?->format('Y-m'),
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'prev' => $month?->copy()->subMonthNoOverflow()->format('Y-m'),
            'next' => $month?->copy()->addMonthNoOverflow()->format('Y-m'),
            // Master lists only — the app renders these as-is.
            'categories' => [
                'income' => Options::incomeCategories(),
                'expense' => Options::expenseCategories(),
            ],
            // Extra categories this user has created (e.g. a custom budget),
            // kept apart so the master lists stay clean.
            'custom_categories' => $this->customCategories($request),
            'payment_methods' => Options::paymentMethods(),
            'repeat_options' => Options::entryRepeatOptions(),
            'totals' => [
                'income' => Money::toRupees($incomeCents),
                'expense' => Money::toRupees($expenseCents),
                'net' => Money::toRupees($incomeCents - $expenseCents),
            ],
            'count' => $entries->count(),
            // Flat list alongside the day groups, so a client that does its own
            // grouping does not have to flatten `groups` first.
            'transactions' => $entries->map($this->present(...))->values()->all(),
            'groups' => $entries
                ->groupBy(fn (Entry $e) => $e->occurred_on->format('Y-m-d'))
                ->map(function ($rows, $day) {
                    $date = Carbon::parse((string) $day);
                    $net = $rows->sum(fn (Entry $e) => $e->type === 'income' ? $e->amount_cents : -$e->amount_cents);

                    return [
                        'date' => $date->format('Y-m-d'),
                        'label' => $date->format('D, d M'),
                        'total' => Money::toRupees((int) $net),
                        'items' => $rows->map($this->present(...))->values()->all(),
                    ];
                })->values()->all(),
        ]);
    }

    /**
     * A single transaction with every field the detail screen renders,
     * including its repeat schedule and proof attachments.
     */
    public function show(Request $request, Entry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        return response()->json([
            'transaction' => $this->present($entry->load('attachments')),
            'repeat_options' => Options::entryRepeatOptions(),
            'payment_methods' => Options::paymentMethods(),
        ]);
    }

    /**
     * The window the list covers. `month=YYYY-MM` (default: this month) or an
     * explicit `from`/`to` pair, which returns a null month.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon|null}
     */
    private function range(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if (is_string($from) && is_string($to) && $from !== '' && $to !== '') {
            return [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
                null,
            ];
        }

        $month = Carbon::parse(((string) $request->query('month', Carbon::now()->format('Y-m'))).'-01')->startOfMonth();

        return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $month];
    }

    /**
     * Categories this user has in play that are not in the master lists —
     * from a custom budget or an older entry — so nothing they typed before
     * disappears from the picker.
     *
     * @return list<string>
     */
    private function customCategories(Request $request): array
    {
        $known = array_merge(Options::incomeCategories(), Options::expenseCategories());

        $custom = $request->user()->budgets()->whereNull('household_id')->pluck('category')
            ->merge($request->user()->entries()->distinct()->pluck('category'))
            ->filter(fn ($c) => is_string($c) && $c !== '' && ! in_array($c, $known, true))
            ->unique()->sort()->all();

        return array_values(array_map(strval(...), $custom));
    }

    public function store(Request $request): JsonResponse
    {
        $entry = $request->user()->entries()->create($this->validated($request));
        $this->attachUploads($request, $entry);

        return response()->json([
            'message' => ucfirst($entry->type).' added.',
            'entry' => $this->present($entry->load('attachments')),
            'transaction' => $this->present($entry),
            'totals' => $this->monthTotals($request),
        ], 201);
    }

    public function update(Request $request, Entry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $entry->update($this->validated($request));
        $this->attachUploads($request, $entry);

        $fresh = $entry->fresh(['attachments']);

        return response()->json([
            'message' => 'Transaction updated.',
            'entry' => $this->present($fresh),
            'transaction' => $this->present($fresh),
            'totals' => $this->monthTotals($request),
        ]);
    }

    public function destroy(Request $request, Entry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $deletedId = $entry->id;

        // Drop the encrypted blobs too — the rows go with the cascade, the
        // files on disk would not.
        foreach ($entry->attachments as $attachment) {
            EntryAttachmentController::purge($attachment);
        }

        $entry->delete();

        return response()->json([
            'message' => 'Transaction deleted.',
            'deleted_id' => $deletedId,
            'totals' => $this->monthTotals($request),
        ]);
    }

    /**
     * Store any proof files sent with a create/update. Accepts `attachments[]`
     * (preferred) or a single `attachment`, so either client shape works.
     */
    private function attachUploads(Request $request, Entry $entry): void
    {
        $request->validate([
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['required', ...EntryAttachment::RULES],
            'attachment' => ['sometimes', ...EntryAttachment::RULES],
        ]);

        $files = $request->file('attachments', []);
        if ($request->hasFile('attachment')) {
            $files[] = $request->file('attachment');
        }

        foreach ($files as $file) {
            EntryAttachment::storeFor($entry, $file);
        }
    }

    /**
     * This month's income / expense / net, so a mutation response can refresh
     * the header without a second request.
     *
     * @return array<string, float>
     */
    private function monthTotals(Request $request): array
    {
        $now = Carbon::now();
        $entries = $request->user()->entries()
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->get(['type', 'amount_cents']);

        $income = (int) $entries->where('type', 'income')->sum('amount_cents');
        $expense = (int) $entries->where('type', 'expense')->sum('amount_cents');

        return [
            'income' => Money::toRupees($income),
            'expense' => Money::toRupees($expense),
            'net' => Money::toRupees($income - $expense),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Entry $e): array
    {
        $attachments = $e->relationLoaded('attachments') ? $e->attachments : $e->attachments()->get();
        $next = $e->nextOccurrenceAfter(Carbon::now()->startOfDay());

        return [
            'id' => $e->id,
            'type' => $e->type,
            'category' => $e->category,
            'description' => $e->description,
            'payee' => $e->payee,
            'method' => $e->method,
            'amount' => Money::toRupees($e->amount_cents),
            'occurred_on' => $e->occurred_on->format('Y-m-d'),
            'repeat' => $e->repeat ?? 'none',
            'recurring' => $e->repeats(),
            'repeat_until' => $e->repeat_until?->format('Y-m-d'),
            'next_occurrence' => $next?->format('Y-m-d'),
            'attachments' => $attachments->map(fn (EntryAttachment $a) => $a->toApi())->values()->all(),
            'attachment_count' => $attachments->count(),
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'category' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'payee' => ['nullable', 'string', 'max:120'],
            'method' => ['nullable', 'string', 'max:60'],
            'occurred_on' => ['required', 'date'],
            'repeat' => ['nullable', Rule::in(Options::entryRepeatOptions())],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:occurred_on'],
        ]);

        return [
            'type' => $validated['type'],
            'amount_cents' => Money::toCents($validated['amount']),
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'payee' => $validated['payee'] ?? null,
            'method' => $validated['method'] ?? null,
            'occurred_on' => $validated['occurred_on'],
            'repeat' => $validated['repeat'] ?? 'none',
            'repeat_until' => $validated['repeat_until'] ?? null,
        ];
    }
}
