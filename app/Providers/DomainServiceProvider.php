<?php

namespace App\Providers;

use App\Domain\Repositories\AuditLogRepositoryInterface;
use App\Domain\Repositories\AssistantRepositoryInterface;
use App\Domain\Repositories\FileRepositoryInterface;
use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Domain\Repositories\PermissionRepositoryInterface;
use App\Domain\Repositories\RoleRepositoryInterface;
use App\Domain\Repositories\SettingRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Services\AuthServiceInterface;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Services\RegistrationServiceInterface;
use App\Domain\Services\StorageServiceInterface;
use App\Infrastructure\Repositories\AuditLogRepository;
use App\Infrastructure\Repositories\AssistantRepository;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Infrastructure\Repositories\FileRepository;
use App\Infrastructure\Repositories\NotificationRepository;
use App\Infrastructure\Repositories\PermissionRepository;
use App\Infrastructure\Repositories\RoleRepository;
use App\Infrastructure\Repositories\SettingRepository;
use App\Infrastructure\Services\AuthService;
use App\Infrastructure\Services\EmailVerificationService;
use App\Infrastructure\Services\RegistrationService;
use App\Infrastructure\Services\StorageService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repositories
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(AssistantRepositoryInterface::class, AssistantRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->singleton(SettingRepositoryInterface::class, SettingRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(FileRepositoryInterface::class, FileRepository::class);

        // Services
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(RegistrationServiceInterface::class, RegistrationService::class);
        $this->app->bind(EmailVerificationServiceInterface::class, EmailVerificationService::class);
        $this->app->bind(StorageServiceInterface::class, StorageService::class);
    }

    public function boot(): void
    {
        //
    }
}
