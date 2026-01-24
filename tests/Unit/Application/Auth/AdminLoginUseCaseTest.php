<?php

namespace Tests\Unit\Application\Auth;

use App\Application\UseCases\Auth\AdminLoginUseCase;
use App\Domain\Entities\Admin;
use App\Domain\Services\AdminAuthServiceInterface;
use App\Domain\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;
use Mockery;

class AdminLoginUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_returns_admin_data_and_token_on_successful_login()
    {
        // Arrange
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: 'hashed_password',
            isActive: true,
            isSuperAdmin: false,
            lastLoginAt: null,
            createdAt: null,
            updatedAt: null
        );

        $mockAuthService = Mockery::mock(AdminAuthServiceInterface::class);
        $mockAuthService->shouldReceive('authenticate')
            ->once()
            ->with('admin@test.com', 'password123')
            ->andReturn($admin);

        $mockAuthService->shouldReceive('generateToken')
            ->once()
            ->with($admin)
            ->andReturn('test_token_123');

        $mockAuthService->shouldReceive('getAdminRolesWithPermissions')
            ->once()
            ->with($admin)
            ->andReturn([]);

        $useCase = new AdminLoginUseCase($mockAuthService);

        // Act
        $result = $useCase->execute('admin@test.com', 'password123');

        // Assert
        $this->assertEquals([
            'admin' => [
                'id' => 1,
                'name' => 'Test Admin',
                'email' => 'admin@test.com',
                'is_active' => true,
                'is_super_admin' => false,
                'last_login_at' => null,
                'created_at' => null,
                'updated_at' => null
            ],
            'token' => 'test_token_123',
            'roles' => []
        ], $result);
    }

    public function test_execute_throws_authentication_exception_when_credentials_are_invalid()
    {
        // Arrange
        $mockAuthService = Mockery::mock(AdminAuthServiceInterface::class);
        $mockAuthService->shouldReceive('authenticate')
            ->once()
            ->with('admin@test.com', 'wrongpassword')
            ->andThrow(new AuthenticationException('Invalid credentials'));

        $useCase = new AdminLoginUseCase($mockAuthService);

        // Act & Assert
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $useCase->execute('admin@test.com', 'wrongpassword');
    }

    public function test_execute_throws_authentication_exception_when_admin_is_inactive()
    {
        // Arrange
        $mockAuthService = Mockery::mock(AdminAuthServiceInterface::class);
        $mockAuthService->shouldReceive('authenticate')
            ->once()
            ->with('admin@test.com', 'password123')
            ->andThrow(new AuthenticationException('Admin account is not active'));

        $useCase = new AdminLoginUseCase($mockAuthService);

        // Act & Assert
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Admin account is not active');

        $useCase->execute('admin@test.com', 'password123');
    }

    public function test_execute_formats_last_login_at_when_present()
    {
        // Arrange
        $lastLoginAt = new \DateTime('2025-06-27 20:45:30');
        $createdAt = new \DateTime('2025-06-01 10:00:00');
        $updatedAt = new \DateTime('2025-06-27 20:45:30');
        
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: 'hashed_password',
            isActive: true,
            isSuperAdmin: false,
            lastLoginAt: $lastLoginAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $mockAuthService = Mockery::mock(AdminAuthServiceInterface::class);
        $mockAuthService->shouldReceive('authenticate')
            ->once()
            ->with('admin@test.com', 'password123')
            ->andReturn($admin);

        $mockAuthService->shouldReceive('generateToken')
            ->once()
            ->with($admin)
            ->andReturn('test_token_123');

        $mockAuthService->shouldReceive('getAdminRolesWithPermissions')
            ->once()
            ->with($admin)
            ->andReturn([]);

        $useCase = new AdminLoginUseCase($mockAuthService);

        // Act
        $result = $useCase->execute('admin@test.com', 'password123');

        // Assert
        $this->assertEquals('2025-06-27 20:45:30', $result['admin']['last_login_at']);
        $this->assertEquals('2025-06-01 10:00:00', $result['admin']['created_at']);
        $this->assertEquals('2025-06-27 20:45:30', $result['admin']['updated_at']);
    }
} 