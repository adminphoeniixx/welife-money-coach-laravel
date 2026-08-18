<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\EntryAttachment;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Proof-of-payment files on income / expense entries: attach after the fact,
 * preview, download, and remove.
 */
class EntryAttachmentController extends Controller
{
    /**
     * Attach one or more proof files to an existing transaction.
     */
    public function store(Request $request, Entry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => ['required', ...EntryAttachment::RULES],
        ]);

        foreach ($request->file('attachments', []) as $file) {
            EntryAttachment::storeFor($entry, $file);
        }

        return response()->json([
            'message' => 'Proof attached.',
            'attachments' => $entry->fresh(['attachments'])->attachments
                ->map(fn (EntryAttachment $a) => $a->toApi())->values(),
        ], 201);
    }

    /**
     * Stream a decrypted proof inline (preview).
     */
    public function view(Request $request, EntryAttachment $attachment): Response
    {
        $this->authorizeOwner($request, $attachment);

        return $this->stream($attachment, inline: true);
    }

    /**
     * Download a decrypted proof.
     */
    public function download(Request $request, EntryAttachment $attachment): Response
    {
        $this->authorizeOwner($request, $attachment);

        return $this->stream($attachment, inline: false);
    }

    /**
     * `DELETE /entries/{entry}/attachments/{attachment}` — the entry in the
     * path is only used to confirm the attachment belongs to it.
     */
    public function destroyForEntry(Request $request, Entry $entry, EntryAttachment $attachment): JsonResponse
    {
        abort_if($attachment->entry_id !== $entry->id, 404);

        return $this->destroy($request, $attachment);
    }

    /**
     * `DELETE /entry-attachments/{attachment}` — remove a proof file.
     */
    public function destroy(Request $request, EntryAttachment $attachment): JsonResponse
    {
        $this->authorizeOwner($request, $attachment);

        $deletedId = $attachment->id;
        $entryId = $attachment->entry_id;
        self::purge($attachment);

        return response()->json([
            'message' => 'Proof removed.',
            'deleted_id' => $deletedId,
            'entry_id' => $entryId,
        ]);
    }

    /**
     * Delete an attachment row together with its encrypted blob.
     */
    public static function purge(EntryAttachment $attachment): void
    {
        Storage::disk(EntryAttachment::DISK)->delete($attachment->path);
        $attachment->delete();
    }

    private function authorizeOwner(Request $request, EntryAttachment $attachment): void
    {
        abort_unless($attachment->user_id === $request->user()->id, 403);
    }

    private function stream(EntryAttachment $attachment, bool $inline): StreamedResponse
    {
        try {
            $contents = Crypt::decryptString(Storage::disk(EntryAttachment::DISK)->get($attachment->path));
        } catch (DecryptException) {
            abort(500, 'This attachment could not be decrypted.');
        }

        $disposition = $inline ? 'inline' : 'attachment';
        $filename = str_replace('"', '', $attachment->original_name);

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
