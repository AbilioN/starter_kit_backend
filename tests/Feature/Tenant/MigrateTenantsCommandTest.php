<?php

namespace Tests\Feature\Tenant;

use Tests\TenantTestCase;

class MigrateTenantsCommandTest extends TenantTestCase
{
    private function provision(string $subdomain): void
    {
        $this->artisan('tenant:provision', [
            'name' => ucfirst($subdomain),
            'subdomain' => $subdomain,
            '--admin-email' => "owner@{$subdomain}.test",
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);
    }

    public function test_it_migrates_every_tenant(): void
    {
        $this->provision('acme');
        $this->provision('beta');

        $this->artisan('tenant:migrate')
            ->assertExitCode(0)
            ->expectsOutputToContain('[acme] migrated')
            ->expectsOutputToContain('[beta] migrated')
            ->expectsOutputToContain('2 tenant(s) migrated, 0 failed.');
    }

    public function test_it_can_target_a_single_tenant_via_option(): void
    {
        $this->provision('acme');
        $this->provision('beta');

        $this->artisan('tenant:migrate', ['--tenant' => 'acme'])
            ->assertExitCode(0)
            ->expectsOutputToContain('[acme] migrated')
            ->doesntExpectOutputToContain('[beta]')
            ->expectsOutputToContain('1 tenant(s) migrated, 0 failed.');
    }

    public function test_it_fails_for_an_unknown_tenant_subdomain(): void
    {
        $this->artisan('tenant:migrate', ['--tenant' => 'does-not-exist'])
            ->assertExitCode(1);
    }
}
