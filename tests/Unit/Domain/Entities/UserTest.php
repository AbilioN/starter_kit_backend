<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function test_user_can_be_created_with_valid_data()
    {
        $user = new User(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            password: password_hash('password123', PASSWORD_DEFAULT)
        );

        $this->assertEquals(1, $user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function test_user_can_validate_correct_password()
    {
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $user = new User(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            password: $hashedPassword
        );

        $this->assertTrue($user->validatePassword('password123'));
    }

    public function test_user_cannot_validate_incorrect_password()
    {
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $user = new User(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            password: $hashedPassword
        );

        $this->assertFalse($user->validatePassword('wrongpassword'));
    }

    public function test_user_can_generate_token()
    {
        $user = new User(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            password: password_hash('password123', PASSWORD_DEFAULT)
        );

        $token = $user->generateToken();
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }
} 