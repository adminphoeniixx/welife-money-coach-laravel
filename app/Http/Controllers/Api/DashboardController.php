<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoachService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * The financial-coach home dashboard (home screen): health score, net
     * worth, AI tips, priority payment, dues, spending, trend.
     */
    public function index(Request $request, CoachService $coach): JsonResponse
    {
        return response()->json($coach->snapshot($request->user()));
    }
}
