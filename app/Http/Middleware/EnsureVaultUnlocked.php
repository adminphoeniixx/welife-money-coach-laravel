<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the Secure Documents Vault behind its PIN.
 *
 * A request may only reach vault contents when the session was unlocked with
 * the correct PIN within the sliding timeout window. Without a PIN set, or
 * when locked/expired, the user is bounced to the vault gate screen.
 */
class EnsureVaultUnlocked
{
    /** Sliding inactivity timeout for an unlocked vault, in seconds. */
    public const TIMEOUT = 900;

    public const SESSION_KEY = 'vault_unlocked_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // No PIN yet — force the user through vault setup first.
        if ($user === null || ! $user->hasVaultPin()) {
            return redirect()->route('vault.gate');
        }

        $unlockedAt = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($unlockedAt === 0 || (time() - $unlockedAt) > self::TIMEOUT) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('vault.gate');
        }

        // Slide the window forward on activity.
        $request->session()->put(self::SESSION_KEY, time());

        return $next($request);
    }
}
