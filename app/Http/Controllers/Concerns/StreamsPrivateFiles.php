<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
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
     * Decrypt a blob off the private disk and stream it.
     *
     * `streamDownload()` builds its own Content-Disposition from its fourth
     * argument, so the disposition has to be passed there — a header in the
     * array is overwritten and every "preview" comes back as a download.
     */
    private function streamPrivateFile(string $disk, string $path, string $mimeType, string $originalName, bool $inline, string $failureMessage): StreamedResponse
    {
        try {
            $contents = Crypt::decryptString(Storage::disk($disk)->get($path));
        } catch (DecryptException) {
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
