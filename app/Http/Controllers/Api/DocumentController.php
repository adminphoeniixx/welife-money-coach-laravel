<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\StreamsPrivateFiles;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use StreamsPrivateFiles;

    /** Private disk holding the encrypted document blobs. */
    private const DISK = 'local';

    /**
     * Upload and securely store a new document (encrypted at rest).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules(fileRequired: true));

        $file = $request->file('file');
        $path = 'documents/'.$request->user()->id.'/'.Str::uuid()->toString().'.enc';

        Storage::disk(self::DISK)->put($path, Crypt::encryptString($this->read($file)));

        $document = $request->user()->documents()->create([
            'category' => $validated['category'],
            'title' => $validated['title'],
            'side' => $validated['side'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'path' => $path,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Document added to your vault.',
            'document' => $this->present($document),
        ], 201);
    }

    /**
     * Stream a decrypted document inline (for viewing).
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
     * The token-free route behind `url` / `view_url`. Links are only minted
     * onto responses that already cleared the vault PIN gate, and they expire.
     */
    public function signedView(Request $request, Document $document): Response
    {
        return $this->stream($document, inline: ! $request->boolean('download'));
    }

    /**
     * Update a document's metadata, optionally replacing the file.
     */
    public function update(Request $request, Document $document): JsonResponse
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

        return response()->json([
            'message' => 'Document updated.',
            'document' => $this->present($document->fresh()),
        ]);
    }

    /**
     * Permanently remove a document and its encrypted blob.
     */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->authorizeOwner($request, $document);

        $deletedId = $document->id;
        Storage::disk(self::DISK)->delete($document->path);
        $document->delete();

        return response()->json([
            'message' => 'Document deleted from your vault.',
            'deleted_id' => $deletedId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Document $doc): array
    {
        return $doc->toApi();
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

    private function read(UploadedFile $file): string
    {
        $contents = $file->get();

        abort_if($contents === false, 422, 'The uploaded file could not be read.');

        return $contents;
    }

    private function stream(Document $document, bool $inline): StreamedResponse
    {
        return $this->streamPrivateFile(
            self::DISK,
            $document->path,
            $document->mime_type,
            $document->original_name,
            $inline,
            'This document could not be decrypted.',
        );
    }
}
