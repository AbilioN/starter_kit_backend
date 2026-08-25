<?php

namespace Database\Seeders;

use App\Application\UseCases\Template\UploadTemplateBackgroundUseCase;
use App\Domain\Services\PdfRenderServiceInterface;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Models\File;
use App\Models\Template;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Landlord seeder that writes into each demo tenant's database. Invoke:
 * php artisan db:seed --class=DemoPdfTemplateSeeder
 *
 * Builds a working "underlay" PDF template — a generic two-page form as the
 * background, plus positioned entries stamped over it — so the positions
 * editor has something real to open. Every other template kind can be tried
 * by typing into a box; this one cannot: it needs a background PDF attached
 * to a saved template before the canvas will draw anything at all, which is
 * a fair amount of setup to do by hand just to see whether dragging a field
 * works.
 *
 * The background is GENERATED rather than committed as a fixture, through
 * the same PdfRenderService the app renders documents with. That keeps the
 * label coordinates and the entry coordinates in one file, where they can be
 * read side by side — a checked-in PDF would leave the numbers below
 * matching an artifact nobody can diff.
 *
 * The entries deliberately cover the parts of the entry language that are
 * hard to discover from an empty canvas: plain text, a {placeholder}, comb
 * spacing over boxes, a conditional entry, a slice, and a preview-only
 * highlight. See App\Domain\ValueObjects\TemplateEntry.
 *
 * Not idempotent by accident: re-running deletes the template and its
 * background files first. Uploading a background APPENDS pages (one file per
 * page, by design), so a second run would otherwise leave a four-page form.
 */
class DemoPdfTemplateSeeder extends Seeder
{
    private const TENANTS = ['tenant-a', 'tenant-b', 'tenant-c'];

    private const NAME = 'Generic Form (PDF positions demo)';

    /**
     * Label column and value column, in millimetres from the page's
     * top-left corner. Both the background labels below and the entries
     * further down are laid out from these, so they cannot drift apart.
     */
    private const LABEL_X = 20;

    private const VALUE_X = 65;

    /** label => y, page 1 */
    private const FIELDS = [
        'Given name' => 50,
        'Family name' => 60,
        'E-mail' => 70,
        'Postal code' => 80,
        'Date' => 90,
    ];

    private const COMB_X = 20;

    private const COMB_Y = 115;

    private const COMB_BOXES = 8;

    private const COMB_BOX_WIDTH = 8;

    public function __construct(
        private readonly TenantConnectionSwitcherInterface $tenantConnection,
        private readonly PdfRenderServiceInterface $pdfRenderer,
        private readonly UploadTemplateBackgroundUseCase $uploadBackground,
    ) {}

    public function run(): void
    {
        foreach (self::TENANTS as $subdomain) {
            $tenant = Tenant::where('subdomain', $subdomain)->first();

            if (! $tenant) {
                $this->command?->warn("Skipping '{$subdomain}': tenant not provisioned yet.");

                continue;
            }

            $this->tenantConnection->run(
                $tenant->database_name,
                fn () => $this->seedTenant($subdomain),
            );
        }
    }

    private function seedTenant(string $subdomain): void
    {
        $this->deleteExisting();

        $template = Template::create([
            'name' => self::NAME,
            'type' => 'pdf',
            'body_format' => 'positions',
            'body' => json_encode($this->entries(), JSON_PRETTY_PRINT),
            'description' => 'Two-page generic form with positioned entries — for trying string and position editing.',
            'is_active' => true,
            'locale' => 'en',
            'translation_group_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $backgroundPath = $this->writeBackgroundToTempFile();

        try {
            $this->uploadBackground->execute(
                $template->id,
                // Test mode ($test = true): the file is already on disk, and
                // this is the only way to hand one to an interface whose
                // production caller is an HTTP upload.
                new UploadedFile($backgroundPath, 'generic-form.pdf', 'application/pdf', null, true),
            );
        } finally {
            @unlink($backgroundPath);
        }

        $this->command?->info("[{$subdomain}] '".self::NAME."' ready (2 background pages, ".count($this->entries()).' entries).');
    }

    private function deleteExisting(): void
    {
        $existing = Template::where('name', self::NAME)->get();

        foreach ($existing as $template) {
            $files = File::where('uploadable_type', Template::class)
                ->where('uploadable_id', $template->id)
                ->get();

            foreach ($files as $file) {
                Storage::disk($file->disk)->delete($file->path);
                $file->delete();
            }

            $template->delete();
        }
    }

    /**
     * The pre-printed form the entries are stamped over. Labels only — the
     * values come from the entries, which is the whole point of the mode.
     */
    private function writeBackgroundToTempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'generic-form-').'.pdf';
        file_put_contents($path, $this->pdfRenderer->renderDocument($this->backgroundHtml()));

        return $path;
    }

    private function backgroundHtml(): string
    {
        $box = fn (float $x, float $y, string $style, string $text) => '<div style="position:absolute; left:'.$x.'mm; top:'.$y.'mm; '.$style.'">'.$text.'</div>';

        $label = 'font-size:9pt; color:#555';
        $rule = 'border-bottom:0.3mm solid #999; width:120mm; height:0';

        $html = $box(self::LABEL_X, 18, 'font-size:16pt; font-weight:bold', 'GENERIC FORM');
        $html .= $box(self::LABEL_X, 27, 'font-size:9pt; color:#777', 'Page 1 of 2 — applicant details');
        $html .= $box(self::LABEL_X, 34, $rule, '');

        foreach (self::FIELDS as $text => $y) {
            $html .= $box(self::LABEL_X, $y, $label, $text);
            // The rule sits just under the baseline so a stamped value looks
            // like it was written on the line, not through it.
            $html .= $box(self::VALUE_X, $y + 5, 'border-bottom:0.2mm solid #bbb; width:110mm; height:0', '');
        }

        $html .= $box(self::LABEL_X, self::COMB_Y - 7, $label, 'Postal code, one digit per box');

        for ($i = 0; $i < self::COMB_BOXES; $i++) {
            $html .= $box(
                self::COMB_X + ($i * self::COMB_BOX_WIDTH),
                self::COMB_Y,
                'border:0.2mm solid #999; width:'.(self::COMB_BOX_WIDTH - 1).'mm; height:8mm',
                '',
            );
        }

        $html .= $box(self::LABEL_X, 140, $label, 'Priority handling');
        $html .= $box(self::LABEL_X, 250, 'font-size:8pt; color:#999', 'Generated for demonstration purposes.');

        // Page 2.
        $html .= '<pagebreak />';
        $html .= $box(self::LABEL_X, 18, 'font-size:16pt; font-weight:bold', 'DECLARATION');
        $html .= $box(self::LABEL_X, 27, 'font-size:9pt; color:#777', 'Page 2 of 2 — signature');
        $html .= $box(self::LABEL_X, 34, $rule, '');
        $html .= $box(self::LABEL_X, 50, 'font-size:10pt; width:170mm', 'I confirm that the details on the previous page are correct.');
        $html .= $box(self::LABEL_X, 90, $label, 'Signed by');
        $html .= $box(self::LABEL_X, 100, 'border-bottom:0.2mm solid #999; width:80mm; height:0', '');
        $html .= $box(self::LABEL_X, 115, $label, 'Date');
        $html .= $box(self::LABEL_X, 125, 'border-bottom:0.2mm solid #999; width:80mm; height:0', '');

        return $html;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entries(): array
    {
        $entries = [];
        $placeholders = [
            'Given name' => '{first_name}',
            'Family name' => '{last_name}',
            'E-mail' => '{email}',
            'Postal code' => '{zip}',
            'Date' => '{date}',
        ];

        foreach (self::FIELDS as $label => $y) {
            $entries[] = [
                'x' => self::VALUE_X,
                'y' => $y,
                'text' => $placeholders[$label],
                'page' => 1,
                'size' => 10,
            ];
        }

        // Comb: one glyph per printed box. +1.5mm on each axis centres the
        // digits inside the boxes drawn above rather than on their corner.
        $entries[] = [
            'x' => self::COMB_X + 1.5,
            'y' => self::COMB_Y + 1.5,
            'text' => '{zip}',
            'page' => 1,
            'size' => 12,
            // Pitch = glyph width + space, and a 12pt monospace glyph is
            // ~2.5mm — so this lands each digit one box further along
            // rather than drifting left across the row.
            'space' => self::COMB_BOX_WIDTH - 2.5,
            'boxes' => self::COMB_BOXES,
        ];

        // Conditional: drawn only when the resolved field equals the literal.
        // The stub merge context returns zip = 75001, so this one shows and
        // is the quickest way to see `if` working — change the literal and it
        // disappears.
        $entries[] = [
            'x' => self::VALUE_X,
            'y' => 140,
            'text' => 'YES',
            'page' => 1,
            'size' => 10,
            'bold' => true,
            'color' => '#0a7',
            // Without a width mPDF picks its own for an absolutely
            // positioned div, and "YES" in bold wrapped after two letters.
            // width also sets white-space:nowrap in buildEntryStyle().
            'width' => 40,
            'if' => '{zip}:75001',
        ];

        // Preview-only red, so an author can find one entry on a busy form.
        // Never affects real output.
        $entries[] = [
            'x' => self::LABEL_X,
            'y' => 95,
            'text' => '{first_name} {last_name}',
            'page' => 2,
            'size' => 11,
            'highlight' => true,
        ];

        // slice takes a substring of the resolved value — here the day and
        // month of a dd/mm/yyyy date.
        $entries[] = [
            'x' => self::LABEL_X,
            'y' => 120,
            'text' => '{date}',
            'page' => 2,
            'size' => 11,
            'slice' => '0:5',
        ];

        $entries[] = [
            'x' => self::LABEL_X,
            'y' => 250,
            'text' => 'Issued by {company}',
            'page' => 2,
            'size' => 8,
            'color' => '#999',
        ];

        return $entries;
    }
}
