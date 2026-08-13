<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Options;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    /**
     * Every dropdown / picker option the app renders, in one call.
     *
     * The app must not hardcode any of these lists — each screen endpoint
     * repeats the lists it needs, and this endpoint serves the full catalogue
     * so the app can cache it once at launch.
     */
    public function options(): JsonResponse
    {
        return response()->json(Options::all());
    }
}
