<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A time-boxed savings challenge the user has joined.
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $title
 * @property string|null $description
 * @property int $target_cents
 * @property int $progress_cents
 * @property string $status
 * @property Carbon $started_on
 * @property Carbon $ends_on
 */
#[Fillable([
    'user_id', 'key', 'title', 'description', 'target_cents',
    'progress_cents', 'status', 'started_on', 'ends_on',
])]
class Challenge extends Model
{
    /**
     * Preset challenges the user can join, keyed by slug.
     *
     * @var array<string, array{title:string, description:string, target:int}>
     */
    public const PRESETS = [
        'save_5000' => ['title' => 'Save ₹5,000 this month', 'description' => 'Put ₹5,000 aside before the month ends.', 'target' => 500000],
        'save_10000' => ['title' => 'Save ₹10,000 this month', 'description' => 'A bigger push — ₹10,000 into savings.', 'target' => 1000000],
        'no_spend_7' => ['title' => 'No unnecessary spending for 7 days', 'description' => 'Avoid non-essential spending for a full week.', 'target' => 700],
        'cut_fuel_10' => ['title' => 'Cut fuel spending by 10%', 'description' => 'Trim your transport costs this month.', 'target' => 100000],
        'cut_dining_3000' => ['title' => 'Trim dining by ₹3,000', 'description' => 'Cook more, order less — save ₹3,000 on food.', 'target' => 300000],
    ];

    protected function casts(): array
    {
        return [
            'target_cents' => 'integer',
            'progress_cents' => 'integer',
            'started_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function progress(): float
    {
        if ($this->target_cents <= 0) {
            return 0.0;
        }

        return min(100, round($this->progress_cents / $this->target_cents * 100, 1));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
