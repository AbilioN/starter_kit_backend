<?php

namespace App\Application\UseCases\Admin;

use App\Application\UseCases\Tenant\EnforcePlanLimitUseCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * An administrator creating an end user directly.
 *
 * Distinct from RegisterUseCase, which is self-service and sends a
 * verification code. Here somebody with `user-create` inside the tenant is
 * vouching for the address, so the user is created already verified — asking
 * an administrator to chase a code for an account they just typed in is
 * ceremony that protects nothing.
 *
 * The plan limit is the same one self-service registration enforces, and it
 * has to be: two doors into the same table with one cap between them.
 */
class CreateUserUseCase
{
    public function __construct(private EnforcePlanLimitUseCase $enforcePlanLimit) {}

    public function execute(string $name, string $email, string $password, ?string $locale = null): User
    {
        $this->enforcePlanLimit->execute('max_users', fn () => User::count());

        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            // Null means "never said", not "chose the default" — the row then
            // keeps following the tenant when the tenant's own language
            // changes. Same rule as users.locale everywhere else.
            'locale' => $locale,
            'email_verified_at' => now(),
        ]);
    }
}
