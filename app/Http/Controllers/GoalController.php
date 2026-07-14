<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GoalController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->user()->goals()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Goal created.']);

        return back();
    }

    public function update(Request $request, Goal $goal): RedirectResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 403);

        $goal->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Goal updated.']);

        return back();
    }

    public function destroy(Request $request, Goal $goal): RedirectResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 403);

        $goal->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Goal removed.']);

        return back();
    }

    /**
     * Add money toward a goal. Celebrates when the target is reached.
     */
    public function contribute(Request $request, Goal $goal): RedirectResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
        ]);

        $reachedBefore = $goal->saved_cents >= $goal->target_cents;
        $goal->update(['saved_cents' => $goal->saved_cents + Money::toCents($validated['amount'])]);

        $reachedNow = $goal->saved_cents >= $goal->target_cents;

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => (! $reachedBefore && $reachedNow)
                ? '🎉 Goal reached: '.$goal->name.'!'
                : 'Added to '.$goal->name.'.',
        ]);

        return back();
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
