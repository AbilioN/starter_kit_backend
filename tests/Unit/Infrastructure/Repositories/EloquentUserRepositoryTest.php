<?php

namespace Tests\Unit\Infrastructure\Repositories;

use App\Domain\Entities\User;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentUserRepository();
    }

    public function test_find_by_email_returns_user_when_exists()
    {
        // Arrange
        $userModel = UserModel::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123')
        ]);

        // Act
        $user = $this->repository->findByEmail('john@example.com');

        // Assert
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($userModel->id, $user->id);
        $this->assertEquals($userModel->name, $user->name);
        $this->assertEquals($userModel->email, $user->email);
    }

    public function test_find_by_email_returns_null_when_user_not_exists()
    {
        // Act
        $user = $this->repository->findByEmail('nonexistent@example.com');

        // Assert
        $this->assertNull($user);
    }

    public function test_save_creates_new_user()
    {
        // Arrange
        $user = new User(
            id: 0,
            name: 'Jane Doe',
            email: 'jane@example.com',
            password: bcrypt('password123')
        );

        // Act
        $savedUser = $this->repository->save($user);

        // Assert
        $this->assertInstanceOf(User::class, $savedUser);
        $this->assertGreaterThan(0, $savedUser->id);
        $this->assertEquals('Jane Doe', $savedUser->name);
        $this->assertEquals('jane@example.com', $savedUser->email);
        
        // Verify it was saved in database
        $this->assertDatabaseHas('users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);
    }

    public function test_save_updates_existing_user()
    {
        // Arrange
        $userModel = UserModel::factory()->create([
            'email' => 'john@example.com'
        ]);

        $user = new User(
            id: $userModel->id,
            name: 'John Updated',
            email: 'john@example.com',
            password: bcrypt('newpassword')
        );

        // Act
        $savedUser = $this->repository->save($user);

        // Assert
        $this->assertEquals($userModel->id, $savedUser->id);
        $this->assertEquals('John Updated', $savedUser->name);
        
        // Verify it was updated in database
        $this->assertDatabaseHas('users', [
            'id' => $userModel->id,
            'name' => 'John Updated'
        ]);
    }
} 