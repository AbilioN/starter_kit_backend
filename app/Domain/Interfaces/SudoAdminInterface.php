<?php

namespace App\Domain\Interfaces;

interface SudoAdminInterface extends AuthorizableUser
{
    public function canManageSystem(): bool;
    public function canBypassAllPermissions(): bool;
    public function getAdminLevel(): string;
}
