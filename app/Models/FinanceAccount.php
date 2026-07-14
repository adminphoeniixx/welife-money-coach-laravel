<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-owned asset (bank balance, cash, gold, FD, mutual funds, stocks, property).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property int $balance_cents
 * @property string $currency
 * @property string|null $note
 */
#[Fillable(['user_id', 'name', 'type', 'balance_cents', 'currency', 'note'])]
class FinanceAccount extends Model
{
    protected $table = 'finance_accounts';

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
