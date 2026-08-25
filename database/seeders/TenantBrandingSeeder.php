<?php

namespace Database\Seeders;

use App\Domain\Services\IconResizingServiceInterface;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Landlord seeder, not tenant - Tenant doesn't exist inside DatabaseSeeder's
 * tenant-scoped run() chain. Invoke directly:
 * php artisan db:seed --class=TenantBrandingSeeder
 *
 * Applies the full palette and logo from
 * storage/app/public/tenant-logos/tenant-branding.json to the demo tenants
 * used throughout local dev (tenant-a, tenant-b, tenant-c - see
 * docs/04-local-dev-tenants.md). That file is the source of truth for the
 * demo palettes and has always described three colors per tenant; until the
 * tenants table grew theme_tertiary_color, this seeder silently dropped the
 * third one.
 *
 * Silently skips a subdomain that hasn't been provisioned yet (tenants are
 * created via `tenant:provision`, never via this seeder) rather than
 * failing - safe to run on any environment regardless of which demo
 * tenants happen to exist.
 *
 * ## Why the icons are generated rather than converted
 *
 * The shipped logos are SVG, and the resizer is GD (no Imagick, no
 * rsvg-convert in the PHP image - see IconResizingService), so an SVG cannot
 * be rasterized here. Rather than ship three PNGs as binary fixtures, this
 * paints a square source image from the tenant's own palette and pushes it
 * through the REAL IconResizingService, so a seeded environment exercises the
 * same 32/128/512 pipeline an operator uploading a logo would - if resizing
 * breaks, seeding breaks with it, which a checked-in PNG would have hidden.
 * logo_path keeps pointing at the SVG: it is the wide-header artwork, and
 * icon_paths is the square-icon artwork. They are not substitutes.
 *
 * Overwrites branding on every run, deliberately: a demo environment that
 * quietly keeps colors from a manual test has stopped reproducing the setup
 * this seeder exists to guarantee. Same reasoning as broadcasting_provider_id
 * below.
 *
 * Both demo tenants also get `broadcasting_provider_id = null` on purpose:
 * they share the one Pusher app configured in .env, which is the intended
 * shape for entry-level plans - those tenants compete for the same WebSocket
 * bus, and that is exactly what they are paying for. A tenant only gets a
 * dedicated bus once its plan (or the tenant itself) points at its own
 * `infrastructure_providers` row, which is what higher tiers are for.
 *
 * Nulling it here is deliberate rather than incidental: leaving the column
 * untouched would let a provider assigned during manual testing survive a
 * re-seed, so the demo environment would silently stop reproducing the
 * shared-bus setup this seeder is supposed to guarantee. See
 * TenantInfrastructureResolver, which falls back tenant -> plan -> .env.
 */
class TenantBrandingSeeder extends Seeder
{
    private const MANIFEST = 'tenant-logos/tenant-branding.json';

    /** Source square fed to the resizer; above the largest variant (512). */
    private const SOURCE_PIXELS = 1024;

    public function __construct(
        private readonly IconResizingServiceInterface $iconResizingService,
    ) {}

    public function run(): void
    {
        foreach ($this->readManifest() as $entry) {
            $subdomain = $entry['id'];
            $tenant = Tenant::where('subdomain', $subdomain)->first();

            if (! $tenant) {
                $this->command?->warn("Skipping '{$subdomain}' branding: tenant not provisioned yet.");

                continue;
            }

            $colors = $entry['colors'];

            $tenant->update([
                'theme_primary_color' => $colors['primary'],
                'theme_secondary_color' => $colors['secondary'],
                'theme_tertiary_color' => $colors['tertiary'],
                'logo_path' => 'tenant-logos/'.$entry['logo'],
                'icon_paths' => $this->generateIcons($subdomain, $colors),
                'broadcasting_provider_id' => null,
            ]);

            $this->command?->info("Applied branding to '{$subdomain}' (3 colors, 3 icon sizes).");
        }
    }

    /**
     * @return array<int, array{id: string, logo: string, colors: array<string, string>}>
     */
    private function readManifest(): array
    {
        $path = Storage::disk('public')->path(self::MANIFEST);

        if (! is_file($path)) {
            $this->command?->warn('tenant-branding.json not found - no branding applied.');

            return [];
        }

        return json_decode(file_get_contents($path), true)['tenants'] ?? [];
    }

    /**
     * Paints a square mark from the tenant's palette and runs it through the
     * production resizer.
     *
     * @param  array<string, string>  $colors
     * @return array<string, string> size => relative path
     */
    private function generateIcons(string $subdomain, array $colors): array
    {
        $letter = strtoupper(substr($subdomain, -1));

        $image = ImageManager::gd()->create(self::SOURCE_PIXELS, self::SOURCE_PIXELS)
            ->fill($colors['primary']);

        // A corner wedge in the secondary and a centered disc in the tertiary:
        // enough that the three colors are each visibly present at 32px, which
        // is the whole point of checking the icons in a demo.
        $image->drawPolygon(function ($polygon) use ($colors) {
            $polygon->point(0, 0);
            $polygon->point(self::SOURCE_PIXELS, 0);
            $polygon->point(0, self::SOURCE_PIXELS);
            $polygon->background($colors['secondary']);
        });

        $image->drawCircle(self::SOURCE_PIXELS / 2, self::SOURCE_PIXELS / 2, function ($circle) use ($colors) {
            $circle->radius((int) (self::SOURCE_PIXELS * 0.30));
            $circle->background($colors['tertiary']);
        });

        // UploadedFile in test mode ($test = true): no upload-error checks, no
        // is_uploaded_file() - the only way to hand a file already on disk to
        // an interface whose production caller is an HTTP upload.
        $sourcePath = tempnam(sys_get_temp_dir(), 'tenant-icon-').'.png';
        $image->toPng()->save($sourcePath);

        try {
            return $this->iconResizingService->generateSizes(
                new UploadedFile($sourcePath, "{$subdomain}.png", 'image/png', null, true),
                'tenant-icons',
            );
        } finally {
            @unlink($sourcePath);
        }
    }
}
