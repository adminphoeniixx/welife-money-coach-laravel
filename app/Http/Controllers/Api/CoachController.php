<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoachService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    /**
     * Debt payoff coach (debtCoach screen): Snowball vs Avalanche plan with an
     * optional extra monthly payment.
     */
    public function index(Request $request, CoachService $coach): JsonResponse
    {
        $strategy = (string) $request->query('strategy', 'avalanche');
        $extra = Money::toCents((float) $request->query('extra', '0'));

        return response()->json([
            'plan' => $coach->coachPlan($request->user(), $strategy, $extra),
        ]);
    }
}
