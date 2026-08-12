<?php

namespace App\Livewire;

use App\Models\GodAdmin;
use App\Services\GodAdminTwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * /god/security — enable, confirm and disable TOTP for the signed-in GodAdmin.
 *
 * Setup is deliberately two-step: generating a secret does NOT enable anything.
 * Enforcement only starts once a code generated from that secret has been
 * verified, so closing the tab halfway through cannot lock the account out.
 */
class Security extends Component
{
    public string $confirmCode = '';

    public string $disablePassword = '';

    public string $error = '';

    public string $status = '';

    /** Shown once, immediately after confirming — never retrievable again. */
    public array $recoveryCodes = [];

    public bool $settingUp = false;

    public string $qrCodeSvg = '';

    /**
     * The pending secret lives in the SESSION, not in a public Livewire
     * property: public properties are round-tripped through the browser, so a
     * secret placed there would be readable in the page payload before 2FA is
     * even active, and writable by the client.
     */
    private const PENDING_SECRET_KEY = 'god.2fa.setup_secret';

    public function mount(): void
    {
        $this->settingUp = session()->has(self::PENDING_SECRET_KEY);

        if ($this->settingUp) {
            $this->renderQrCode(session(self::PENDING_SECRET_KEY));
        }
    }

    public function startSetup(GodAdminTwoFactorService $twoFactor): void
    {
        $secret = $twoFactor->generateSecret();
        session([self::PENDING_SECRET_KEY => $secret]);

        $this->renderQrCode($secret);
        $this->settingUp = true;
        $this->error = '';
        $this->status = '';
        $this->recoveryCodes = [];
    }

    public function confirmSetup(GodAdminTwoFactorService $twoFactor): void
    {
        $this->validate(['confirmCode' => 'required|string']);

        $secret = session(self::PENDING_SECRET_KEY);

        if (! $secret) {
            $this->cancelSetup();
            $this->error = 'Setup expired. Start again.';

            return;
        }

        if (! $twoFactor->verifyCode($secret, $this->confirmCode)) {
            $this->confirmCode = '';
            $this->error = 'That code is not valid. Check your authenticator app and try again.';

            return;
        }

        $godAdmin = $this->godAdmin();
        $godAdmin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $codes = $twoFactor->generateRecoveryCodes();
        $twoFactor->storeRecoveryCodes($godAdmin, $codes);

        session()->forget(self::PENDING_SECRET_KEY);

        $this->recoveryCodes = $codes;
        $this->settingUp = false;
        $this->confirmCode = '';
        $this->qrCodeSvg = '';
        $this->error = '';
        $this->status = 'Two-factor authentication is now enabled.';
    }

    public function cancelSetup(): void
    {
        session()->forget(self::PENDING_SECRET_KEY);
        $this->settingUp = false;
        $this->qrCodeSvg = '';
        $this->confirmCode = '';
    }

    public function regenerateRecoveryCodes(GodAdminTwoFactorService $twoFactor): void
    {
        $godAdmin = $this->godAdmin();

        if (! $godAdmin->hasTwoFactorEnabled()) {
            return;
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $twoFactor->storeRecoveryCodes($godAdmin, $codes);

        $this->recoveryCodes = $codes;
        $this->status = 'New recovery codes generated. The previous ones no longer work.';
    }

    /**
     * Turning 2FA off is a privilege downgrade on the most powerful account in
     * the system, so it re-checks the password: an unattended, already-open
     * session should not be enough to strip the second factor.
     */
    public function disable(GodAdminTwoFactorService $twoFactor): void
    {
        $this->validate(['disablePassword' => 'required|string']);

        $godAdmin = $this->godAdmin();

        if (! Hash::check($this->disablePassword, $godAdmin->password)) {
            $this->disablePassword = '';
            $this->error = 'Incorrect password.';

            return;
        }

        $twoFactor->disable($godAdmin);

        $this->disablePassword = '';
        $this->recoveryCodes = [];
        $this->error = '';
        $this->status = 'Two-factor authentication has been disabled.';
    }

    private function renderQrCode(string $secret): void
    {
        $twoFactor = app(GodAdminTwoFactorService::class);
        $this->qrCodeSvg = $twoFactor->qrCodeSvg(
            $twoFactor->provisioningUri($this->godAdmin(), $secret)
        );
    }

    private function godAdmin(): GodAdmin
    {
        return Auth::guard('godadmin')->user();
    }

    public function render()
    {
        return view('livewire.security', [
            'enabled' => $this->godAdmin()->hasTwoFactorEnabled(),
            'pendingSecret' => session(self::PENDING_SECRET_KEY),
        ])->layout('layouts.god');
    }
}
