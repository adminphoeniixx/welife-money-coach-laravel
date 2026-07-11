<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $subscription_id
 * @property int|null $plan_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $status
 * @property string|null $reference
 */
#[Fillable(['user_id', 'subscription_id', 'plan_id', 'amount_cents', 'currency', 'status', 'reference', 'paid_at'])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
