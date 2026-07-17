<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Goal;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    /**
     * The Budgets & Goals planning screen. (budgets / emergency screens)
     */
    public function index(Request $request): JsonResponse
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

        return response()->json([
            'budgets' => $budgets->values(),
            'goals' => $user->goals()->latest()->get()->map($this->presentGoal(...))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $budget = $request->user()->budgets()->create($this->validated($request, $request->user()->id));

        return response()->json(['message' => 'Budget set.', 'budget' => $this->presentBudget($budget)], 201);
    }

    public function update(Request $request, Budget $budget): JsonResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 403);

        $budget->update($this->validated($request, $request->user()->id, $budget->id));

        return response()->json(['message' => 'Budget updated.', 'budget' => $this->presentBudget($budget->fresh())]);
    }

    public function destroy(Request $request, Budget $budget): JsonResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 403);

        $budget->delete();

        return response()->json(['message' => 'Budget removed.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBudget(Budget $b): array
    {
        return [
            'id' => $b->id,
            'category' => $b->category,
            'limit' => Money::toRupees($b->limit_cents),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentGoal(Goal $g): array
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
