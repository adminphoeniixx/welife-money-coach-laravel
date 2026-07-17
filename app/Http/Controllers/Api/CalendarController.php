<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    /**
     * A month grid of all bill/EMI/subscription due dates. (calendar screen)
     */
    public function index(Request $request): JsonResponse
    {
        $month = Carbon::parse($request->query('month', Carbon::now()->format('Y-m')).'-01')->startOfMonth();

        $bills = $request->user()->bills()
            ->whereBetween('due_date', [
                $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            ])->get()
            ->groupBy(fn (Bill $b) => $b->due_date->format('Y-m-d'));

        $cursor = $month->copy()->startOfWeek(Carbon::MONDAY);
        $today = Carbon::now()->format('Y-m-d');
        $days = [];

        for ($i = 0; $i < 42; $i++) {
            $key = $cursor->format('Y-m-d');
            $items = ($bills[$key] ?? collect())->map(fn (Bill $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'kind' => $b->kind,
                'amount' => Money::toRupees($b->amount_cents),
                'status' => $b->status,
            ])->values();

            $days[] = [
                'date' => $key,
                'day' => $cursor->day,
                'in_month' => $cursor->month === $month->month,
                'today' => $key === $today,
                'items' => $items,
            ];
            $cursor->addDay();
        }

        return response()->json([
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'prev' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'next' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'weekdays' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'days' => $days,
        ]);
    }
}
