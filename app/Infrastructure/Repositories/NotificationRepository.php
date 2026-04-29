<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Notification\NotificationDto;
use App\Domain\Repositories\NotificationRepositoryInterface;
use Illuminate\Support\Facades\DB;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function findForNotifiable(string $type, int $id, bool $unreadOnly = false, int $perPage = 20): array
    {
        $query = DB::table('notifications')
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($row) => $this->toDto($row)->toArray())->all(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function markAsRead(string $notificationId): void
    {
        DB::table('notifications')
            ->where('id', $notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(string $notifiableType, int $notifiableId): void
    {
        DB::table('notifications')
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadCount(string $notifiableType, int $notifiableId): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->whereNull('read_at')
            ->count();
    }

    public function delete(string $notificationId): void
    {
        DB::table('notifications')->where('id', $notificationId)->delete();
    }

    private function toDto(object $row): NotificationDto
    {
        return new NotificationDto(
            id: $row->id,
            type: class_basename($row->type),
            data: json_decode($row->data, true),
            read_at: $row->read_at,
            created_at: $row->created_at,
        );
    }
}
