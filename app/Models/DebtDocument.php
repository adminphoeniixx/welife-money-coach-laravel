<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function App\Support\signed_file_url;

/**
 * A photo or document attached to a loan / credit card, encrypted at rest.
 *
 * @property int $id
 * @property int $debt_id
 * @property int $user_id
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $path
 */
#[Fillable([
    'debt_id', 'user_id', 'original_name', 'mime_type', 'size_bytes', 'path',
])]
class DebtDocument extends Model
{
    /** Private disk holding the encrypted document blobs. */
    public const DISK = 'local';

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Encrypt an uploaded file and attach it to a debt.
     */
    public static function storeFor(Debt $debt, UploadedFile $file): self
    {
        $contents = $file->get();
        abort_if($contents === false, 422, 'The uploaded file could not be read.');

        $path = 'debt-documents/'.$debt->user_id.'/'.Str::uuid()->toString().'.enc';
        Storage::disk(self::DISK)->put($path, Crypt::encryptString($contents));

        return $debt->documents()->create([
            'user_id' => $debt->user_id,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'path' => $path,
        ]);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * The shape the app reads for a loan / card document. `url` and
     * `view_url` are the same signed, token-free link — see
     * {@see EntryAttachment::toApi()} for how the signing works.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        $view = signed_file_url('api.files.debt-document', ['document' => $this->id]);

        return [
            'id' => $this->id,
            'debt_id' => $this->debt_id,
            'name' => $this->original_name,
            'file_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size_bytes,
            'size_bytes' => $this->size_bytes,
            'is_image' => $this->isImage(),
            'url' => $view,
            'view_url' => $view,
            'download_url' => signed_file_url('api.files.debt-document', [
                'document' => $this->id,
                'download' => 1,
            ]),
            'authenticated_view_url' => url('/api/debt-documents/'.$this->id.'/view'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return BelongsTo<Debt, $this> */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
