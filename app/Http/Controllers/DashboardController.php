<?php

namespace App\Http\Controllers;

use App\Services\CoachService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The financial-coach home dashboard.
     */
    public function index(Request $request, CoachService $coach): Response
    {
        return Inertia::render('Dashboard', $coach->snapshot($request->user()));
    }
}
