<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationRead;
use App\Services\InsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InsightController extends Controller
{
    /**
     * Achievement / milestone wall. (achievements screen)
     */
    public function achievements(Request $request, InsightService $insights): JsonResponse
    {
        $items = $insights->achievements($request->user());

        return response()->json([
            'achievements' => $items,
            'earned' => collect($items)->where('earned', true)->count(),
            'total' => count($items),
        ]);
    }

    /**
     * Smart notifications centre. (notifications screen)
     */
    public function notifications(Request $request, InsightService $insights): JsonResponse
    {
        $items = $insights->notifications($request->user());

        return response()->json([
            'notifications' => $items,
            'total' => count($items),
            'unread' => count(array_filter($items, fn (array $n) => ! $n['read'])),
        ]);
    }

    /**
     * Mark one notification read. The id is the notification's stable key from
     * the feed, so this is safe to call before the situation changes.
     */
    public function markRead(Request $request, string $notification, InsightService $insights): JsonResponse
    {
        $this->recordRead($request, [$notification]);

        return $this->notifications($request, $insights);
    }

    /**
     * Mark the whole current feed read — clears the home red dot.
     */
    public function markAllRead(Request $request, InsightService $insights): JsonResponse
    {
        $this->recordRead(
            $request,
            array_column($insights->notifications($request->user()), 'id'),
        );

        return $this->notifications($request, $insights);
    }

    /**
     * @param  list<string>  $keys
     */
    private function recordRead(Request $request, array $keys): void
    {
        foreach (array_unique($keys) as $key) {
            if ($key === '') {
                continue;
            }

            NotificationRead::updateOrCreate(
                ['user_id' => $request->user()->id, 'key' => $key],
                ['read_at' => Carbon::now()],
            );
        }
    }
}
