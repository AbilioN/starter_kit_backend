<?php

namespace App\Domain\Repositories;

interface NotificationRepositoryInterface
{
    public function findForNotifiable(string $type, int $id, bool $unreadOnly = false, int $perPage = 20): array;
    public function markAsRead(string $notificationId): void;
    public function markAllAsRead(string $notifiableType, int $notifiableId): void;
    public function unreadCount(string $notifiableType, int $notifiableId): int;
    public function delete(string $notificationId): void;
}
