<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $user_email
 * @property string $type
 * @property string $status
 * @property string|null $note
 * @property int|null $resolved_by
 */
#[Fillable(['user_id', 'user_email', 'type', 'status', 'note', 'resolved_by', 'resolved_at'])]
class DataRequest extends Model
{
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
