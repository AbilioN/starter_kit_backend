<?php

namespace App\Application\UseCases\Admin;

use App\Domain\Repositories\UserRepositoryInterface;

class ListUsersUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $page = 1, int $perPage = 15): array
    {
        $users = $this->userRepository->findAllPaginated($page, $perPage);
        
        return [
            'users' => $users['data'],
            'pagination' => [
                'current_page' => $users['current_page'],
                'per_page' => $users['per_page'],
                'total' => $users['total'],
                'last_page' => $users['last_page'],
                'from' => $users['from'],
                'to' => $users['to']
            ]
        ];
    }
} 