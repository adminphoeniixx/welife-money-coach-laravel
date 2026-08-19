<?php

namespace App\Models;

use App\Concerns\StampsUserCurrency;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
    use StampsUserCurrency;

    /** The emergency fund type key the app matches on. */
    public const TYPE_EMERGENCY_FUND = 'emergency_fund';

    protected function casts(): array
    {
        return [
            'target_cents' => 'integer',
            'saved_cents' => 'integer',
            'target_date' => 'date',
        ];
    }

    /**
     * `type` always reads and writes as a canonical key.
     *
     * The column is a plain string with no enum constraint, so a row written
     * before the `Rule::in` validation landed can hold "Emergency Fund" or
     * "emergency-fund". The app matches the emergency fund by an exact
     * `emergency_fund` comparison, so fold those spellings here rather than
     * leaving every reader to guess.
     *
     * @return Attribute<string, string>
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => self::normaliseType($value),
            set: fn (?string $value) => self::normaliseType($value),
        );
    }

    /**
     * Fold a loosely-written type onto its canonical key. A value that is not
     * a known type is left as it is — better an unrecognised label than a
     * confidently wrong one.
     */
    public static function normaliseType(?string $value): string
    {
        $raw = trim((string) $value);
        $key = str_replace([' ', '-'], '_', strtolower($raw));

        return in_array($key, Options::goalTypeKeys(), true) ? $key : $raw;
    }

    public function isEmergencyFund(): bool
    {
        return $this->type === self::TYPE_EMERGENCY_FUND;
    }

    /**
     * The one goal shape every endpoint returns — planning, goal mutations and
     * the coach payload. `id` is what `POST /goals/{id}/contribute` needs, so
     * it is never omitted.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'target' => Money::toRupees($this->target_cents),
            'saved' => Money::toRupees($this->saved_cents),
            'progress' => $this->progress(),
            'target_date' => $this->target_date?->format('Y-m-d'),
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
