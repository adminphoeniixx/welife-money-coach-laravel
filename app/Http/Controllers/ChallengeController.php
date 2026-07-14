<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChallengeController extends Controller
{
    /**
     * Active challenges plus presets the user can still join.
     */
    public function index(Request $request): Response
    {
        $active = $request->user()->challenges()->latest()->get();
        $joinedKeys = $active->pluck('key')->all();

        return Inertia::render('challenges/Index', [
            'active' => $active->map(fn (Challenge $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'description' => $c->description,
                'target' => Money::toRupees($c->target_cents),
                'progress' => Money::toRupees($c->progress_cents),
                'percent' => $c->progress(),
                'status' => $c->status,
                'days_left' => max(0, (int) round(Carbon::now()->startOfDay()->diffInDays($c->ends_on, false))),
            ])->values(),
            'presets' => collect(Challenge::PRESETS)
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
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', Rule::in(array_keys(Challenge::PRESETS))],
        ]);

        $preset = Challenge::PRESETS[$validated['key']];

        $request->user()->challenges()->firstOrCreate(
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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Challenge accepted! 💪']);

        return back();
    }

    /**
     * Log progress toward a challenge.
     */
    public function progress(Request $request, Challenge $challenge): RedirectResponse
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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $done ? '🏆 Challenge complete: '.$challenge->title.'!' : 'Progress logged.',
        ]);

        return back();
    }

    public function destroy(Request $request, Challenge $challenge): RedirectResponse
    {
        abort_unless($challenge->user_id === $request->user()->id, 403);

        $challenge->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Challenge left.']);

        return back();
    }
}
