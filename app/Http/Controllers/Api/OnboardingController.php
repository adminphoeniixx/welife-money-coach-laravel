<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PresentsUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    use PresentsUser;

    /** Choices offered by the onboarding screens (onbCurrency / onbGoal). */
    private const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD'];

    private const GOALS = [
        'get_out_of_debt' => 'Get out of debt',
        'build_emergency_fund' => 'Build an emergency fund',
        'save_for_goal' => 'Save for a big goal',
        'track_spending' => 'Track my spending',
        'grow_wealth' => 'Grow my wealth',
    ];

    /**
     * The onboarding options + the user's current answers.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'currencies' => self::CURRENCIES,
            'goals' => collect(self::GOALS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Persist onboarding answers (currency, primary goal, notification opt-in).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'in:'.implode(',', self::CURRENCIES)],
            'primary_goal' => ['nullable', 'in:'.implode(',', array_keys(self::GOALS))],
            'notifications_enabled' => ['required', 'boolean'],
            'locale' => ['nullable', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $user = $request->user();
        $user->update([
            'currency' => $validated['currency'],
            'primary_goal' => $validated['primary_goal'] ?? $user->primary_goal,
            'notifications_enabled' => $validated['notifications_enabled'],
            'locale' => $validated['locale'] ?? $user->locale,
            'country' => $validated['country'] ?? $user->country,
            'onboarded' => true,
        ]);

        return response()->json([
            'message' => 'Welcome to MoneyCoach!',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }
}
