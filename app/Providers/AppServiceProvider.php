<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Domain\Services\AuthServiceInterface;
use App\Infrastructure\Services\AuthService;
use App\Domain\Services\RegistrationServiceInterface;
use App\Infrastructure\Services\RegistrationService;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Infrastructure\Services\EmailVerificationService;
use App\Domain\Repositories\AdminRepositoryInterface;
use App\Infrastructure\Repositories\AdminRepository;
use App\Domain\Services\AdminAuthServiceInterface;
use App\Infrastructure\Services\AdminAuthService;
use App\Domain\Repositories\MessageRepositoryInterface;
use App\Infrastructure\Repositories\MessageRepository;
use App\Domain\Repositories\GodAdminRepositoryInterface;
use App\Infrastructure\Repositories\GodAdminRepository;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Infrastructure\Repositories\SubscriptionPlanRepository;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Repositories\TenantRepository;
use App\Domain\Repositories\LandlordAuditLogRepositoryInterface;
use App\Infrastructure\Repositories\LandlordAuditLogRepository;
use App\Domain\Services\TenantProvisioningServiceInterface;
use App\Infrastructure\Services\TenantProvisioningService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // User dependencies
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(RegistrationServiceInterface::class, RegistrationService::class);
        $this->app->bind(EmailVerificationServiceInterface::class, EmailVerificationService::class);

        // Admin dependencies
        $this->app->bind(AdminRepositoryInterface::class, AdminRepository::class);
        $this->app->bind(AdminAuthServiceInterface::class, AdminAuthService::class);

        // Chat dependencies
        $this->app->bind(\App\Domain\Repositories\ChatRepositoryInterface::class, \App\Infrastructure\Repositories\ChatRepository::class);
        $this->app->bind(\App\Domain\Repositories\MessageRepositoryInterface::class, \App\Infrastructure\Repositories\MessageRepository::class);

        // Landlord dependencies
        $this->app->bind(GodAdminRepositoryInterface::class, GodAdminRepository::class);
        $this->app->bind(SubscriptionPlanRepositoryInterface::class, SubscriptionPlanRepository::class);
        $this->app->bind(TenantRepositoryInterface::class, TenantRepository::class);
        $this->app->bind(LandlordAuditLogRepositoryInterface::class, LandlordAuditLogRepository::class);
        $this->app->bind(TenantProvisioningServiceInterface::class, TenantProvisioningService::class);

        // Services
        $this->app->singleton(\App\Services\PusherApiService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Habilitar broadcasting
        \Illuminate\Support\Facades\Broadcast::routes(['middleware' => ['auth:sanctum']]);

        // Horizon dashboard — only accessible by super admin in production
        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            \Laravel\Horizon\Horizon::auth(function ($request) {
                if (app()->environment('local')) {
                    return true;
                }

                $user = $request->user();
                return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
            });
        }
    }
}
