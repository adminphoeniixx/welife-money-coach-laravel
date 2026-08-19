<?php

namespace App\Models;

use App\Concerns\StampsUserCurrency;
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
    use StampsUserCurrency;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'due_date' => 'date',
            'remind_days_before' => 'integer',
            'paid_on' => 'date',
        ];
    }

    /**
     * What this reminder costs per month, normalised across repeat cycles.
     *
     * A yearly subscription is not a monthly cost of its full price, and a
     * weekly one bills about 4.33 times a month — summing `amount_cents` as-is
     * (which is what `subscription_monthly` used to do) overstates the first
     * and understates the second. A reminder that does not recur has no
     * monthly cost at all.
     */
    public function monthlyCostCents(): int
    {
        return match ($this->repeat) {
            'weekly' => (int) round($this->amount_cents * 52 / 12),
            'monthly' => $this->amount_cents,
            'yearly' => (int) round($this->amount_cents / 12),
            default => 0,
        };
    }

    public function isSubscription(): bool
    {
        return $this->kind === 'subscription';
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
