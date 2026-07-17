<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stateless twin of {@see EnsureVaultUnlocked} for the token-based mobile API.
 *
 * Because API requests carry no session, an unlocked vault is tracked in the
 * cache keyed by the caller's access token, with the same sliding 15-minute
 * inactivity window. POST /vault/unlock sets the flag; a 423 (Locked) here
 * tells the app to send the user back to the vault lock screen.
 */
class EnsureVaultUnlockedApi
{
    /** Sliding inactivity timeout for an unlocked vault, in seconds. */
    public const TIMEOUT = 900;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasVaultPin()) {
            return response()->json([
                'message' => 'Vault is locked.',
                'vault' => ['locked' => true, 'reason' => $user?->hasVaultPin() ? 'locked' : 'no_pin'],
            ], 423);
        }

        $key = self::cacheKey($request);

        if ($key === null || ! Cache::has($key)) {
            return response()->json([
                'message' => 'Vault is locked.',
                'vault' => ['locked' => true, 'reason' => 'locked'],
            ], 423);
        }

        // Slide the window forward on activity.
        Cache::put($key, time(), self::TIMEOUT);

        return $next($request);
    }

    /**
     * Cache key for the current caller's unlocked-vault flag, or null when the
     * request is not authenticated with a personal access token.
     */
    public static function cacheKey(Request $request): ?string
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            return 'vault_unlocked:token:'.$token->getKey();
        }

        return null;
    }
}
