<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monthly spending limit for a category.
 *
 * @property int $id
 * @property int $user_id
 * @property string $category
 * @property int $limit_cents
 * @property string $currency
 */
#[Fillable(['user_id', 'household_id', 'category', 'limit_cents', 'currency'])]
class Budget extends Model
{
    protected function casts(): array
    {
        return [
            'limit_cents' => 'integer',
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
