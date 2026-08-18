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

    /**
     * Public app config, readable before sign-in.
     *
     * The login screen uses `features.social_sign_in` / `auth.social_providers`
     * to decide whether to render social buttons at all — while there is no
     * provider the app must show none rather than a button that cannot work.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'features' => Options::features(),
            'auth' => [
                'social_providers' => Options::socialProviders(),
            ],
            'shortcuts' => Options::shortcuts(),
        ]);
    }
}
