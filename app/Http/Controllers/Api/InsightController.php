<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    /**
     * Achievement / milestone wall. (achievements screen)
     */
    public function achievements(Request $request, InsightService $insights): JsonResponse
    {
        $items = $insights->achievements($request->user());

        return response()->json([
            'achievements' => $items,
            'earned' => collect($items)->where('earned', true)->count(),
            'total' => count($items),
        ]);
    }

    /**
     * Smart notifications centre. (notifications screen)
     */
    public function notifications(Request $request, InsightService $insights): JsonResponse
    {
        return response()->json([
            'notifications' => $insights->notifications($request->user()),
        ]);
    }
}
