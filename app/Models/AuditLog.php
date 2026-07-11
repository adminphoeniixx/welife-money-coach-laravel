<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string $action
 * @property string|null $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $ip_address
 */
#[Fillable(['user_id', 'user_name', 'action', 'description', 'subject_type', 'subject_id', 'ip_address'])]
class AuditLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
