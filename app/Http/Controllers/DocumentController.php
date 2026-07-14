<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /** Private disk holding the encrypted document blobs. */
    private const DISK = 'local';

    /**
     * Upload and securely store a new document (encrypted at rest).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(fileRequired: true));

        $file = $request->file('file');
        $path = 'documents/'.$request->user()->id.'/'.Str::uuid()->toString().'.enc';

        Storage::disk(self::DISK)->put($path, Crypt::encryptString($this->read($file)));

        $request->user()->documents()->create([
            'category' => $validated['category'],
            'title' => $validated['title'],
            'side' => $validated['side'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'path' => $path,
            'notes' => $validated['notes'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document added to your vault.']);

        return back();
    }

    /**
     * Stream a decrypted document inline (for viewing in the browser).
     */
    public function view(Request $request, Document $document): Response
    {
        $this->authorizeOwner($request, $document);

        return $this->stream($document, inline: true);
    }

    /**
     * Download a decrypted document as an attachment.
     */
    public function download(Request $request, Document $document): Response
    {
        $this->authorizeOwner($request, $document);

        return $this->stream($document, inline: false);
    }

    /**
     * Update a document's metadata, optionally replacing the file.
     */
    public function update(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeOwner($request, $document);

        $validated = $request->validate($this->rules(fileRequired: false));

        $attributes = [
            'category' => $validated['category'],
            'title' => $validated['title'],
            'side' => $validated['side'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = 'documents/'.$request->user()->id.'/'.Str::uuid()->toString().'.enc';
            Storage::disk(self::DISK)->put($path, Crypt::encryptString($this->read($file)));
            Storage::disk(self::DISK)->delete($document->path);

            $attributes += [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'path' => $path,
            ];
        }

        $document->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document updated.']);

        return back();
    }

    /**
     * Permanently remove a document and its encrypted blob.
     */
    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeOwner($request, $document);

        Storage::disk(self::DISK)->delete($document->path);
        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document deleted from your vault.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $fileRequired): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'side' => ['nullable', Rule::in(['front', 'back'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => [
                $fileRequired ? 'required' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:8192',
            ],
        ];
    }

    private function authorizeOwner(Request $request, Document $document): void
    {
        abort_unless($document->user_id === $request->user()->id, 403);
    }

    /**
     * Read an uploaded file's bytes, failing loudly if it can't be read.
     */
    private function read(UploadedFile $file): string
    {
        $contents = $file->get();

        abort_if($contents === false, 422, 'The uploaded file could not be read.');

        return $contents;
    }

    private function stream(Document $document, bool $inline): StreamedResponse
    {
        try {
            $contents = Crypt::decryptString(Storage::disk(self::DISK)->get($document->path));
        } catch (DecryptException) {
            abort(500, 'This document could not be decrypted.');
        }

        $disposition = $inline ? 'inline' : 'attachment';
        $filename = str_replace('"', '', $document->original_name);

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
