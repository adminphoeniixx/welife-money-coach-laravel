<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\DebtDocument;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DebtController extends Controller
{
    private const LOAN_CATEGORIES = ['home', 'vehicle', 'gold', 'personal', 'education', 'business', 'custom'];

    /**
     * Consolidated loans + credit cards with the avalanche payoff order.
     */
    public function index(Request $request): Response
    {
        $debts = $request->user()->debts()->with('documents')->where('status', 'active')->get();

        $totalCents = (int) $debts->sum('balance_cents');
        $emiCents = (int) $debts->sum('emi_cents');
        $weightedApr = $totalCents > 0
            ? round($debts->sum(fn (Debt $d) => $d->balance_cents * $d->interest_rate) / $totalCents, 1)
            : 0.0;

        return Inertia::render('debts/Index', [
            'loan_categories' => self::LOAN_CATEGORIES,
            'summary' => [
                'total' => Money::toRupees($totalCents),
                'monthly' => Money::toRupees($emiCents),
                'avg_apr' => $weightedApr,
                'count' => $debts->count(),
            ],
            'loans' => $debts->where('kind', 'loan')->map($this->present(...))->values(),
            'cards' => $debts->where('kind', 'credit_card')->map($this->present(...))->values(),
            'payoff_order' => $debts->sortByDesc('interest_rate')->map($this->present(...))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $debt = $request->user()->debts()->create($this->validated($request));

        $this->saveDocuments($request, $debt);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Debt added.']);

        return back();
    }

    public function update(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->user_id === $request->user()->id, 403);

        $debt->update($this->validated($request));

        $this->saveDocuments($request, $debt);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Debt updated.']);

        return back();
    }

    public function destroy(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->user_id === $request->user()->id, 403);

        $debt->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Debt removed.']);

        return back();
    }

    /**
     * Record a payment against a debt: reduce the balance, close it at zero.
     */
    public function recordPayment(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
        ]);

        $newBalance = max(0, $debt->balance_cents - Money::toCents($validated['amount']));

        // Each recorded payment on a loan counts as one EMI (capped at tenure).
        $emisPaid = $debt->emis_paid;
        if (! $debt->isCard()) {
            $emisPaid = $debt->total_emis !== null
                ? min($debt->total_emis, $debt->emis_paid + 1)
                : $debt->emis_paid + 1;
        }

        $tenureDone = $debt->total_emis !== null && $emisPaid >= $debt->total_emis;
        $closed = $newBalance === 0 || $tenureDone;

        $debt->update([
            'balance_cents' => $newBalance,
            'emis_paid' => $emisPaid,
            'status' => $closed ? 'closed' : 'active',
            'closed_at' => $closed ? Carbon::now() : null,
        ]);

        $debt->payments()->create([
            'user_id' => $debt->user_id,
            'amount_cents' => Money::toCents($validated['amount']),
            'balance_after_cents' => $newBalance,
            'emi_number' => $debt->isCard() ? null : $emisPaid,
            'paid_on' => Carbon::now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $closed ? '🎉 Paid off! '.$debt->name.' is now closed.' : 'Payment recorded.',
        ]);

        return back();
    }

    /**
     * Persist any photos / documents uploaded alongside the add / edit form.
     */
    private function saveDocuments(Request $request, Debt $debt): void
    {
        if (! $request->hasFile('documents')) {
            return;
        }

        $request->validate([
            'documents' => ['array', 'max:10'],
            'documents.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        foreach ($request->file('documents', []) as $file) {
            DebtDocument::storeFor($debt, $file);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Debt $d): array
    {
        $repayment = $d->repayment();

        return [
            'id' => $d->id,
            'name' => $d->name,
            'institution' => $d->institution,
            'kind' => $d->kind,
            'category' => $d->category,
            'balance' => Money::toRupees($d->balance_cents),
            'principal' => Money::toRupees($d->principal_cents),
            'interest_rate' => (float) $d->interest_rate,
            'emi' => Money::toRupees($d->emi_cents),
            'limit' => $d->credit_limit_cents ? Money::toRupees($d->credit_limit_cents) : null,
            'min_due' => $d->min_due_cents ? Money::toRupees($d->min_due_cents) : null,
            'due_day' => $d->due_day,
            'statement_day' => $d->statement_day,
            'utilisation' => $d->isCard() ? $d->utilisation() : null,
            'paid_percent' => $d->principal_cents > 0
                ? max(0, min(100, round(($d->principal_cents - $d->balance_cents) / $d->principal_cents * 100)))
                : null,
            // Loan repayment tracking (auto-updates as payments are recorded).
            'total_emis' => $repayment['total_emis'],
            'emis_paid' => $repayment['emis_paid'],
            'remaining_emis' => $repayment['remaining_emis'],
            'amount_paid' => Money::toRupees($repayment['amount_paid_cents']),
            'remaining_amount' => Money::toRupees($repayment['remaining_cents']),
            'repayment_progress' => $repayment['progress'],
            'documents' => $d->documents->map(fn (DebtDocument $doc) => [
                'id' => $doc->id,
                'name' => $doc->original_name,
                'is_image' => $doc->isImage(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:120'],
            'kind' => ['required', 'in:loan,credit_card'],
            'category' => ['nullable', 'in:'.implode(',', self::LOAN_CATEGORIES)],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'balance' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'principal' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'emi' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'min_due' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'statement_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'total_emis' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'emis_paid' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $isCard = $v['kind'] === 'credit_card';
        $totalEmis = $isCard ? null : ($v['total_emis'] ?? null);
        $emisPaid = $isCard ? 0 : min((int) ($v['emis_paid'] ?? 0), $totalEmis ?? PHP_INT_MAX);

        return [
            'name' => $v['name'],
            'institution' => $v['institution'] ?? null,
            'kind' => $v['kind'],
            'category' => $isCard ? null : ($v['category'] ?? 'custom'),
            'interest_rate' => $v['interest_rate'],
            'balance_cents' => Money::toCents($v['balance']),
            'principal_cents' => Money::toCents($v['principal'] ?? $v['balance']),
            'emi_cents' => Money::toCents($v['emi'] ?? 0),
            'total_emis' => $totalEmis,
            'emis_paid' => $emisPaid,
            'credit_limit_cents' => $isCard && isset($v['credit_limit']) ? Money::toCents($v['credit_limit']) : null,
            'min_due_cents' => $isCard && isset($v['min_due']) ? Money::toCents($v['min_due']) : null,
            'due_day' => $v['due_day'] ?? null,
            'statement_day' => $isCard ? ($v['statement_day'] ?? null) : null,
            'status' => 'active',
        ];
    }
}
