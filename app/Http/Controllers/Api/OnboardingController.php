<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Api\Concerns\PresentsUser;
use App\Http\Controllers\Controller;
use App\Support\Currency;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    use PresentsUser, ProfileValidationRules;

    /**
     * The onboarding options + the user's current answers.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'currencies' => Currency::codes(),
            'currency_details' => Currency::options(),
            'countries' => Options::countries(),
            'timezones' => Options::timezones(),
            'number_formats' => Options::numberFormats(),
            'goals' => Options::onboardingGoals(),
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Persist onboarding answers (region, primary goal, notification opt-in).
     *
     * Either a country or a currency is enough — a country on its own picks
     * the matching currency and locale.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            ...$this->regionRules(),
            'primary_goal' => ['nullable', Rule::in(Options::onboardingGoalKeys())],
            'notifications_enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $previous = $user->currency;

        $user->fill([
            'primary_goal' => $validated['primary_goal'] ?? $user->primary_goal,
            'notifications_enabled' => $validated['notifications_enabled'],
            'onboarded' => true,
        ])->applyRegion(
            $validated['country'] ?? null,
            $validated['currency'] ?? null,
            $validated['locale'] ?? null,
            $validated['timezone'] ?? null,
            $validated['number_format'] ?? null,
        )->save();

        if ($user->currency !== $previous) {
            $user->restampRecordCurrency();
        }

        return response()->json([
            'message' => 'Welcome to MoneyCoach!',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }
}
