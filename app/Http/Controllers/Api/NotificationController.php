<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\Notification\GetNotificationsUseCase;
use App\Application\UseCases\Notification\MarkNotificationAsReadUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private GetNotificationsUseCase $getNotifications,
        private MarkNotificationAsReadUseCase $markAsRead,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $unreadOnly = $request->boolean('unread_only', false);
            $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

            $result = $this->getNotifications->execute(
                'App\Models\User',
                $user->id,
                $unreadOnly,
                $perPage
            );

            return response()->json(['success' => true, ...$result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $count = $this->getNotifications->unreadCount('App\Models\User', $user->id);

            return response()->json(['success' => true, 'count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function markRead(string $id, Request $request): JsonResponse
    {
        try {
            $this->markAsRead->execute($id);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $this->markAsRead->markAll('App\Models\User', $user->id);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
