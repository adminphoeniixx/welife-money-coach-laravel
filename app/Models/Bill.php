<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recurring bill, subscription or EMI reminder.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $debt_id
 * @property string $name
 * @property string $kind bill|subscription|emi
 * @property string|null $category
 * @property int $amount_cents
 * @property string $currency
 * @property Carbon $due_date
 * @property string $repeat
 * @property int $remind_days_before
 * @property string $status
 * @property Carbon|null $paid_on
 */
#[Fillable([
    'user_id', 'debt_id', 'name', 'kind', 'category', 'amount_cents', 'currency',
    'due_date', 'repeat', 'remind_days_before', 'status', 'paid_on',
])]
class Bill extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'due_date' => 'date',
            'remind_days_before' => 'integer',
            'paid_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }
}
