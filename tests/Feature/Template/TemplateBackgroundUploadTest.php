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

class TemplateBackgroundUploadTest extends TenantTestCase
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
        $this->admin = Admin::factory()->create(['email' => 'bgadmin@test.com', 'is_active' => true]);
        $role = Role::where('slug', 'admin')->first();
        $role->permissions()->sync(Permission::all()->pluck('id'));
        $this->admin->roles()->attach($role->id, ['assigned_at' => now(), 'assigned_by' => $superAdmin->id]);
    }

    private function token(): string
    {
        return $this->admin->createToken('t')->plainTextToken;
    }

    private function realPdf(int $pages = 1): UploadedFile
    {
        $mpdf = new Mpdf();
        for ($i = 1; $i <= $pages; $i++) {
            if ($i > 1) {
                $mpdf->AddPage();
            }
            $mpdf->WriteHTML("<h1>Page {$i}</h1>");
        }

        $path = storage_path('app/sqlite/upload-fixture-'.uniqid().'.pdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));
        $mpdf->Output($path, Destination::FILE);

        return new UploadedFile($path, 'form.pdf', 'application/pdf', null, true);
    }

    public function test_uploading_a_single_page_pdf_attaches_one_background_file(): void
    {
        $template = Template::create(['name' => 'Form', 'type' => 'pdf', 'body_format' => 'positions', 'body' => '[]']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->postJson("/api/admin/templates/{$template->id}/background", ['file' => $this->realPdf(1)]);

        $response->assertStatus(201);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(0, $response->json('data.0.sort'));
    }

    public function test_uploading_a_multi_page_pdf_splits_into_one_file_per_page(): void
    {
        $template = Template::create(['name' => 'Multi-page Form', 'type' => 'pdf', 'body_format' => 'positions', 'body' => '[]']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->postJson("/api/admin/templates/{$template->id}/background", ['file' => $this->realPdf(3)]);

        $response->assertStatus(201);
        $this->assertCount(3, $response->json('data'));
        $this->assertSame([0, 1, 2], collect($response->json('data'))->pluck('sort')->all());
    }

    public function test_a_second_upload_appends_after_existing_pages(): void
    {
        $template = Template::create(['name' => 'Form', 'type' => 'pdf', 'body_format' => 'positions', 'body' => '[]']);
        $token = $this->token();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/admin/templates/{$template->id}/background", ['file' => $this->realPdf(2)])
            ->assertStatus(201);

        $second = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/admin/templates/{$template->id}/background", ['file' => $this->realPdf(1)]);

        $second->assertStatus(201);
        $this->assertSame(2, $second->json('data.0.sort'));
    }

    public function test_listing_background_files_returns_them_sorted(): void
    {
        $template = Template::create(['name' => 'Form', 'type' => 'pdf', 'body_format' => 'positions', 'body' => '[]']);
        $token = $this->token();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/admin/templates/{$template->id}/background", ['file' => $this->realPdf(2)]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/admin/templates/{$template->id}/background");

        $response->assertStatus(200);
        $this->assertSame([0, 1], collect($response->json('data'))->pluck('sort')->all());
    }

    public function test_a_background_file_can_be_deleted(): void
    {
        $template = Template::create(['name' => 'Form', 'type' => 'pdf', 'body_format' => 'positions', 'body' => '[]']);
        $token = $this->token();

        $upload = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/admin/templates/{$template->id}/background", ['file' => $this->realPdf(1)]);

        $fileId = $upload->json('data.0.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson("/api/admin/templates/{$template->id}/background/{$fileId}")
            ->assertStatus(200);

        // File uses SoftDeletes - the row stays, just marked deleted.
        $this->assertSoftDeleted('files', ['id' => $fileId]);
    }

    public function test_uploading_a_non_pdf_file_is_rejected(): void
    {
        $template = Template::create(['name' => 'Form', 'type' => 'pdf', 'body_format' => 'positions', 'body' => '[]']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->postJson("/api/admin/templates/{$template->id}/background", [
                'file' => UploadedFile::fake()->image('not-a-pdf.jpg'),
            ]);

        $response->assertStatus(422);
    }

    public function test_uploading_a_background_to_a_non_pdf_template_is_rejected(): void
    {
        $template = Template::create(['name' => 'Just an email', 'type' => 'text_email', 'body_format' => 'text', 'body' => 'hi']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->postJson("/api/admin/templates/{$template->id}/background", ['file' => $this->realPdf(1)]);

        $response->assertStatus(422);
    }
}
