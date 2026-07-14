<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single encrypted file in a user's Secure Documents Vault.
 *
 * @property int $id
 * @property int $user_id
 * @property string $category
 * @property string $title
 * @property string|null $side
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $path
 * @property string|null $notes
 */
#[Fillable([
    'user_id', 'category', 'title', 'side', 'original_name',
    'mime_type', 'size_bytes', 'path', 'notes',
])]
class Document extends Model
{
    /**
     * Vault categories, in display order. Keys are stored; values are labels.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'debit_atm_card' => 'Bank Debit / ATM Card',
        'credit_card' => 'Credit Card',
        'aadhaar' => 'Aadhaar Card',
        'pan' => 'PAN Card',
        'driving_license' => 'Driving License',
        'passport' => 'Passport',
        'voter_id' => 'Voter ID',
        'insurance' => 'Insurance Document',
        'vehicle_rc' => 'Vehicle RC Book',
        'loan' => 'Loan Document',
        'property' => 'Property Document',
        'medical' => 'Medical Report',
        'passport_photo' => 'Passport-size Photo',
        'other' => 'Other Document',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Document';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
