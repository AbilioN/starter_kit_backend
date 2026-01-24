<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\User;
use App\Domain\Services\RegistrationServiceInterface;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Exceptions\RegistrationException;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\Hash;

class RegistrationService implements RegistrationServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EmailVerificationServiceInterface $emailVerificationService
    ) {}

    public function register(string $name, string $email, string $password): User
    {
        // Verificar se o email já existe
        $existingUser = $this->userRepository->findByEmail($email);
        
        if ($existingUser) {
            throw new RegistrationException('Email already exists');
        }

        // Criar novo usuário
        $user = new User(
            id: 0,
            name: $name,
            email: $email,
            password: Hash::make($password)
        );

        // Salvar no repositório
        $savedUser = $this->userRepository->save($user);

        // Enviar código de verificação
        $this->emailVerificationService->sendVerificationCode($savedUser);

        return $savedUser;
    }
} 