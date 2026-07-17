<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinanceAccount;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    /**
     * Asset types, keyed by stored value with a display label.
     *
     * @var array<string, string>
     */
    private const TYPES = [
        'bank' => 'Bank Balance',
        'cash' => 'Cash',
        'gold' => 'Gold',
        'fixed_deposit' => 'Fixed Deposit',
        'mutual_fund' => 'Mutual Fund',
        'stocks' => 'Stocks',
        'property' => 'Property',
        'other' => 'Other',
    ];

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
            'types' => collect(self::TYPES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'summary' => [
                'assets' => Money::toRupees($assetsCents),
                'liabilities' => Money::toRupees($liabilitiesCents),
                'net_worth' => Money::toRupees($assetsCents - $liabilitiesCents),
            ],
            'breakdown' => $accounts->groupBy('type')->map(fn ($rows, $type) => [
                'type' => $type,
                'label' => self::TYPES[$type] ?? 'Other',
                'total' => Money::toRupees((int) $rows->sum('balance_cents')),
                'percent' => $assetsCents > 0 ? round((int) $rows->sum('balance_cents') / $assetsCents * 100) : 0,
            ])->sortByDesc('total')->values(),
            'accounts' => $accounts->map($this->present(...))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $asset = $request->user()->financeAccounts()->create($this->validated($request));

        return response()->json(['message' => 'Asset added.', 'asset' => $this->present($asset)], 201);
    }

    public function update(Request $request, FinanceAccount $asset): JsonResponse
    {
        abort_unless($asset->user_id === $request->user()->id, 403);

        $asset->update($this->validated($request));

        return response()->json(['message' => 'Asset updated.', 'asset' => $this->present($asset->fresh())]);
    }

    public function destroy(Request $request, FinanceAccount $asset): JsonResponse
    {
        abort_unless($asset->user_id === $request->user()->id, 403);

        $asset->delete();

        return response()->json(['message' => 'Asset removed.']);
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
            'type_label' => self::TYPES[$a->type] ?? 'Other',
            'balance' => Money::toRupees($a->balance_cents),
            'note' => $a->note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
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
