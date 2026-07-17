<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;

trait PresentsUser
{
    /**
     * The canonical JSON shape of a user returned across the mobile API.
     *
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'currency' => $user->currency,
            'locale' => $user->locale,
            'country' => $user->country,
            'primary_goal' => $user->primary_goal,
            'onboarded' => $user->onboarded,
            'notifications_enabled' => $user->notifications_enabled,
            'notification_prefs' => $user->notification_prefs,
            'has_vault_pin' => $user->hasVaultPin(),
            'has_household' => $user->hasHousehold(),
        ];
    }
}
