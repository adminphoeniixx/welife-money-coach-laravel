<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Proof of payment (receipt photo / PDF) attached to an income or expense
 * entry, encrypted at rest on the private disk — same scheme as
 * {@see DebtDocument}.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $user_id
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $path
 */
#[Fillable([
    'entry_id', 'user_id', 'original_name', 'mime_type', 'size_bytes', 'path',
])]
class EntryAttachment extends Model
{
    /** Private disk holding the encrypted blobs. */
    public const DISK = 'local';

    /** Accepted proof types + max size (photos and PDFs). */
    public const RULES = ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Encrypt an uploaded proof file and attach it to an entry.
     */
    public static function storeFor(Entry $entry, UploadedFile $file): self
    {
        $contents = $file->get();
        abort_if($contents === false, 422, 'The uploaded file could not be read.');

        $path = 'entry-attachments/'.$entry->user_id.'/'.Str::uuid()->toString().'.enc';
        Storage::disk(self::DISK)->put($path, Crypt::encryptString($contents));

        return $entry->attachments()->create([
            'user_id' => $entry->user_id,
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
     * The shape the app reads for a proof attachment. `url` and `view_url` are
     * the same inline endpoint — both keys exist so either client naming works.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'entry_id' => $this->entry_id,
            'name' => $this->original_name,
            'file_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size_bytes,
            'is_image' => $this->isImage(),
            'url' => url('/api/entry-attachments/'.$this->id.'/view'),
            'view_url' => url('/api/entry-attachments/'.$this->id.'/view'),
            'download_url' => url('/api/entry-attachments/'.$this->id.'/download'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
