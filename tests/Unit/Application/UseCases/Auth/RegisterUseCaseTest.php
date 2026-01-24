<?php

namespace Tests\Unit\Application\UseCases\Auth;

use App\Application\UseCases\Auth\RegisterUseCase;
use App\Domain\Entities\User;
use App\Domain\Exceptions\RegistrationException;
use App\Domain\Services\RegistrationServiceInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

class RegisterUseCaseTest extends TestCase
{
    private RegisterUseCase $registerUseCase;
    private RegistrationServiceInterface $registrationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registrationService = Mockery::mock(RegistrationServiceInterface::class);
        $this->registerUseCase = new RegisterUseCase($this->registrationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_returns_user_data_with_valid_data()
    {
        $mockRegistrationService = Mockery::mock(RegistrationServiceInterface::class);
        $mockRegistrationService->shouldReceive('register')
            ->once()
            ->andReturn(new User(1, 'John Doe', 'john@example.com', 'hashed-password'));

        $useCase = new RegisterUseCase($mockRegistrationService);

        $result = $useCase->execute('John Doe', 'john@example.com', 'password123');

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ], $result['user']);
    }

    public function test_execute_throws_exception_when_registration_fails()
    {
        // Arrange
        $this->registrationService
            ->shouldReceive('register')
            ->with('John Doe', 'existing@example.com', 'password123')
            ->once()
            ->andThrow(new RegistrationException('Email already exists'));

        // Act & Assert
        $this->expectException(RegistrationException::class);
        $this->registerUseCase->execute('John Doe', 'existing@example.com', 'password123');
    }
} 