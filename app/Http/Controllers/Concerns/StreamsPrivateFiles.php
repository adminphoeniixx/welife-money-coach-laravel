<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared plumbing for the encrypted-at-rest file endpoints (proof
 * attachments, debt documents, vault documents): decrypt the blob and hand it
 * back with the right disposition, plus the signed links the mobile app can
 * fetch without an Authorization header.
 */
trait StreamsPrivateFiles
{
    /**
     * How long a signed file link stays valid. Long enough for the app to
     * render a list and open anything on it, short enough that a link that
     * leaks out of a screenshot or log goes stale.
     */
    public static function signedLinkLifetime(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * A link the app (or a plain `Image.network`) can fetch with no token —
     * the signature carries the authorisation instead.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function signedFileUrl(string $route, array $parameters): string
    {
        return URL::temporarySignedRoute($route, self::signedLinkLifetime(), $parameters);
    }

    /**
     * Read an encrypted blob off the private disk, or 404 if it is gone.
     *
     * The private disk is configured with `throw => false`, so a file that is
     * no longer on disk comes back as null and reaches `decryptString()` as an
     * empty payload — which raises DecryptException and gets reported as
     * "could not be decrypted", pointing at the wrong thing entirely. A blob
     * that has disappeared (an ephemeral container filesystem being the usual
     * reason) is a 404, so rule that out before decrypting.
     */
    private function readPrivateFile(string $disk, string $path): string
    {
        $payload = Storage::disk($disk)->get($path);

        if ($payload === null || $payload === '') {
            Log::warning('Private file missing from disk.', [
                'disk' => $disk,
                'path' => $path,
            ]);

            abort(404, 'This file is no longer available on the server.');
        }

        return $payload;
    }

    /**
     * Decrypt a blob off the private disk and stream it.
     *
     * `streamDownload()` builds its own Content-Disposition from its fourth
     * argument, so the disposition has to be passed there — a header in the
     * array is overwritten and every "preview" comes back as a download.
     */
    private function streamPrivateFile(string $disk, string $path, string $mimeType, string $originalName, bool $inline, string $failureMessage): StreamedResponse
    {
        $payload = $this->readPrivateFile($disk, $path);

        try {
            $contents = Crypt::decryptString($payload);
        } catch (DecryptException $e) {
            // A blob that is present but undecryptable means the key that
            // encrypted it is not the key in use now (APP_KEY rotated), or the
            // blob is truncated. Log which, so it is not guesswork later.
            Log::error('Private file could not be decrypted.', [
                'disk' => $disk,
                'path' => $path,
                'bytes' => strlen($payload),
                'reason' => $e->getMessage(),
            ]);

            abort(500, $failureMessage);
        }

        $filename = str_replace('"', '', $originalName);

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'no-store, private',
        ], $inline ? 'inline' : 'attachment');
    }
}
