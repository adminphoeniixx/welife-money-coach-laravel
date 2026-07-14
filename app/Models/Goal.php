<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A savings goal or emergency fund target.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type emergency_fund|savings
 * @property int $target_cents
 * @property int $saved_cents
 * @property string $currency
 * @property Carbon|null $target_date
 */
#[Fillable(['user_id', 'name', 'type', 'target_cents', 'saved_cents', 'currency', 'target_date'])]
class Goal extends Model
{
    protected function casts(): array
    {
        return [
            'target_cents' => 'integer',
            'saved_cents' => 'integer',
            'target_date' => 'date',
        ];
    }

    /**
     * Progress toward the goal as a percentage capped at 100.
     */
    public function progress(): float
    {
        if ($this->target_cents <= 0) {
            return 0.0;
        }

        return min(100, round($this->saved_cents / $this->target_cents * 100, 1));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
