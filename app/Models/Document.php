<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use function App\Support\signed_file_url;

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

    /**
     * The shape the vault screens read. `url` / `view_url` are signed,
     * token-free links so the app can render a stored ID card without
     * building an authenticated request — they are minted only on responses
     * that already passed the PIN gate, and they expire.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        $view = signed_file_url('api.files.vault-document', ['document' => $this->id]);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'side' => $this->side,
            'is_image' => $this->isImage(),
            'name' => $this->original_name,
            'file_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size_bytes,
            'size_bytes' => $this->size_bytes,
            'size_label' => self::humanSize($this->size_bytes),
            'notes' => $this->notes,
            'url' => $view,
            'view_url' => $view,
            'download_url' => signed_file_url('api.files.vault-document', [
                'document' => $this->id,
                'download' => 1,
            ]),
            'authenticated_view_url' => url('/api/vault/documents/'.$this->id.'/view'),
            'created_at' => $this->created_at?->toIso8601String(),
            'uploaded_at' => $this->created_at?->format('d M Y'),
        ];
    }

    /** Byte count as the short string the vault list shows under a title. */
    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
