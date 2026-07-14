<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending invitation to join a household, shared as a link/token.
 *
 * @property int $id
 * @property int $household_id
 * @property int $invited_by
 * @property string $email
 * @property string $role
 * @property string $token
 * @property Carbon|null $accepted_at
 */
#[Fillable(['household_id', 'invited_by', 'email', 'role', 'token', 'accepted_at'])]
class HouseholdInvitation extends Model
{
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
