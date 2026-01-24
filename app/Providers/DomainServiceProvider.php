<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Domain\Repositories\AssistantRepositoryInterface;
use App\Infrastructure\Repositories\AssistantRepository;
use App\Domain\Repositories\RoleRepositoryInterface;
use App\Infrastructure\Repositories\RoleRepository;
use App\Domain\Repositories\PermissionRepositoryInterface;
use App\Infrastructure\Repositories\PermissionRepository;
use App\Domain\Services\AuthServiceInterface;
use App\Infrastructure\Services\AuthService;
use App\Domain\Services\RegistrationServiceInterface;
use App\Infrastructure\Services\RegistrationService;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Infrastructure\Services\EmailVerificationService;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repositories
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(AssistantRepositoryInterface::class, AssistantRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);

         // Services
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(RegistrationServiceInterface::class, RegistrationService::class);
        $this->app->bind(EmailVerificationServiceInterface::class, EmailVerificationService::class);
    }

    public function boot(): void
    {
        //
    }
}
