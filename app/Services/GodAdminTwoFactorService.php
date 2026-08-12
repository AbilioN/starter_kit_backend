<?php

namespace App\Services;

use App\Models\GodAdmin;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * All TOTP handling for the GodAdmin guard, kept out of the Livewire
 * components so the rules live in one place and can be unit-tested without a
 * browser or a session.
 */
class GodAdminTwoFactorService
{
    public const RECOVERY_CODE_COUNT = 8;

    /**
     * How many 30s slices either side of "now" are accepted. 1 tolerates a
     * phone clock drifting by up to half a minute, which is common enough that
     * 0 produces support tickets; more than that widens the replay window for
     * no real usability gain.
     */
    private const WINDOW = 1;

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * otpauth:// URI for the QR code. The issuer is what Google Authenticator
     * shows above the code, so it must identify this system rather than the
     * tenant — GodAdmin is not tenant-scoped.
     */
    public function provisioningUri(GodAdmin $godAdmin, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name').' GodAdmin',
            $godAdmin->email,
            $secret,
        );
    }

    /** Inline SVG so the QR never leaves the server — no third-party chart URL. */
    public function qrCodeSvg(string $provisioningUri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 0), new SvgImageBackEnd));

        return $writer->writeString($provisioningUri);
    }

    /**
     * Verifies a code against a secret WITHOUT touching the model — used during
     * setup, where the secret is still pending in the session.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $this->normalise($code), self::WINDOW) !== false;
    }

    /**
     * Verifies a code for an already-enabled GodAdmin and burns the timestamp
     * it was accepted for, so the same code cannot be replayed while it is
     * still inside its validity window.
     */
    public function verifyAndConsumeCode(GodAdmin $godAdmin, string $code): bool
    {
        if (! $godAdmin->two_factor_secret) {
            return false;
        }

        $timestamp = $this->google2fa->verifyKeyNewer(
            $godAdmin->two_factor_secret,
            $this->normalise($code),
            $godAdmin->two_factor_last_used_timestamp,
            self::WINDOW,
        );

        if ($timestamp === false) {
            return false;
        }

        // verifyKeyNewer returns `true` (not a timestamp) when there was no
        // previous timestamp to compare against, so fall back to the current
        // slice rather than writing `1` and defeating the check next time.
        $godAdmin->forceFill([
            'two_factor_last_used_timestamp' => is_int($timestamp)
                ? $timestamp
                : $this->google2fa->getTimestamp(),
        ])->save();

        return true;
    }

    /** @return string[] plaintext codes — shown once, stored hashed */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /**
     * Recovery codes are stored HASHED, not encrypted: nothing ever needs to
     * read them back, only compare. A database dump therefore yields nothing
     * usable even if APP_KEY leaks with it.
     *
     * @param  string[]  $plaintextCodes
     */
    public function storeRecoveryCodes(GodAdmin $godAdmin, array $plaintextCodes): void
    {
        $godAdmin->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $plaintextCodes),
        ])->save();
    }

    /**
     * Consumes a recovery code if it matches. Single use — the matched entry is
     * removed, so a leaked list shrinks as it is used.
     */
    public function consumeRecoveryCode(GodAdmin $godAdmin, string $code): bool
    {
        $stored = $godAdmin->two_factor_recovery_codes ?? [];
        $candidate = trim($code);

        foreach ($stored as $index => $hash) {
            if (Hash::check($candidate, $hash)) {
                unset($stored[$index]);
                $godAdmin->forceFill([
                    'two_factor_recovery_codes' => array_values($stored),
                ])->save();

                return true;
            }
        }

        return false;
    }

    public function disable(GodAdmin $godAdmin): void
    {
        $godAdmin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestamp' => null,
        ])->save();
    }

    /** Users paste codes with spaces from the authenticator app. */
    private function normalise(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?? $code;
    }
}
