<?php

namespace Tests\Feature\GodAdmin;

use App\Livewire\Auth\Login;
use App\Livewire\Security;
use App\Models\GodAdmin;
use App\Services\GodAdminTwoFactorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TenantTestCase;

class GodAdminTwoFactorTest extends TenantTestCase
{
    private function godAdmin(array $attributes = []): GodAdmin
    {
        return GodAdmin::create(array_merge([
            'name' => 'Root',
            'email' => 'root@starterkit.test',
            'password' => 'secret-password',
        ], $attributes));
    }

    private function enable(GodAdmin $godAdmin): string
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        $godAdmin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    private function currentCode(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('god-login:root@starterkit.test|127.0.0.1');
        parent::tearDown();
    }

    // ── login flow ──────────────────────────────────────────────────────────

    public function test_login_without_two_factor_still_authenticates_directly(): void
    {
        $godAdmin = $this->godAdmin();

        Livewire::test(Login::class)
            ->set('email', 'root@starterkit.test')
            ->set('password', 'secret-password')
            ->call('login')
            ->assertRedirect('/god/dashboard');

        $this->assertAuthenticatedAs($godAdmin, 'godadmin');
    }

    public function test_password_alone_does_not_authenticate_when_two_factor_is_enabled(): void
    {
        $godAdmin = $this->godAdmin();
        $this->enable($godAdmin);

        Livewire::test(Login::class)
            ->set('email', 'root@starterkit.test')
            ->set('password', 'secret-password')
            ->call('login')
            ->assertSet('awaitingTwoFactor', true)
            ->assertNoRedirect();

        // The whole point: the password step must not leave a usable session.
        $this->assertGuest('godadmin');
    }

    public function test_valid_totp_code_completes_the_login(): void
    {
        $godAdmin = $this->godAdmin();
        $secret = $this->enable($godAdmin);

        Livewire::test(Login::class)
            ->set('email', 'root@starterkit.test')
            ->set('password', 'secret-password')
            ->call('login')
            ->set('code', $this->currentCode($secret))
            ->call('verifyTwoFactor')
            ->assertRedirect('/god/dashboard');

        $this->assertAuthenticatedAs($godAdmin, 'godadmin');
    }

    public function test_invalid_totp_code_does_not_authenticate(): void
    {
        $godAdmin = $this->godAdmin();
        $this->enable($godAdmin);

        Livewire::test(Login::class)
            ->set('email', 'root@starterkit.test')
            ->set('password', 'secret-password')
            ->call('login')
            ->set('code', '000000')
            ->call('verifyTwoFactor')
            ->assertSet('error', 'Invalid authentication code.')
            ->assertNoRedirect();

        $this->assertGuest('godadmin');
    }

    public function test_the_same_code_cannot_be_replayed(): void
    {
        $godAdmin = $this->godAdmin();
        $secret = $this->enable($godAdmin);
        $code = $this->currentCode($secret);

        $twoFactor = app(GodAdminTwoFactorService::class);

        $this->assertTrue($twoFactor->verifyAndConsumeCode($godAdmin->fresh(), $code));
        // Still inside the same 30s window, so without replay protection this
        // would pass a second time.
        $this->assertFalse($twoFactor->verifyAndConsumeCode($godAdmin->fresh(), $code));
    }

    public function test_challenge_cannot_be_completed_without_passing_the_password_step(): void
    {
        $godAdmin = $this->godAdmin();
        $secret = $this->enable($godAdmin);

        // Straight to the challenge, never having called login().
        Livewire::test(Login::class)
            ->set('code', $this->currentCode($secret))
            ->call('verifyTwoFactor')
            ->assertNoRedirect();

        $this->assertGuest('godadmin');
    }

    // ── recovery codes ──────────────────────────────────────────────────────

    public function test_recovery_code_logs_in_and_is_single_use(): void
    {
        $godAdmin = $this->godAdmin();
        $this->enable($godAdmin);

        $twoFactor = app(GodAdminTwoFactorService::class);
        $codes = $twoFactor->generateRecoveryCodes();
        $twoFactor->storeRecoveryCodes($godAdmin, $codes);

        Livewire::test(Login::class)
            ->set('email', 'root@starterkit.test')
            ->set('password', 'secret-password')
            ->call('login')
            ->set('code', $codes[0])
            ->call('verifyTwoFactor')
            ->assertRedirect('/god/dashboard');

        $this->assertAuthenticatedAs($godAdmin, 'godadmin');

        // Burned: the same code must not work again.
        $this->assertFalse($twoFactor->consumeRecoveryCode($godAdmin->fresh(), $codes[0]));
        // The others survive.
        $this->assertTrue($twoFactor->consumeRecoveryCode($godAdmin->fresh(), $codes[1]));
    }

    public function test_recovery_codes_are_stored_hashed_not_in_plaintext(): void
    {
        $godAdmin = $this->godAdmin();
        $twoFactor = app(GodAdminTwoFactorService::class);
        $codes = $twoFactor->generateRecoveryCodes();
        $twoFactor->storeRecoveryCodes($godAdmin, $codes);

        foreach ($godAdmin->fresh()->two_factor_recovery_codes as $stored) {
            $this->assertNotContains($stored, $codes, 'a recovery code was stored in plaintext');
        }
        $this->assertTrue(Hash::check($codes[0], $godAdmin->fresh()->two_factor_recovery_codes[0]));
    }

    public function test_secret_is_encrypted_at_rest(): void
    {
        $godAdmin = $this->godAdmin();
        $secret = $this->enable($godAdmin);

        $raw = GodAdmin::on('landlord')
            ->getConnection()
            ->table('godadmins')
            ->where('id', $godAdmin->id)
            ->value('two_factor_secret');

        $this->assertNotSame($secret, $raw, 'the TOTP secret is readable straight from the database');
        // ...but still decrypts through the model cast.
        $this->assertSame($secret, $godAdmin->fresh()->two_factor_secret);
    }

    // ── setup / disable ─────────────────────────────────────────────────────

    public function test_starting_setup_does_not_enable_anything_yet(): void
    {
        $godAdmin = $this->godAdmin();
        $this->actingAs($godAdmin, 'godadmin');

        Livewire::test(Security::class)
            ->call('startSetup')
            ->assertSet('settingUp', true);

        $this->assertFalse($godAdmin->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirming_setup_with_a_valid_code_enables_and_returns_recovery_codes(): void
    {
        $godAdmin = $this->godAdmin();
        $this->actingAs($godAdmin, 'godadmin');

        $component = Livewire::test(Security::class)->call('startSetup');
        $secret = session('god.2fa.setup_secret');

        $component->set('confirmCode', $this->currentCode($secret))
            ->call('confirmSetup')
            ->assertSet('settingUp', false);

        $this->assertTrue($godAdmin->fresh()->hasTwoFactorEnabled());
        $this->assertCount(
            GodAdminTwoFactorService::RECOVERY_CODE_COUNT,
            $component->get('recoveryCodes')
        );
    }

    public function test_confirming_setup_with_a_bad_code_does_not_enable(): void
    {
        $godAdmin = $this->godAdmin();
        $this->actingAs($godAdmin, 'godadmin');

        Livewire::test(Security::class)
            ->call('startSetup')
            ->set('confirmCode', '000000')
            ->call('confirmSetup')
            ->assertSet('settingUp', true);

        $this->assertFalse($godAdmin->fresh()->hasTwoFactorEnabled());
    }

    public function test_disabling_requires_the_current_password(): void
    {
        $godAdmin = $this->godAdmin();
        $this->enable($godAdmin);
        $this->actingAs($godAdmin, 'godadmin');

        Livewire::test(Security::class)
            ->set('disablePassword', 'not-the-password')
            ->call('disable')
            ->assertSet('error', 'Incorrect password.');

        $this->assertTrue($godAdmin->fresh()->hasTwoFactorEnabled());

        Livewire::test(Security::class)
            ->set('disablePassword', 'secret-password')
            ->call('disable');

        $this->assertFalse($godAdmin->fresh()->hasTwoFactorEnabled());
    }

    public function test_security_page_requires_authentication(): void
    {
        $this->get('/god/security')->assertRedirect('/god/login');
    }
}
