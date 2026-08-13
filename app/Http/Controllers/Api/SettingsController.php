<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Api\Concerns\PresentsUser;
use App\Http\Controllers\Controller;
use App\Support\Currency;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use PresentsUser, ProfileValidationRules;

    /**
     * Region / currency settings. (setRegion screen)
     */
    public function showRegion(Request $request): JsonResponse
    {
        $user = $request->user();

        $timezone = $user->timezone ?: Options::timezoneForCountry($user->country);

        return response()->json([
            // Current selection…
            'currency' => $user->currency,
            'symbol' => $user->currencySymbol(),
            'locale' => $user->locale,
            'country' => $user->country,
            'timezone' => $timezone,
            'number_format' => $user->number_format,
            // …and every option the pickers can offer.
            'currencies' => Currency::codes(),
            'currency_details' => Currency::options(),
            'countries' => Options::countries(),
            'timezones' => Options::timezones(),
            'number_formats' => Options::numberFormats(),
        ]);
    }

    /**
     * Change the region. Sending a country alone switches the currency and
     * locale with it; sending a currency alone overrides just the currency.
     */
    public function updateRegion(Request $request): JsonResponse
    {
        $validated = $request->validate($this->regionRules());

        $user = $request->user();
        $previous = $user->currency;

        $user->applyRegion(
            $validated['country'] ?? null,
            $validated['currency'] ?? null,
            $validated['locale'] ?? null,
            $validated['timezone'] ?? null,
            $validated['number_format'] ?? null,
        )->save();

        if ($user->currency !== $previous) {
            $user->restampRecordCurrency();
        }

        return response()->json(['message' => 'Region settings saved.', 'user' => $this->userPayload($user->fresh())]);
    }

    /**
     * Notification preferences. (setNotif screen)
     */
    public function showNotifications(Request $request): JsonResponse
    {
        return response()->json([
            'notifications_enabled' => $request->user()->notifications_enabled,
            'channels' => $this->notificationPrefs($request->user()),
            'available_channels' => Options::notificationChannels(),
        ]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $channels = Options::notificationChannelKeys();

        $rules = ['notifications_enabled' => ['required', 'boolean']];
        foreach ($channels as $channel) {
            $rules["channels.{$channel}"] = ['nullable', 'boolean'];
        }
        $validated = $request->validate($rules);

        $prefs = [];
        foreach ($channels as $channel) {
            $prefs[$channel] = (bool) ($validated['channels'][$channel] ?? true);
        }

        $user = $request->user();
        $user->update([
            'notifications_enabled' => $validated['notifications_enabled'],
            'notification_prefs' => $prefs,
        ]);

        return response()->json([
            'message' => 'Notification settings saved.',
            'notifications_enabled' => $user->notifications_enabled,
            'channels' => $this->notificationPrefs($user->fresh()),
            'available_channels' => Options::notificationChannels(),
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    /**
     * Data & privacy overview: counts of the data we hold. (dataPrivacy screen)
     */
    public function dataPrivacy(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'counts' => [
                'transactions' => $user->entries()->count(),
                'debts' => $user->debts()->count(),
                'assets' => $user->financeAccounts()->count(),
                'budgets' => $user->budgets()->count(),
                'goals' => $user->goals()->count(),
                'reminders' => $user->bills()->count(),
                'documents' => $user->documents()->count(),
                'challenges' => $user->challenges()->count(),
            ],
            'account_created' => $user->created_at?->toIso8601String(),
            'privacy_url' => url('/legal/privacy'),
            'terms_url' => url('/legal/terms'),
        ]);
    }

    /**
     * Export all of the user's data as JSON (data-portability / GDPR).
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'profile' => $this->userPayload($user),
            'transactions' => $user->entries()->get(),
            'debts' => $user->debts()->get(),
            'assets' => $user->financeAccounts()->get(),
            'budgets' => $user->budgets()->get(),
            'goals' => $user->goals()->get(),
            'reminders' => $user->bills()->get(),
            'challenges' => $user->challenges()->get(),
        ], 200, ['Content-Disposition' => 'attachment; filename="moneycoach-data.json"']);
    }
}
