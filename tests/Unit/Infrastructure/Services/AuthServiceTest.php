<?php

namespace Tests\Unit\Infrastructure\Services;

use App\Domain\Entities\User;
use App\Domain\Exceptions\AuthenticationException;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Services\AuthService;
use App\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->authService = new AuthService($this->userRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_authenticate_returns_user_with_valid_credentials()
    {
        $user = new User(1, 'John Doe', 'john@example.com', password_hash('password123', PASSWORD_DEFAULT), new \DateTime());
        $mockRepo = Mockery::mock(UserRepositoryInterface::class);
        $mockRepo->shouldReceive('findByEmail')->with('john@example.com')->andReturn($user);

        $service = new AuthService($mockRepo);

        $result = $service->authenticate('john@example.com', 'password123');
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('John Doe', $result->name);
    }

    public function test_authenticate_throws_exception_with_invalid_email()
    {
        // Arrange
        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('nonexistent@example.com')
            ->once()
            ->andReturn(null);

        // Act & Assert
        $this->expectException(AuthenticationException::class);
        $this->authService->authenticate('nonexistent@example.com', 'password123');
    }

    public function test_authenticate_throws_exception_with_invalid_password()
    {
        // Arrange
        $user = new User(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            password: password_hash('password123', PASSWORD_DEFAULT)
        );

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('john@example.com')
            ->once()
            ->andReturn($user);

        // Act & Assert
        $this->expectException(AuthenticationException::class);
        $this->authService->authenticate('john@example.com', 'wrongpassword');
    }

    public function test_generate_token_returns_string()
    {
        // Arrange
        $user = new User(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            password: password_hash('password123', PASSWORD_DEFAULT)
        );

        // Mock the User model for token generation
        $userModel = UserModel::factory()->create([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // Act
        $token = $this->authService->generateToken($user);

        // Assert
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function test_authenticate_throws_exception_for_unverified_user()
    {
        $user = new User(1, 'John Doe', 'john@example.com', password_hash('password123', PASSWORD_DEFAULT), null);
        $mockRepo = Mockery::mock(UserRepositoryInterface::class);
        $mockRepo->shouldReceive('findByEmail')->with('john@example.com')->andReturn($user);

        $service = new AuthService($mockRepo);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Email not verified');
        $service->authenticate('john@example.com', 'password123');
    }
} 