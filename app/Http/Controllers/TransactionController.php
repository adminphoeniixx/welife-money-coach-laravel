<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /** Suggested categories offered in the UI (free text is still allowed). */
    private const CATEGORIES = [
        'income' => ['Salary', 'Business', 'Freelance', 'Rent', 'Interest', 'Dividends', 'Bonus', 'Gift', 'Refund', 'Other'],
        'expense' => ['Food', 'Housing', 'Transport', 'Utilities', 'Loans', 'Insurance', 'Healthcare', 'Education', 'Shopping', 'Entertainment', 'Investments', 'Other'],
    ];

    /**
     * Income and expense ledger for the current month, grouped by day.
     */
    public function index(Request $request): Response
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

        return Inertia::render('transactions/Index', [
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
                    'items' => $rows->map(fn (Entry $e) => [
                        'id' => $e->id,
                        'type' => $e->type,
                        'category' => $e->category,
                        'description' => $e->description,
                        'payee' => $e->payee,
                        'method' => $e->method,
                        'amount' => Money::toRupees($e->amount_cents),
                        'occurred_on' => $e->occurred_on->format('Y-m-d'),
                    ])->values()->all(),
                ])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $request->user()->entries()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($data['type']).' added.']);

        return back();
    }

    public function update(Request $request, Entry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $entry->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction updated.']);

        return back();
    }

    public function destroy(Request $request, Entry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $entry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction deleted.']);

        return back();
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
