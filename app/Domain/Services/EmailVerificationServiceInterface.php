<?php

namespace App\Domain\Services;

use App\Domain\Entities\User;

interface EmailVerificationServiceInterface
{
    public function sendVerificationCode(User $user): void;
    public function verifyCode(string $email, string $code): bool;
    public function resendVerificationCode(string $email): void;
} 