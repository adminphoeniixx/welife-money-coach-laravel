<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\StreamsPrivateFiles;
use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\EntryAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Proof-of-payment files on income / expense entries: attach after the fact,
 * preview, download, and remove.
 */
class EntryAttachmentController extends Controller
{
    use StreamsPrivateFiles;

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
     * The token-free route behind `url` / `view_url`: a signed link the app can
     * hand straight to an image widget or a browser. The signature is what
     * authorises it, so there is no owner check to run here — the link was
     * minted for this attachment and expires on its own.
     */
    public function signedView(Request $request, EntryAttachment $attachment): Response
    {
        return $this->stream($attachment, inline: ! $request->boolean('download'));
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
        return $this->streamPrivateFile(
            EntryAttachment::DISK,
            $attachment->path,
            $attachment->mime_type,
            $attachment->original_name,
            $inline,
            'This attachment could not be decrypted.',
        );
    }
}
