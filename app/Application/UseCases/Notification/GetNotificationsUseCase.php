<?php

namespace App\Application\UseCases\Notification;

use App\Domain\Repositories\NotificationRepositoryInterface;

class GetNotificationsUseCase
{
    public function __construct(private NotificationRepositoryInterface $notificationRepository) {}

    public function execute(string $notifiableType, int $notifiableId, bool $unreadOnly = false, int $perPage = 20): array
    {
        return $this->notificationRepository->findForNotifiable($notifiableType, $notifiableId, $unreadOnly, $perPage);
    }

    public function unreadCount(string $notifiableType, int $notifiableId): int
    {
        return $this->notificationRepository->unreadCount($notifiableType, $notifiableId);
    }
}
