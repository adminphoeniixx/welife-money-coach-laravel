<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    /**
     * Record every state-changing admin request into the audit log.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Capture the acting admin before the request runs, since actions like
        // impersonation swap the authenticated user mid-request.
        $admin = $request->user();
        $mutating = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        $response = $next($request);

        if ($mutating && $admin && $response->getStatusCode() < 400) {
            [$subjectType, $subjectId] = $this->resolveSubject($request);

            AuditLog::create([
                'user_id' => $admin->id,
                'user_name' => $admin->name,
                'action' => $request->route()?->getName() ?? $request->path(),
                'description' => $request->method().' /'.ltrim($request->path(), '/'),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'ip_address' => $request->ip(),
            ]);
        }

        return $response;
    }

    /**
     * Pull the first bound Eloquent model from the route as the subject.
     *
     * @return array{0: string|null, 1: int|null}
     */
    private function resolveSubject(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return [class_basename($parameter), (int) $parameter->getKey()];
            }
        }

        return [null, null];
    }
}
