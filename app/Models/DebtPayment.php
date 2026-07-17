<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single recorded payment against a {@see Debt}.
 *
 * @property int $id
 * @property int $debt_id
 * @property int $user_id
 * @property int $amount_cents
 * @property int $balance_after_cents
 * @property int|null $emi_number
 * @property Carbon $paid_on
 */
#[Fillable(['debt_id', 'user_id', 'amount_cents', 'balance_after_cents', 'emi_number', 'paid_on'])]
class DebtPayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'emi_number' => 'integer',
            'paid_on' => 'date',
        ];
    }

    /** @return BelongsTo<Debt, $this> */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }
}
