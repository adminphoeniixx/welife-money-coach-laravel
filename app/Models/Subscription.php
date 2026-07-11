<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $plan_id
 * @property string $status
 * @property int $price_cents
 * @property string $currency
 * @property string $interval
 */
#[Fillable(['user_id', 'plan_id', 'status', 'price_cents', 'currency', 'interval', 'started_at', 'ends_at', 'cancelled_at'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Normalise the subscription price to a monthly amount (for MRR).
     */
    public function monthlyCents(): int
    {
        return match ($this->interval) {
            'year' => (int) round($this->price_cents / 12),
            'lifetime' => 0,
            default => $this->price_cents,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
