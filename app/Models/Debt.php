<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A loan or credit card the user owes money on.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $institution
 * @property string $kind loan|credit_card
 * @property string|null $category
 * @property int $principal_cents
 * @property int $balance_cents
 * @property float $interest_rate
 * @property int $emi_cents
 * @property int|null $total_emis
 * @property int $emis_paid
 * @property int|null $credit_limit_cents
 * @property int|null $min_due_cents
 * @property int|null $due_day
 * @property string $currency
 * @property string $status
 */
#[Fillable([
    'user_id', 'name', 'institution', 'kind', 'category', 'principal_cents',
    'balance_cents', 'interest_rate', 'emi_cents', 'total_emis', 'emis_paid',
    'credit_limit_cents', 'min_due_cents', 'due_day', 'currency', 'status',
    'opened_on', 'closed_at',
])]
class Debt extends Model
{
    protected function casts(): array
    {
        return [
            'principal_cents' => 'integer',
            'balance_cents' => 'integer',
            'interest_rate' => 'float',
            'emi_cents' => 'integer',
            'total_emis' => 'integer',
            'emis_paid' => 'integer',
            'credit_limit_cents' => 'integer',
            'min_due_cents' => 'integer',
            'due_day' => 'integer',
            'opened_on' => 'date',
            'closed_at' => 'date',
        ];
    }

    public function isCard(): bool
    {
        return $this->kind === 'credit_card';
    }

    /**
     * Loan repayment breakdown, derived live from the current balance and the
     * recorded EMI count. Amounts are in integer minor units (paise).
     *
     * @return array{
     *     total_emis: int|null, emis_paid: int, remaining_emis: int|null,
     *     amount_paid_cents: int, remaining_cents: int, progress: float
     * }
     */
    public function repayment(): array
    {
        $amountPaidCents = max(0, $this->principal_cents - $this->balance_cents);
        $total = $this->total_emis;
        $paid = $total !== null ? min($this->emis_paid, $total) : $this->emis_paid;

        $progress = $total
            ? round($paid / $total * 100, 1)
            : ($this->principal_cents > 0 ? round($amountPaidCents / $this->principal_cents * 100, 1) : 0.0);

        return [
            'total_emis' => $total,
            'emis_paid' => $paid,
            'remaining_emis' => $total !== null ? max(0, $total - $paid) : null,
            'amount_paid_cents' => $amountPaidCents,
            'remaining_cents' => $this->balance_cents,
            'progress' => min(100, $progress),
        ];
    }

    /**
     * Credit utilisation as a percentage (cards only), 0 when no limit.
     */
    public function utilisation(): float
    {
        if (! $this->credit_limit_cents) {
            return 0.0;
        }

        return round($this->balance_cents / $this->credit_limit_cents * 100, 1);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** @return HasMany<DebtDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(DebtDocument::class);
    }
}
