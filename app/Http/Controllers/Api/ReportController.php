<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Entry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * A monthly financial report with income, expense and category breakdown.
     * (insights / reports screen)
     */
    public function index(Request $request): JsonResponse
    {
        [$start, $end, $month] = $this->range($request);
        $user = $request->user();

        $entries = $user->entries()->whereBetween('occurred_on', [$start, $end])->get();
        $incomeCents = (int) $entries->where('type', 'income')->sum('amount_cents');
        $expenseCents = (int) $entries->where('type', 'expense')->sum('amount_cents');

        $byCategory = $entries->where('type', 'expense')->groupBy('category')
            ->map(fn ($rows, $cat) => [
                'category' => $cat ?: 'Other',
                'amount' => Money::toRupees((int) $rows->sum('amount_cents')),
                'percent' => $expenseCents > 0 ? round((int) $rows->sum('amount_cents') / $expenseCents * 100) : 0,
            ])->sortByDesc('amount')->values();

        return response()->json([
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'prev' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'next' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'summary' => [
                'income' => Money::toRupees($incomeCents),
                'expense' => Money::toRupees($expenseCents),
                'net' => Money::toRupees($incomeCents - $expenseCents),
                'savings_rate' => $incomeCents > 0 ? round(($incomeCents - $expenseCents) / $incomeCents * 100) : 0,
                'count' => $entries->count(),
            ],
            'by_category' => $byCategory,
            'budgets' => $this->budgetRows($user, $entries),
            'user_name' => $user->name,
            'currency' => $user->currency,
            'export_url' => url('/api/reports/export?month='.$month->format('Y-m')),
        ]);
    }

    /**
     * Each budget with what was actually spent against it in the reported
     * month — computed from that month's expense entries, never stored.
     *
     * @param  Collection<int, Entry>  $entries
     * @return list<array<string, mixed>>
     */
    private function budgetRows(User $user, Collection $entries): array
    {
        $spentByCategory = $entries->where('type', 'expense')
            ->groupBy('category')
            ->map(fn ($rows) => (int) $rows->sum('amount_cents'));

        return $user->budgets()->whereNull('household_id')->orderBy('category')->get()
            ->map(function (Budget $b) use ($spentByCategory) {
                $spent = (int) ($spentByCategory[$b->category] ?? 0);

                return [
                    'id' => $b->id,
                    'category' => $b->category,
                    'limit' => Money::toRupees($b->limit_cents),
                    'spent' => Money::toRupees($spent),
                    'remaining' => Money::toRupees(max(0, $b->limit_cents - $spent)),
                    'percent' => $b->limit_cents > 0 ? (int) round($spent / $b->limit_cents * 100) : 0,
                    'progress' => $b->limit_cents > 0 ? round(min(1, $spent / $b->limit_cents), 2) : 0.0,
                    'exceeded' => $spent > $b->limit_cents,
                ];
            })->sortByDesc('percent')->values()->all();
    }

    /**
     * Export the month's transactions as a CSV file.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        [$start, $end, $month] = $this->range($request);

        $user = $request->user();
        $entries = $user->entries()
            ->whereBetween('occurred_on', [$start, $end])
            ->orderBy('occurred_on')->get();

        $filename = 'moneycoach-'.$month->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($entries, $user) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // Header names the user's own currency so the file is unambiguous.
            fputcsv($out, ['Date', 'Type', 'Category', 'Description', 'Paid to / From', 'Method', 'Amount ('.$user->currency.')']);
            foreach ($entries as $e) {
                /** @var Entry $e */
                fputcsv($out, [
                    $e->occurred_on->format('Y-m-d'),
                    $e->type,
                    $e->category,
                    $e->description,
                    $e->payee,
                    $e->method,
                    number_format(Money::toRupees($e->amount_cents), 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon}
     */
    private function range(Request $request): array
    {
        $month = Carbon::parse($request->query('month', Carbon::now()->format('Y-m')).'-01')->startOfMonth();

        return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $month];
    }
}
