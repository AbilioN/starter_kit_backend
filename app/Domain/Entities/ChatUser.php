<?php

namespace App\Domain\Entities;

interface ChatUser
{
    /**
     * Obtém o ID único do usuário
     */
    public function getId(): int;

    /**
     * Obtém o nome do usuário
     */
    public function getName(): string;

    /**
     * Obtém o email do usuário (pode ser null para assistentes)
     */
    public function getEmail(): ?string;

    /**
     * Obtém o tipo do usuário (user ou admin)
     */
    public function getType(): string;

    /**
     * Verifica se o usuário está ativo
     */
    public function isActive(): bool;
} 