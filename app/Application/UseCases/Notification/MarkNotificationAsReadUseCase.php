<?php

namespace App\Application\UseCases\Notification;

use App\Domain\Repositories\NotificationRepositoryInterface;

class MarkNotificationAsReadUseCase
{
    public function __construct(private NotificationRepositoryInterface $notificationRepository) {}

    public function execute(string $notificationId): void
    {
        $this->notificationRepository->markAsRead($notificationId);
    }

    public function markAll(string $notifiableType, int $notifiableId): void
    {
        $this->notificationRepository->markAllAsRead($notifiableType, $notifiableId);
    }
}
