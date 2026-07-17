<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    /** Suggested categories offered in the UI (free text is still allowed). */
    private const CATEGORIES = [
        'income' => ['Salary', 'Business', 'Freelance', 'Rent', 'Interest', 'Dividends', 'Bonus', 'Gift', 'Refund', 'Other'],
        'expense' => ['Food', 'Housing', 'Transport', 'Utilities', 'Loans', 'Insurance', 'Healthcare', 'Education', 'Shopping', 'Entertainment', 'Investments', 'Other'],
    ];

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
            'categories' => self::CATEGORIES,
            'totals' => [
                'income' => Money::toRupees($incomeCents),
                'expense' => Money::toRupees($expenseCents),
                'net' => Money::toRupees($incomeCents - $expenseCents),
            ],
            'groups' => $entries
                ->groupBy(fn (Entry $e) => $e->occurred_on->format('Y-m-d'))
                ->map(fn ($rows, $day) => [
                    'date' => Carbon::parse((string) $day)->format('D, d M'),
                    'items' => $rows->map($this->present(...))->values()->all(),
                ])->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $entry = $request->user()->entries()->create($this->validated($request));

        return response()->json([
            'message' => ucfirst($entry->type).' added.',
            'entry' => $this->present($entry),
        ], 201);
    }

    public function update(Request $request, Entry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $entry->update($this->validated($request));

        return response()->json([
            'message' => 'Transaction updated.',
            'entry' => $this->present($entry->fresh()),
        ]);
    }

    public function destroy(Request $request, Entry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $entry->delete();

        return response()->json(['message' => 'Transaction deleted.']);
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
