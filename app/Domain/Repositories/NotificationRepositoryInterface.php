<?php

namespace App\Domain\Repositories;

interface NotificationRepositoryInterface
{
    public function findForNotifiable(string $type, string $id, bool $unreadOnly = false, int $perPage = 20): array;
    public function markAsRead(string $notificationId): void;
    public function markAllAsRead(string $notifiableType, string $notifiableId): void;
    public function unreadCount(string $notifiableType, string $notifiableId): int;
    public function delete(string $notificationId): void;
}
