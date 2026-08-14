<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ChallengeController extends Controller
{
    /**
     * Active challenges plus presets the user can still join. (challenges screen)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = $user->challenges()->latest()->get();
        $joinedKeys = $active->pluck('key')->all();

        return response()->json([
            'active' => $active->map($this->present(...))->values(),
            'presets' => collect(Challenge::presetsFor($user))
                ->reject(fn ($_, $key) => in_array($key, $joinedKeys, true))
                ->map(fn ($p, $key) => [
                    'key' => $key,
                    'title' => $p['title'],
                    'description' => $p['description'],
                    'target' => Money::toRupees($p['target']),
                ])->values(),
        ]);
    }

    /**
     * Join a preset challenge for the current month.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', Rule::in(array_keys(Challenge::PRESETS))],
        ]);

        // Render the preset copy in the joining user's currency.
        $preset = Challenge::presetsFor($request->user())[$validated['key']];

        $challenge = $request->user()->challenges()->firstOrCreate(
            ['key' => $validated['key'], 'status' => 'active'],
            [
                'title' => $preset['title'],
                'description' => $preset['description'],
                'target_cents' => $preset['target'],
                'progress_cents' => 0,
                'started_on' => Carbon::now(),
                'ends_on' => Carbon::now()->endOfMonth(),
            ],
        );

        return response()->json([
            'message' => 'Challenge accepted! 💪',
            'id' => $challenge->id,
            'challenge' => $this->present($challenge->fresh()),
        ], 201);
    }

    /**
     * Log progress toward a challenge.
     */
    public function progress(Request $request, Challenge $challenge): JsonResponse
    {
        abort_unless($challenge->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
        ]);

        $progress = $challenge->progress_cents + Money::toCents($validated['amount']);
        $done = $progress >= $challenge->target_cents;

        $challenge->update([
            'progress_cents' => $progress,
            'status' => $done ? 'completed' : 'active',
        ]);

        return response()->json([
            'message' => $done ? '🏆 Challenge complete: '.$challenge->title.'!' : 'Progress logged.',
            'completed' => $done,
            'percent' => $challenge->fresh()->progress(),
            'challenge' => $this->present($challenge->fresh()),
        ]);
    }

    /**
     * The one challenge shape used by the list and every mutation response.
     *
     * @return array<string, mixed>
     */
    private function present(Challenge $c): array
    {
        return [
            'id' => $c->id,
            'key' => $c->key,
            'title' => $c->title,
            'description' => $c->description,
            'target' => Money::toRupees($c->target_cents),
            'progress' => Money::toRupees($c->progress_cents),
            'percent' => $c->progress(),
            'status' => $c->status,
            'started_on' => $c->started_on->format('Y-m-d'),
            'ends_on' => $c->ends_on->format('Y-m-d'),
            'days_left' => max(0, (int) round(Carbon::now()->startOfDay()->diffInDays($c->ends_on, false))),
        ];
    }

    public function destroy(Request $request, Challenge $challenge): JsonResponse
    {
        abort_unless($challenge->user_id === $request->user()->id, 403);

        $deletedId = $challenge->id;
        $challenge->delete();

        return response()->json(['message' => 'Challenge left.', 'deleted_id' => $deletedId]);
    }
}
