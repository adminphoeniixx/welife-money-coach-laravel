<?php

namespace App\Http\Controllers;

use App\Services\CoachService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DebtCoachController extends Controller
{
    /**
     * Interactive payoff coach: Snowball vs Avalanche + extra-payment simulator.
     */
    public function index(Request $request, CoachService $coach): Response
    {
        $strategy = (string) $request->query('strategy', 'avalanche');
        $extra = Money::toCents((float) $request->query('extra', '0'));

        return Inertia::render('debts/Coach', [
            'plan' => $coach->coachPlan($request->user(), $strategy, $extra),
        ]);
    }
}
