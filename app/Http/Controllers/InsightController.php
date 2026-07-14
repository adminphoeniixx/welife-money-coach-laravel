<?php

namespace App\Http\Controllers;

use App\Services\InsightService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsightController extends Controller
{
    /**
     * Achievement / milestone wall.
     */
    public function achievements(Request $request, InsightService $insights): Response
    {
        $items = $insights->achievements($request->user());

        return Inertia::render('insights/Achievements', [
            'achievements' => $items,
            'earned' => collect($items)->where('earned', true)->count(),
            'total' => count($items),
        ]);
    }

    /**
     * Smart notifications centre.
     */
    public function notifications(Request $request, InsightService $insights): Response
    {
        return Inertia::render('insights/Notifications', [
            'notifications' => $insights->notifications($request->user()),
        ]);
    }
}
