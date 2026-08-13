<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use App\Support\Options;

trait PresentsUser
{
    /**
     * The canonical JSON shape of a user returned across the mobile API.
     *
     * Every key here is stable: the app reads the same names from /api/user,
     * /api/profile, the auth responses and every settings mutation.
     *
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        $timezone = $user->timezone ?: Options::timezoneForCountry($user->country);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'currency' => $user->currency,
            'currency_symbol' => $user->currencySymbol(),
            'locale' => $user->locale,
            'country' => $user->country,
            'timezone' => $timezone,
            'timezone_label' => $timezone.' (GMT'.Options::gmtOffset($timezone).')',
            'number_format' => $user->number_format,
            'primary_goal' => $user->primary_goal,
            'onboarded' => $user->onboarded,
            'notifications_enabled' => $user->notifications_enabled,
            'notification_prefs' => $this->notificationPrefs($user),
            'has_vault_pin' => $user->hasVaultPin(),
            'has_household' => $user->hasHousehold(),
        ];
    }

    /**
     * Notification toggles with every known channel present, so the app never
     * has to guess a missing key's default.
     *
     * @return array<string, bool>
     */
    protected function notificationPrefs(User $user): array
    {
        $prefs = $user->notification_prefs ?? [];

        $resolved = [];
        foreach (Options::notificationChannelKeys() as $channel) {
            $resolved[$channel] = (bool) ($prefs[$channel] ?? true);
        }

        return $resolved;
    }
}
