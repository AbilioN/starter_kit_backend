<?php

namespace Tests\Feature\Template;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Template;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Tests\TenantTestCase;

class TemplatePreviewTest extends TenantTestCase
{
    private Admin $admin;

    public function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();
        Storage::fake('local');

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);

        $superAdmin = Admin::where('is_super_admin', true)->first();
        $this->admin = Admin::factory()->create(['email' => 'previewadmin@test.com', 'is_active' => true]);
        $role = Role::where('slug', 'admin')->first();
        $role->permissions()->sync(Permission::all()->pluck('id'));
        $this->admin->roles()->attach($role->id, ['assigned_at' => now(), 'assigned_by' => $superAdmin->id]);
    }

    private function token(): string
    {
        return $this->admin->createToken('t')->plainTextToken;
    }

    public function test_preview_of_a_text_template_resolves_placeholders(): void
    {
        $template = Template::create([
            'name' => 'Welcome', 'type' => 'text_email', 'body_format' => 'text',
            'body' => 'Hi {first_name} {last_name}!',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->postJson("/api/admin/templates/{$template->id}/preview");

        $response->assertStatus(200)
            ->assertJsonPath('data.content_type', 'text/plain')
            ->assertJsonPath('data.content', 'Hi Jean Sample!');
    }

    public function test_preview_of_an_html_template_returns_html(): void
    {
        $template = Template::create([
            'name' => 'HTML Welcome', 'type' => 'html_email', 'body_format' => 'html',
            'body' => '<p>Hi {first_name}</p>',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->postJson("/api/admin/templates/{$template->id}/preview");

        $response->assertStatus(200)
            ->assertJsonPath('data.content_type', 'text/html')
            ->assertJsonPath('data.content', '<p>Hi Jean</p>');
    }

    public function test_preview_of_a_no_background_pdf_template_returns_pdf_bytes(): void
    {
        $template = Template::create([
            'name' => 'Certificate', 'type' => 'pdf', 'body_format' => 'html',
            'body' => '<h1>Certificate for {first_name}</h1>',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->postJson("/api/admin/templates/{$template->id}/preview");

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_preview_of_an_underlay_pdf_template_stamps_entries_on_the_background(): void
    {
        $template = Template::create([
            'name' => 'Form', 'type' => 'pdf', 'body_format' => 'positions',
            'body' => json_encode([
                ['page' => 1, 'x' => 20, 'y' => 30, 'text' => '{first_name} {last_name}'],
            ]),
        ]);

        $token = $this->token();
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/admin/templates/{$template->id}/background", ['file' => $this->realPdf()]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/admin/templates/{$template->id}/preview");

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_preview_fills_empty_catalogue_fields_with_guillemets(): void
    {
        $template = Template::create([
            'name' => 'Uses Unknown Field', 'type' => 'text_email', 'body_format' => 'text',
            'body' => 'Value: {zip}',
        ]);

        // StubMergeContext always returns a real zip value - this proves
        // the "fill empty with «label»" behaviour specifically, the same
        // way it's proven in isolation for PlaceholderResolverService,
        // by swapping in a MergeContext whose zip actually IS empty.
        $this->app->instance(\App\Domain\Services\MergeContextInterface::class, new class implements \App\Domain\Services\MergeContextInterface {
            public function fields(): array
            {
                return [['key' => 'zip', 'label' => 'ZIP Code']];
            }

            public function values(int|string $recordId): array
            {
                return ['{zip}' => ''];
            }
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->postJson("/api/admin/templates/{$template->id}/preview");

        $response->assertStatus(200)->assertJsonPath('data.content', 'Value: «ZIP Code»');
    }

    private function realPdf(): UploadedFile
    {
        $mpdf = new Mpdf();
        $mpdf->WriteHTML('<h1>Background Form</h1>');

        $path = storage_path('app/sqlite/preview-fixture-'.uniqid().'.pdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));
        $mpdf->Output($path, Destination::FILE);

        return new UploadedFile($path, 'form.pdf', 'application/pdf', null, true);
    }
}
