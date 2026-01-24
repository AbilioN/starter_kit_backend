<?php

namespace Tests\Unit\Application\Auth;

use App\Application\UseCases\Auth\AdminRegisterUseCase;
use App\Domain\Entities\Admin;
use App\Domain\Repositories\AdminRepositoryInterface;
use App\Domain\Exceptions\RegistrationException;
use PHPUnit\Framework\TestCase;
use Mockery;

class AdminRegisterUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_returns_admin_data_on_successful_registration()
    {
        // Arrange
        $admin = new Admin(
            id: 1,
            name: 'New Admin',
            email: 'newadmin@test.com',
            password: 'hashed_password',
            isActive: true,
            lastLoginAt: null
        );

        $mockRepository = Mockery::mock(AdminRepositoryInterface::class);
        $mockRepository->shouldReceive('findByEmail')
            ->once()
            ->with('newadmin@test.com')
            ->andReturn(null);

        $mockRepository->shouldReceive('create')
            ->once()
            ->with('New Admin', 'newadmin@test.com', 'hashed_password')
            ->andReturn($admin);

        $useCase = new AdminRegisterUseCase($mockRepository);

        // Act
        $result = $useCase->execute('New Admin', 'newadmin@test.com', 'hashed_password');

        // Assert
        $this->assertEquals([
            'admin' => [
                'id' => 1,
                'name' => 'New Admin',
                'email' => 'newadmin@test.com',
                'is_active' => true,
                'created_at' => now()->format('Y-m-d H:i:s')
            ]
        ], $result);
    }

    public function test_execute_throws_registration_exception_when_email_already_exists()
    {
        // Arrange
        $existingAdmin = new Admin(
            id: 1,
            name: 'Existing Admin',
            email: 'existing@test.com',
            password: 'hashed_password',
            isActive: true,
            lastLoginAt: null
        );

        $mockRepository = Mockery::mock(AdminRepositoryInterface::class);
        $mockRepository->shouldReceive('findByEmail')
            ->once()
            ->with('existing@test.com')
            ->andReturn($existingAdmin);

        $useCase = new AdminRegisterUseCase($mockRepository);

        // Act & Assert
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Admin with this email already exists');

        $useCase->execute('New Admin', 'existing@test.com', 'hashed_password');
    }

    public function test_execute_creates_admin_with_correct_data()
    {
        // Arrange
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'test@test.com',
            password: 'hashed_password',
            isActive: true,
            lastLoginAt: null
        );

        $mockRepository = Mockery::mock(AdminRepositoryInterface::class);
        $mockRepository->shouldReceive('findByEmail')
            ->once()
            ->with('test@test.com')
            ->andReturn(null);

        $mockRepository->shouldReceive('create')
            ->once()
            ->with('Test Admin', 'test@test.com', 'hashed_password')
            ->andReturn($admin);

        $useCase = new AdminRegisterUseCase($mockRepository);

        // Act
        $result = $useCase->execute('Test Admin', 'test@test.com', 'hashed_password');

        // Assert
        $this->assertEquals('Test Admin', $result['admin']['name']);
        $this->assertEquals('test@test.com', $result['admin']['email']);
        $this->assertTrue($result['admin']['is_active']);
    }

    public function test_execute_includes_created_at_timestamp()
    {
        // Arrange
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'test@test.com',
            password: 'hashed_password',
            isActive: true,
            lastLoginAt: null
        );

        $mockRepository = Mockery::mock(AdminRepositoryInterface::class);
        $mockRepository->shouldReceive('findByEmail')
            ->once()
            ->with('test@test.com')
            ->andReturn(null);

        $mockRepository->shouldReceive('create')
            ->once()
            ->with('Test Admin', 'test@test.com', 'hashed_password')
            ->andReturn($admin);

        $useCase = new AdminRegisterUseCase($mockRepository);

        // Act
        $result = $useCase->execute('Test Admin', 'test@test.com', 'hashed_password');

        // Assert
        $this->assertArrayHasKey('created_at', $result['admin']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['admin']['created_at']);
    }
} 