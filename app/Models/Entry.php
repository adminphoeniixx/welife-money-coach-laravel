<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single income or expense entry in the user's ledger.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type income|expense
 * @property string|null $category
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $description
 * @property string|null $payee
 * @property string|null $method
 * @property Carbon $occurred_on
 */
#[Fillable([
    'user_id', 'household_id', 'type', 'category', 'amount_cents', 'currency',
    'description', 'payee', 'method', 'occurred_on',
])]
class Entry extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'occurred_on' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
