<?php

namespace Tests\Feature\GodAdmin;

use App\Models\GodAdmin;
use Livewire\Livewire;
use Tests\TenantTestCase;

class GodAdminAuthTest extends TenantTestCase
{
    public function test_login_page_renders(): void
    {
        $this->get('/god/login')->assertStatus(200);
    }

    public function test_godadmin_can_log_in_with_valid_credentials(): void
    {
        $godAdmin = GodAdmin::create([
            'name' => 'Root',
            'email' => 'root@starterkit.test',
            'password' => 'secret-password',
        ]);

        Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', 'root@starterkit.test')
            ->set('password', 'secret-password')
            ->call('login')
            ->assertRedirect('/god/dashboard');

        $this->assertAuthenticatedAs($godAdmin, 'godadmin');
    }

    public function test_godadmin_login_fails_with_invalid_credentials(): void
    {
        GodAdmin::create([
            'name' => 'Root',
            'email' => 'root@starterkit.test',
            'password' => 'secret-password',
        ]);

        Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', 'root@starterkit.test')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertSet('error', 'Invalid credentials.');

        $this->assertGuest('godadmin');
    }
}
