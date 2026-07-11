<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DataRequestController extends Controller
{
    /**
     * List privacy/compliance requests (data export & account deletion).
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'all');

        $requests = DataRequest::query()
            ->with('user:id,name')
            ->when(in_array($status, ['pending', 'completed', 'rejected'], true),
                fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DataRequest $r) => [
                'id' => $r->id,
                'user' => $r->user?->name,
                'user_email' => $r->user_email,
                'type' => $r->type,
                'status' => $r->status,
                'note' => $r->note,
                'created_at' => $r->created_at,
                'resolved_at' => $r->resolved_at,
            ]);

        return Inertia::render('admin/compliance/Index', [
            'requests' => $requests,
            'filters' => ['status' => $status],
            'stats' => [
                'pending' => DataRequest::where('status', 'pending')->count(),
                'export' => DataRequest::where('type', 'export')->count(),
                'deletion' => DataRequest::where('type', 'deletion')->count(),
            ],
        ]);
    }

    /**
     * Fulfil a request. A completed deletion request removes the account.
     */
    public function complete(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        if ($dataRequest->type === 'deletion' && $dataRequest->user) {
            $dataRequest->user->delete();
        }

        $dataRequest->update([
            'status' => 'completed',
            'resolved_by' => $request->user()->id,
            'resolved_at' => Carbon::now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $dataRequest->type === 'deletion'
                ? 'Deletion request completed and account removed.'
                : 'Export request marked as completed.',
        ]);

        return back();
    }

    /**
     * Reject a request with an optional note.
     */
    public function reject(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $dataRequest->update([
            'status' => 'rejected',
            'note' => $data['note'] ?? $dataRequest->note,
            'resolved_by' => $request->user()->id,
            'resolved_at' => Carbon::now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request rejected.']);

        return back();
    }
}
