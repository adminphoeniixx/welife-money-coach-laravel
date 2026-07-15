<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\DebtDocument;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtDocumentController extends Controller
{
    /** Accepted attachment types + size (photos and PDFs). */
    private const RULES = ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'];

    /**
     * Attach one or more photos / documents to an existing loan or card.
     */
    public function store(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->user_id === $request->user()->id, 403);

        $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['required', ...self::RULES],
        ]);

        foreach ($request->file('documents', []) as $file) {
            DebtDocument::storeFor($debt, $file);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document attached.']);

        return back();
    }

    /**
     * Stream a decrypted attachment inline (for viewing in the browser).
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
     * Permanently remove an attachment and its encrypted blob.
     */
    public function destroy(Request $request, DebtDocument $document): RedirectResponse
    {
        $this->authorizeOwner($request, $document);

        Storage::disk(DebtDocument::DISK)->delete($document->path);
        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document removed.']);

        return back();
    }

    private function authorizeOwner(Request $request, DebtDocument $document): void
    {
        abort_unless($document->user_id === $request->user()->id, 403);
    }

    private function stream(DebtDocument $document, bool $inline): StreamedResponse
    {
        try {
            $contents = Crypt::decryptString(Storage::disk(DebtDocument::DISK)->get($document->path));
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
