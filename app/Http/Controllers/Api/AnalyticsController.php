<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Yearly analytics for the Insights screen: income-vs-expense per month,
     * savings metrics, and a category breakdown for the whole year.
     * (insights screen)
     */
    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', (string) Carbon::now()->year);
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();
        $user = $request->user();

        $entries = $user->entries()->whereBetween('occurred_on', [$start, $end])->get();
        $incomeCents = (int) $entries->where('type', 'income')->sum('amount_cents');
        $expenseCents = (int) $entries->where('type', 'expense')->sum('amount_cents');

        // 12-month income vs expense series (Jan → Dec).
        $byMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $rows = $entries->filter(fn ($e) => $e->occurred_on->month === $m);
            $byMonth[] = [
                'month' => Carbon::create($year, $m, 1)->format('M'),
                'income' => Money::toRupees((int) $rows->where('type', 'income')->sum('amount_cents')),
                'expense' => Money::toRupees((int) $rows->where('type', 'expense')->sum('amount_cents')),
            ];
        }

        // How many months actually have activity — for a fair "avg monthly" figure.
        $activeMonths = $entries->groupBy(fn ($e) => $e->occurred_on->month)->count();
        $divisor = max(1, $activeMonths);

        $byCategory = $entries->where('type', 'expense')->groupBy('category')
            ->map(fn ($rows, $cat) => [
                'category' => $cat ?: 'Other',
                'amount' => Money::toRupees((int) $rows->sum('amount_cents')),
                'percent' => $expenseCents > 0 ? round((int) $rows->sum('amount_cents') / $expenseCents * 100) : 0,
            ])->sortByDesc('amount')->values();

        return response()->json([
            'year' => $year,
            'prev' => $year - 1,
            'next' => $year + 1,
            'summary' => [
                'income' => Money::toRupees($incomeCents),
                'expense' => Money::toRupees($expenseCents),
                'net' => Money::toRupees($incomeCents - $expenseCents),
                'savings_rate' => $incomeCents > 0 ? round(($incomeCents - $expenseCents) / $incomeCents * 100) : 0,
                'avg_monthly_savings' => Money::toRupees((int) round(($incomeCents - $expenseCents) / $divisor)),
                'avg_monthly_expense' => Money::toRupees((int) round($expenseCents / $divisor)),
                'count' => $entries->count(),
            ],
            'by_month' => $byMonth,
            'by_category' => $byCategory,
        ]);
    }
}
