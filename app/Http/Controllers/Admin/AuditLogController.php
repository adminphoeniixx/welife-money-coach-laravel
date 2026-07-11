<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Searchable, paginated feed of admin actions.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'like', "%{$search}%")
                        ->orWhere('user_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'actor' => $log->user?->name ?? $log->user_name ?? 'System',
                'action' => $log->action,
                'description' => $log->description,
                'subject' => $log->subject_type ? "{$log->subject_type} #{$log->subject_id}" : null,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
            ]);

        return Inertia::render('admin/audit/Index', [
            'logs' => $logs,
            'filters' => ['search' => $search],
        ]);
    }
}
