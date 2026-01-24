<?php

namespace Tests\Unit\Application\UseCases\Auth;

use App\Application\UseCases\Auth\LoginUseCase;
use App\Domain\Entities\User;
use App\Domain\Exceptions\AuthenticationException;
use App\Domain\Services\AuthServiceInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

class LoginUseCaseTest extends TestCase
{
    private LoginUseCase $loginUseCase;
    private AuthServiceInterface $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = Mockery::mock(AuthServiceInterface::class);
        $this->loginUseCase = new LoginUseCase($this->authService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_returns_user_data_and_token_with_valid_credentials()
    {
        // Arrange
        $user = new User(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            password: password_hash('password123', PASSWORD_DEFAULT)
        );

        $this->authService
            ->shouldReceive('authenticate')
            ->with('john@example.com', 'password123')
            ->once()
            ->andReturn($user);

        $this->authService
            ->shouldReceive('generateToken')
            ->with($user)
            ->once()
            ->andReturn('valid-token-123');

        // Act
        $result = $this->loginUseCase->execute('john@example.com', 'password123');

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        
        $this->assertEquals([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => null
        ], $result['user']);
        
        $this->assertEquals('valid-token-123', $result['token']);
    }

    public function test_execute_throws_exception_with_invalid_credentials()
    {
        // Arrange
        $this->authService
            ->shouldReceive('authenticate')
            ->with('invalid@example.com', 'wrongpassword')
            ->once()
            ->andThrow(new AuthenticationException());

        // Act & Assert
        $this->expectException(AuthenticationException::class);
        $this->loginUseCase->execute('invalid@example.com', 'wrongpassword');
    }
} 