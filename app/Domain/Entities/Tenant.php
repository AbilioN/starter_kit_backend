<?php

namespace App\Domain\Entities;

use DateTime;

class Tenant
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $subdomain,
        public readonly string $databaseName,
        public readonly ?string $subscriptionPlanId,
        public readonly ?string $themePrimaryColor,
        public readonly ?string $themeSecondaryColor,
        public readonly ?string $logoPath,
        public readonly string $status, // pending|active|suspended
        public readonly string $createdVia, // godadmin|self_service
        public readonly ?string $broadcastingProviderId = null,
        public readonly ?string $storageProviderId = null,
        public readonly ?string $aiProviderId = null,
        public readonly ?string $backupProviderId = null,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null
    ) {}

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
