<?php

namespace App\Models;

use App\Concerns\StampsUserCurrency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string $repeat none|weekly|monthly|yearly
 * @property Carbon|null $repeat_until
 */
#[Fillable([
    'user_id', 'household_id', 'type', 'category', 'amount_cents', 'currency',
    'description', 'payee', 'method', 'occurred_on', 'repeat', 'repeat_until',
])]
class Entry extends Model
{
    use StampsUserCurrency;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'occurred_on' => 'date',
            'repeat_until' => 'date',
        ];
    }

    /** Whether this entry repeats on a schedule. */
    public function repeats(): bool
    {
        return ! in_array($this->repeat, ['none', 'one_time', '', null], true);
    }

    /**
     * The next occurrence after the given date while the schedule is still
     * running, or null when the entry does not repeat / has expired.
     */
    public function nextOccurrenceAfter(Carbon $from): ?Carbon
    {
        if (! $this->repeats()) {
            return null;
        }

        $cursor = $this->occurred_on->copy();
        $guard = 0;

        while ($cursor->lte($from) && $guard++ < 600) {
            $cursor = match ($this->repeat) {
                'weekly' => $cursor->addWeek(),
                'yearly' => $cursor->addYear(),
                default => $cursor->addMonthNoOverflow(),
            };
        }

        if ($this->repeat_until !== null && $cursor->gt($this->repeat_until)) {
            return null;
        }

        return $cursor->lte($from) ? null : $cursor;
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

    /** @return HasMany<EntryAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(EntryAttachment::class);
    }
}
