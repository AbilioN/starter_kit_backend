<?php

namespace App\Livewire\Auth;

use App\Models\GodAdmin;
use App\Services\GodAdminTwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public string $code = '';

    public string $error = '';

    /** Drives which step the view renders. */
    public bool $awaitingTwoFactor = false;

    /**
     * Session key holding the id of the GodAdmin who has passed the password
     * step but not yet the TOTP step. Deliberately NOT a public Livewire
     * property: those round-trip through the browser and are tamperable, so
     * putting the pending identity there would let anyone skip straight to the
     * challenge for an arbitrary account.
     */
    private const PENDING_KEY = 'god.2fa.pending_id';

    private const PENDING_EXPIRES_KEY = 'god.2fa.pending_expires_at';

    /** A half-finished login should not stay resumable forever. */
    private const PENDING_TTL_SECONDS = 300;

    public function mount(): void
    {
        // Survives a page reload in the middle of the challenge.
        $this->awaitingTwoFactor = $this->pendingGodAdmin() !== null;
    }

    public function login(): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $key = 'god-login:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->error = 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.';

            return;
        }

        $godAdmin = GodAdmin::on('landlord')->where('email', $this->email)->first();

        // Credentials are checked WITHOUT logging in, because a GodAdmin with
        // 2FA enabled must not hold an authenticated session until the second
        // factor is proved. Auth::attempt() would have established one here.
        if (! $godAdmin || ! Hash::check($this->password, $godAdmin->password)) {
            RateLimiter::hit($key, 300);
            $this->error = 'Invalid credentials.';

            return;
        }

        RateLimiter::clear($key);

        if (! $godAdmin->hasTwoFactorEnabled()) {
            $this->completeLogin($godAdmin);

            return;
        }

        session([
            self::PENDING_KEY => $godAdmin->id,
            self::PENDING_EXPIRES_KEY => now()->addSeconds(self::PENDING_TTL_SECONDS)->timestamp,
        ]);

        $this->password = '';
        $this->awaitingTwoFactor = true;
        $this->error = '';
    }

    public function verifyTwoFactor(GodAdminTwoFactorService $twoFactor): void
    {
        $this->validate(['code' => 'required|string']);

        $godAdmin = $this->pendingGodAdmin();

        if (! $godAdmin) {
            $this->resetChallenge();
            $this->error = 'Your login attempt expired. Please sign in again.';

            return;
        }

        // Six digits is only a million combinations, and the accepted window
        // spans 90 seconds — without a limiter that is brute-forceable.
        $key = 'god-2fa:'.$godAdmin->id.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->error = 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.';

            return;
        }

        $accepted = $twoFactor->verifyAndConsumeCode($godAdmin, $this->code)
            || $twoFactor->consumeRecoveryCode($godAdmin, $this->code);

        if (! $accepted) {
            RateLimiter::hit($key, 300);
            $this->code = '';
            $this->error = 'Invalid authentication code.';

            return;
        }

        RateLimiter::clear($key);
        $this->completeLogin($godAdmin);
    }

    public function backToLogin(): void
    {
        $this->resetChallenge();
        $this->reset('password', 'code');
        $this->error = '';
    }

    private function completeLogin(GodAdmin $godAdmin): void
    {
        $this->resetChallenge();

        Auth::guard('godadmin')->login($godAdmin);

        // Fixation defence: the session id that carried the pending-2FA state
        // must not be the one that ends up authenticated.
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect('/god/dashboard', navigate: false);
    }

    private function pendingGodAdmin(): ?GodAdmin
    {
        $id = session(self::PENDING_KEY);
        $expiresAt = session(self::PENDING_EXPIRES_KEY);

        if (! $id || ! $expiresAt || now()->timestamp > $expiresAt) {
            return null;
        }

        return GodAdmin::on('landlord')->find($id);
    }

    private function resetChallenge(): void
    {
        session()->forget([self::PENDING_KEY, self::PENDING_EXPIRES_KEY]);
        $this->awaitingTwoFactor = false;
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.god');
    }
}
