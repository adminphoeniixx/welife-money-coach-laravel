<?php

namespace App\Http\Controllers;

use App\Models\FinanceAccount;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
     * The Net Worth page: assets minus liabilities.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $accounts = $user->financeAccounts()->orderByDesc('balance_cents')->get();
        $assetsCents = (int) $accounts->sum('balance_cents');
        $liabilitiesCents = (int) $user->debts()->where('status', 'active')->sum('balance_cents');

        return Inertia::render('networth/Index', [
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
            'accounts' => $accounts->map(fn (FinanceAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'type_label' => self::TYPES[$a->type] ?? 'Other',
                'balance' => Money::toRupees($a->balance_cents),
                'note' => $a->note,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->financeAccounts()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset added.']);

        return back();
    }

    public function update(Request $request, FinanceAccount $asset): RedirectResponse
    {
        abort_unless($asset->user_id === $request->user()->id, 403);

        $asset->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset updated.']);

        return back();
    }

    public function destroy(Request $request, FinanceAccount $asset): RedirectResponse
    {
        abort_unless($asset->user_id === $request->user()->id, 403);

        $asset->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset removed.']);

        return back();
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
