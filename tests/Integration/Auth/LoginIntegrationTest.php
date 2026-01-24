<?php

namespace Tests\Integration\Auth;

use App\Application\UseCases\Auth\LoginUseCase;
use App\Domain\Services\AuthServiceInterface;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Infrastructure\Services\AuthService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private LoginUseCase $loginUseCase;
    private AuthServiceInterface $authService;
    private EloquentUserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userRepository = new EloquentUserRepository();
        $this->authService = new AuthService($this->userRepository);
        $this->loginUseCase = new LoginUseCase($this->authService);
    }

    public function test_complete_login_flow_with_real_database()
    {
        // Arrange - Create user in database
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123')
        ]);

        // Act - Execute login use case
        $result = $this->loginUseCase->execute('john@example.com', 'password123');

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        
        $this->assertEquals($user->id, $result['user']['id']);
        $this->assertEquals($user->name, $result['user']['name']);
        $this->assertEquals($user->email, $result['user']['email']);
        $this->assertNotEmpty($result['token']);
    }

    public function test_login_fails_with_nonexistent_user()
    {
        // Act & Assert
        $this->expectException(\App\Domain\Exceptions\AuthenticationException::class);
        $this->loginUseCase->execute('nonexistent@example.com', 'password123');
    }

    public function test_login_fails_with_wrong_password()
    {
        // Arrange
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123')
        ]);

        // Act & Assert
        $this->expectException(\App\Domain\Exceptions\AuthenticationException::class);
        $this->loginUseCase->execute('john@example.com', 'wrongpassword');
    }

    public function test_repository_can_find_user_by_email()
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'john@example.com'
        ]);

        // Act
        $foundUser = $this->userRepository->findByEmail('john@example.com');

        // Assert
        $this->assertNotNull($foundUser);
        $this->assertEquals($user->id, $foundUser->id);
        $this->assertEquals($user->email, $foundUser->email);
    }

    public function test_repository_returns_null_for_nonexistent_email()
    {
        // Act
        $foundUser = $this->userRepository->findByEmail('nonexistent@example.com');

        // Assert
        $this->assertNull($foundUser);
    }
} 