<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoachService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    /**
     * The coach endpoint serves two modes off the same route:
     *
     *  - `?q=…`        Ask AI Coach — a free-text (typed or voice-transcribed)
     *                  question answered from this user's own finance data.
     *  - `?strategy=…` Debt payoff coach (debtCoach screen): Snowball vs
     *                  Avalanche with an optional extra monthly payment.
     *
     * With neither parameter it falls back to the debt plan, which is what the
     * debtCoach screen has always called.
     */
    public function index(Request $request, CoachService $coach): JsonResponse
    {
        $question = trim((string) $request->query('q', ''));

        if ($question !== '') {
            return $this->ask($request, $coach, $question);
        }

        return $this->plan($request, $coach);
    }

    /**
     * Also exposed as POST /coach so long questions are not limited by URL
     * length — the body key is `q` or `question`.
     */
    public function ask(Request $request, CoachService $coach, ?string $question = null): JsonResponse
    {
        $question ??= trim((string) ($request->input('q') ?? $request->input('question') ?? ''));

        $request->merge(['q' => $question]);
        $request->validate(['q' => ['required', 'string', 'max:500']]);

        $answer = $coach->answer($request->user(), $question);

        return response()->json([
            'question' => $answer['question'],
            // Both shapes are returned: `answers` for the list-style coach
            // screen, `answer` for a client that renders a single reply.
            'answers' => [$answer],
            'answer' => $answer['answer'],
            'tone' => $answer['tone'],
        ]);
    }

    private function plan(Request $request, CoachService $coach): JsonResponse
    {
        $strategy = (string) $request->query('strategy', 'avalanche');
        $extra = Money::toCents((float) $request->query('extra', '0'));

        return response()->json([
            'plan' => $coach->coachPlan($request->user(), $strategy, $extra),
        ]);
    }
}
