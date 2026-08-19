<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Entry;
use App\Models\Goal;
use App\Support\Money;
use App\Support\Options;
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

        $budgets = $user->budgets()->whereNull('household_id')->orderBy('category')->get()
            ->map(fn (Budget $b) => $this->presentBudget($b, (int) ($spentByCategory[$b->category] ?? 0)));

        return response()->json([
            'budget_categories' => Options::expenseCategories(),
            'goal_types' => Options::goalTypes(),
            'budgets' => $budgets->values(),
            'goals' => $user->goals()->latest()->get()->map(fn (Goal $g) => $g->toApi())->values(),
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

        $deletedId = $budget->id;
        $budget->delete();

        return response()->json(['message' => 'Budget removed.', 'deleted_id' => $deletedId]);
    }

    /**
     * The one budget shape used by the list and by every mutation response.
     *
     * @return array<string, mixed>
     */
    private function presentBudget(Budget $b, ?int $spentCents = null): array
    {
        $spentCents ??= $this->spentThisMonth($b);

        return [
            'id' => $b->id,
            'category' => $b->category,
            'limit' => Money::toRupees($b->limit_cents),
            'spent' => Money::toRupees($spentCents),
            'percent' => $b->limit_cents > 0 ? round($spentCents / $b->limit_cents * 100) : 0,
            'exceeded' => $spentCents > $b->limit_cents,
        ];
    }

    /** Current-month spend in a budget's category. */
    private function spentThisMonth(Budget $b): int
    {
        $now = Carbon::now();

        return (int) Entry::query()
            ->where('user_id', $b->user_id)
            ->where('type', 'expense')
            ->where('category', $b->category)
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->sum('amount_cents');
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
