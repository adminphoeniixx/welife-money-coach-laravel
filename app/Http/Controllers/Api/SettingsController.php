<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PresentsUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use PresentsUser;

    /** Currencies offered on the region screen. */
    private const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD'];

    /** Notification channels the user can toggle (setNotif screen). */
    private const NOTIF_CHANNELS = ['bill_reminders', 'budget_alerts', 'goal_milestones', 'weekly_summary', 'debt_tips'];

    /**
     * Region / currency settings. (setRegion screen)
     */
    public function showRegion(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'currencies' => self::CURRENCIES,
            'currency' => $user->currency,
            'locale' => $user->locale,
            'country' => $user->country,
        ]);
    }

    public function updateRegion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'in:'.implode(',', self::CURRENCIES)],
            'locale' => ['nullable', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $request->user()->update([
            'currency' => $validated['currency'],
            'locale' => $validated['locale'] ?? $request->user()->locale,
            'country' => $validated['country'] ?? $request->user()->country,
        ]);

        return response()->json(['message' => 'Region settings saved.', 'user' => $this->userPayload($request->user()->fresh())]);
    }

    /**
     * Notification preferences. (setNotif screen)
     */
    public function showNotifications(Request $request): JsonResponse
    {
        $prefs = $request->user()->notification_prefs ?? [];

        return response()->json([
            'notifications_enabled' => $request->user()->notifications_enabled,
            'channels' => collect(self::NOTIF_CHANNELS)->mapWithKeys(
                fn (string $key) => [$key => (bool) ($prefs[$key] ?? true)]
            ),
        ]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $rules = ['notifications_enabled' => ['required', 'boolean']];
        foreach (self::NOTIF_CHANNELS as $channel) {
            $rules["channels.{$channel}"] = ['nullable', 'boolean'];
        }
        $validated = $request->validate($rules);

        $prefs = [];
        foreach (self::NOTIF_CHANNELS as $channel) {
            $prefs[$channel] = (bool) ($validated['channels'][$channel] ?? true);
        }

        $request->user()->update([
            'notifications_enabled' => $validated['notifications_enabled'],
            'notification_prefs' => $prefs,
        ]);

        return response()->json(['message' => 'Notification settings saved.', 'user' => $this->userPayload($request->user()->fresh())]);
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
