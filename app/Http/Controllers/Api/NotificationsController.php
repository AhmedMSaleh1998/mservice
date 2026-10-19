<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Notifications\Resources\UserNotificationResource;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $notifications = $request->user()
            ->userNotifications()
            ->with('adminNotification')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Notifications loaded successfully.',
            'status' => 200,
            'data' => [
                'items' => UserNotificationResource::collection($notifications->items()),
                'unread_count' => $this->unreadCount($request),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ],
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 200,
            'data' => ['unread_count' => $this->unreadCount($request)],
        ]);
    }

    public function read(Request $request, int $notification): JsonResponse
    {
        $userNotification = $request->user()
            ->userNotifications()
            ->where('admin_notification_id', $notification)
            ->firstOrFail();

        if ($userNotification->read_at === null) {
            $userNotification->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => __('Notification marked as read.'),
            'status' => 200,
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->userNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'message' => __('All notifications marked as read.'),
            'status' => 200,
        ]);
    }

    private function unreadCount(Request $request): int
    {
        return $request->user()->userNotifications()->whereNull('read_at')->count();
    }
}
