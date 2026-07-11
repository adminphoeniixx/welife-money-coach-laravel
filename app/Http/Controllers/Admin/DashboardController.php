<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryTemplate;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Platform monitoring overview for administrators.
     */
    public function index(): Response
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        $signupTrend = collect(range(5, 0))->map(function (int $monthsAgo) use ($now) {
            $month = $now->copy()->subMonths($monthsAgo)->startOfMonth();

            return [
                'label' => $month->format('M'),
                'count' => User::whereBetween('created_at', [
                    $month,
                    $month->copy()->endOfMonth(),
                ])->count(),
            ];
        })->values();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'totalUsers' => User::count(),
                'admins' => User::where('is_admin', true)->count(),
                'newThisMonth' => User::where('created_at', '>=', $startOfMonth)->count(),
                'verifiedUsers' => User::whereNotNull('email_verified_at')->count(),
                'activePlans' => Plan::where('is_active', true)->count(),
                'categoryTemplates' => CategoryTemplate::count(),
            ],
            'signupTrend' => $signupTrend,
            'recentUsers' => User::latest()->take(5)->get([
                'id', 'name', 'email', 'is_admin', 'created_at',
            ]),
        ]);
    }
}
