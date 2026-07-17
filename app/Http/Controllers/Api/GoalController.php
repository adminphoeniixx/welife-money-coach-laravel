<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $goal = $request->user()->goals()->create($this->validated($request));

        return response()->json(['message' => 'Goal created.', 'goal' => $this->present($goal)], 201);
    }

    public function update(Request $request, Goal $goal): JsonResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 403);

        $goal->update($this->validated($request));

        return response()->json(['message' => 'Goal updated.', 'goal' => $this->present($goal->fresh())]);
    }

    public function destroy(Request $request, Goal $goal): JsonResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 403);

        $goal->delete();

        return response()->json(['message' => 'Goal removed.']);
    }

    /**
     * Add money toward a goal. Celebrates when the target is reached.
     * (emergency fund + savings goals)
     */
    public function contribute(Request $request, Goal $goal): JsonResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
        ]);

        $reachedBefore = $goal->saved_cents >= $goal->target_cents;
        $goal->update(['saved_cents' => $goal->saved_cents + Money::toCents($validated['amount'])]);
        $reachedNow = $goal->saved_cents >= $goal->target_cents;

        return response()->json([
            'message' => (! $reachedBefore && $reachedNow)
                ? '🎉 Goal reached: '.$goal->name.'!'
                : 'Added to '.$goal->name.'.',
            'reached' => $reachedNow,
            'goal' => $this->present($goal->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Goal $g): array
    {
        return [
            'id' => $g->id,
            'name' => $g->name,
            'type' => $g->type,
            'target' => Money::toRupees($g->target_cents),
            'saved' => Money::toRupees($g->saved_cents),
            'progress' => $g->progress(),
            'target_date' => $g->target_date?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:emergency_fund,savings'],
            'target' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'saved' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'target_date' => ['nullable', 'date'],
        ]);

        return [
            'name' => $v['name'],
            'type' => $v['type'],
            'target_cents' => Money::toCents($v['target']),
            'saved_cents' => Money::toCents($v['saved'] ?? 0),
            'target_date' => $v['target_date'] ?? null,
        ];
    }
}
