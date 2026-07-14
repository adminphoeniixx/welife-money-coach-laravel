<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    /**
     * The Budgets & Goals planning page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $now = Carbon::now();

        $spentByCategory = $user->entries()
            ->where('type', 'expense')
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->selectRaw('category, sum(amount_cents) as spent')
            ->groupBy('category')
            ->pluck('spent', 'category');

        $budgets = $user->budgets()->whereNull('household_id')->orderBy('category')->get()->map(function (Budget $b) use ($spentByCategory) {
            $spent = (int) ($spentByCategory[$b->category] ?? 0);

            return [
                'id' => $b->id,
                'category' => $b->category,
                'limit' => Money::toRupees($b->limit_cents),
                'spent' => Money::toRupees($spent),
                'percent' => $b->limit_cents > 0 ? round($spent / $b->limit_cents * 100) : 0,
                'exceeded' => $spent > $b->limit_cents,
            ];
        });

        $goals = $user->goals()->latest()->get()->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'type' => $g->type,
            'target' => Money::toRupees($g->target_cents),
            'saved' => Money::toRupees($g->saved_cents),
            'progress' => $g->progress(),
            'target_date' => $g->target_date?->format('Y-m-d'),
        ]);

        return Inertia::render('planning/Index', [
            'budgets' => $budgets,
            'goals' => $goals,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->budgets()->create($this->validated($request, $request->user()->id));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Budget set.']);

        return back();
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 403);

        $budget->update($this->validated($request, $request->user()->id, $budget->id));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Budget updated.']);

        return back();
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 403);

        $budget->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Budget removed.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $userId, ?int $ignoreId = null): array
    {
        $v = $request->validate([
            'category' => [
                'required', 'string', 'max:60',
                Rule::unique('budgets', 'category')
                    ->where(fn ($q) => $q->where('user_id', $userId)->whereNull('household_id'))
                    ->ignore($ignoreId),
            ],
            'limit' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        return [
            'category' => $v['category'],
            'limit_cents' => Money::toCents($v['limit']),
        ];
    }
}
