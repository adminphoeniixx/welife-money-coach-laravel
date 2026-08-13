<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    /**
     * Income and expense ledger for the current month, grouped by day.
     * (transactions screen)
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type', 'all'); // all | income | expense
        $now = Carbon::now();

        $entries = $request->user()->entries()
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->when(in_array($type, ['income', 'expense'], true), fn ($q) => $q->where('type', $type))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $incomeCents = (int) $entries->where('type', 'income')->sum('amount_cents');
        $expenseCents = (int) $entries->where('type', 'expense')->sum('amount_cents');

        return response()->json([
            'filter' => $type,
            // Master lists only — the app renders these as-is.
            'categories' => [
                'income' => Options::incomeCategories(),
                'expense' => Options::expenseCategories(),
            ],
            // Extra categories this user has created (e.g. a custom budget),
            // kept apart so the master lists stay clean.
            'custom_categories' => $this->customCategories($request),
            'payment_methods' => Options::paymentMethods(),
            'totals' => [
                'income' => Money::toRupees($incomeCents),
                'expense' => Money::toRupees($expenseCents),
                'net' => Money::toRupees($incomeCents - $expenseCents),
            ],
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

        return response()->json([
            'message' => ucfirst($entry->type).' added.',
            'entry' => $this->present($entry),
            'totals' => $this->monthTotals($request),
        ], 201);
    }

    public function update(Request $request, Entry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $entry->update($this->validated($request));

        return response()->json([
            'message' => 'Transaction updated.',
            'entry' => $this->present($entry->fresh()),
            'totals' => $this->monthTotals($request),
        ]);
    }

    public function destroy(Request $request, Entry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $deletedId = $entry->id;
        $entry->delete();

        return response()->json([
            'message' => 'Transaction deleted.',
            'deleted_id' => $deletedId,
            'totals' => $this->monthTotals($request),
        ]);
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
        return [
            'id' => $e->id,
            'type' => $e->type,
            'category' => $e->category,
            'description' => $e->description,
            'payee' => $e->payee,
            'method' => $e->method,
            'amount' => Money::toRupees($e->amount_cents),
            'occurred_on' => $e->occurred_on->format('Y-m-d'),
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
        ]);

        return [
            'type' => $validated['type'],
            'amount_cents' => Money::toCents($validated['amount']),
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'payee' => $validated['payee'] ?? null,
            'method' => $validated['method'] ?? null,
            'occurred_on' => $validated['occurred_on'],
        ];
    }
}
