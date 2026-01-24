<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Admin;
use PHPUnit\Framework\TestCase;

class AdminTest extends TestCase
{
    public function test_admin_entity_creation()
    {
        // Arrange & Act
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: 'hashed_password',
            isActive: true,
            lastLoginAt: null
        );

        // Assert
        $this->assertEquals(1, $admin->id);
        $this->assertEquals('Test Admin', $admin->name);
        $this->assertEquals('admin@test.com', $admin->email);
        $this->assertEquals('hashed_password', $admin->password);
        $this->assertTrue($admin->isActive);
        $this->assertNull($admin->lastLoginAt);
    }

    public function test_admin_entity_with_last_login()
    {
        // Arrange
        $lastLoginAt = new \DateTime('2025-06-27 20:45:30');

        // Act
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: 'hashed_password',
            isActive: true,
            lastLoginAt: $lastLoginAt
        );

        // Assert
        $this->assertEquals($lastLoginAt, $admin->lastLoginAt);
    }

    public function test_validate_password_returns_true_for_valid_password()
    {
        // Arrange
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: $hashedPassword,
            isActive: true
        );

        // Act
        $result = $admin->validatePassword('password123');

        // Assert
        $this->assertTrue($result);
    }

    public function test_validate_password_returns_false_for_invalid_password()
    {
        // Arrange
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: $hashedPassword,
            isActive: true
        );

        // Act
        $result = $admin->validatePassword('wrongpassword');

        // Assert
        $this->assertFalse($result);
    }

    public function test_generate_token_returns_string()
    {
        // Arrange
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: 'hashed_password',
            isActive: true
        );

        // Act
        $token = $admin->generateToken();

        // Assert
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function test_generate_token_returns_different_tokens()
    {
        // Arrange
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: 'hashed_password',
            isActive: true
        );

        // Act
        $token1 = $admin->generateToken();
        sleep(1); // Ensure different timestamps
        $token2 = $admin->generateToken();

        // Assert
        $this->assertNotEquals($token1, $token2);
    }

    public function test_is_active_returns_true_for_active_admin()
    {
        // Arrange
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: 'hashed_password',
            isActive: true
        );

        // Act
        $result = $admin->isActive();

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_active_returns_false_for_inactive_admin()
    {
        // Arrange
        $admin = new Admin(
            id: 1,
            name: 'Test Admin',
            email: 'admin@test.com',
            password: 'hashed_password',
            isActive: false
        );

        // Act
        $result = $admin->isActive();

        // Assert
        $this->assertFalse($result);
    }
} 