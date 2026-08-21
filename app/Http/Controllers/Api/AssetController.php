<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinanceAccount;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    /**
     * The Net Worth screen: assets minus liabilities. (networth screen)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $accounts = $user->financeAccounts()->orderByDesc('balance_cents')->get();
        $assetsCents = (int) $accounts->sum('balance_cents');
        $liabilitiesCents = (int) $user->debts()->where('status', 'active')->sum('balance_cents');

        return response()->json([
            'types' => Options::assetTypes(),
            'summary' => [
                'assets' => Money::toRupees($assetsCents),
                'liabilities' => Money::toRupees($liabilitiesCents),
                'net_worth' => Money::toRupees($assetsCents - $liabilitiesCents),
            ],
            'breakdown' => $accounts->groupBy('type')->map(fn ($rows, $type) => [
                'type' => $type,
                'label' => Options::assetTypeLabel((string) $type),
                'total' => Money::toRupees((int) $rows->sum('balance_cents')),
                'percent' => $assetsCents > 0 ? round((int) $rows->sum('balance_cents') / $assetsCents * 100) : 0,
            ])->sortByDesc('total')->values(),
            'accounts' => $accounts->map($this->present(...))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $asset = $request->user()->financeAccounts()->create($this->validated($request));

        return response()->json([
            'message' => 'Asset added.',
            'asset' => $this->present($asset),
            'summary' => $this->summary($request),
        ], 201);
    }

    public function update(Request $request, FinanceAccount $asset): JsonResponse
    {
        abort_unless($asset->user_id === $request->user()->id, 403);

        $asset->update($this->validated($request));

        return response()->json([
            'message' => 'Asset updated.',
            'asset' => $this->present($asset->fresh()),
            'summary' => $this->summary($request),
        ]);
    }

    public function destroy(Request $request, FinanceAccount $asset): JsonResponse
    {
        abort_unless($asset->user_id === $request->user()->id, 403);

        $deletedId = $asset->id;
        $asset->delete();

        return response()->json([
            'message' => 'Asset removed.',
            'deleted_id' => $deletedId,
            'summary' => $this->summary($request),
        ]);
    }

    /**
     * Assets, liabilities and net worth — the same object GET /net-worth
     * returns, so mutations can refresh the header on their own.
     *
     * @return array<string, float>
     */
    private function summary(Request $request): array
    {
        $user = $request->user();
        $assetsCents = (int) $user->financeAccounts()->sum('balance_cents');
        $liabilitiesCents = (int) $user->debts()->where('status', 'active')->sum('balance_cents');

        return [
            'assets' => Money::toRupees($assetsCents),
            'liabilities' => Money::toRupees($liabilitiesCents),
            'net_worth' => Money::toRupees($assetsCents - $liabilitiesCents),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FinanceAccount $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'type' => $a->type,
            'type_label' => Options::assetTypeLabel($a->type),
            'balance' => Money::toRupees($a->balance_cents),
            'note' => $a->note,
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(Options::assetTypeKeys())],
            'balance' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'name' => $v['name'],
            'type' => $v['type'],
            'balance_cents' => Money::toCents($v['balance']),
            'note' => $v['note'] ?? null,
        ];
    }
}
