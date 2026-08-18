<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\StreamsPrivateFiles;
use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\DebtDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtDocumentController extends Controller
{
    use StreamsPrivateFiles;

    /** Accepted attachment types + size (photos and PDFs). */
    private const RULES = ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'];

    /**
     * Attach one or more photos / documents to an existing loan or card.
     */
    public function store(Request $request, Debt $debt): JsonResponse
    {
        abort_unless($debt->user_id === $request->user()->id, 403);

        $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['required', ...self::RULES],
        ]);

        foreach ($request->file('documents', []) as $file) {
            DebtDocument::storeFor($debt, $file);
        }

        return response()->json([
            'message' => 'Document attached.',
            'documents' => $debt->fresh(['documents'])->documents
                ->map(fn (DebtDocument $doc) => $doc->toApi())->values(),
        ], 201);
    }

    /**
     * Stream a decrypted attachment inline (for viewing).
     */
    public function view(Request $request, DebtDocument $document): Response
    {
        $this->authorizeOwner($request, $document);

        return $this->stream($document, inline: true);
    }

    /**
     * Download a decrypted attachment.
     */
    public function download(Request $request, DebtDocument $document): Response
    {
        $this->authorizeOwner($request, $document);

        return $this->stream($document, inline: false);
    }

    /**
     * The token-free route behind `url` / `view_url`; the URL signature is
     * what authorises it.
     */
    public function signedView(Request $request, DebtDocument $document): Response
    {
        return $this->stream($document, inline: ! $request->boolean('download'));
    }

    public function destroy(Request $request, DebtDocument $document): JsonResponse
    {
        $this->authorizeOwner($request, $document);

        $deletedId = $document->id;
        Storage::disk(DebtDocument::DISK)->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Document removed.', 'deleted_id' => $deletedId]);
    }

    private function authorizeOwner(Request $request, DebtDocument $document): void
    {
        abort_unless($document->user_id === $request->user()->id, 403);
    }

    private function stream(DebtDocument $document, bool $inline): StreamedResponse
    {
        return $this->streamPrivateFile(
            DebtDocument::DISK,
            $document->path,
            $document->mime_type,
            $document->original_name,
            $inline,
            'This document could not be decrypted.',
        );
    }
}
