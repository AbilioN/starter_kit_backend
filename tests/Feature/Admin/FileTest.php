<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $adminWithPermissions;

    public function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);

        $this->superAdmin = Admin::where('is_super_admin', true)->first();

        $this->adminWithPermissions = Admin::factory()->create([
            'name' => 'File Admin',
            'email' => 'fileadmin@test.com',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $role = Role::where('slug', 'admin')->first();
        $role->permissions()->sync(Permission::all()->pluck('id'));
        $this->adminWithPermissions->roles()->attach($role->id, [
            'assigned_at' => now(),
            'assigned_by' => $this->superAdmin->id,
        ]);
    }

    /** @test */
    public function admin_can_upload_a_file(): void
    {
        $token = $this->adminWithPermissions->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/admin/files', [
                'file' => $file,
                'folder' => 'photos',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'original_name', 'mime_type', 'size', 'size_human', 'url']]);
    }

    /** @test */
    public function admin_can_list_files(): void
    {
        $token = $this->adminWithPermissions->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->create('doc.pdf', 512, 'application/pdf');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/admin/files', ['file' => $file]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/files');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'pagination']);
    }

    /** @test */
    public function admin_can_delete_a_file(): void
    {
        $token = $this->adminWithPermissions->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $upload = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/admin/files', ['file' => $file]);

        $fileId = $upload->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/admin/files/{$fileId}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function unauthorized_admin_cannot_upload(): void
    {
        $noPermAdmin = Admin::factory()->create(['is_active' => true, 'is_super_admin' => false]);
        $token = $noPermAdmin->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('photo.jpg');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/admin/files', ['file' => $file])
            ->assertStatus(403);
    }
}
