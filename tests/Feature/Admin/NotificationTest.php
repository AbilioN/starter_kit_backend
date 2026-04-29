<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Notifications\AdminActionNotification;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);

        $this->superAdmin = Admin::where('is_super_admin', true)->first();
    }

    /** @test */
    public function admin_can_list_their_notifications(): void
    {
        $this->superAdmin->notify(new AdminActionNotification('Test', 'Test message'));

        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data', 'pagination']);
    }

    /** @test */
    public function admin_can_get_unread_count(): void
    {
        $this->superAdmin->notify(new AdminActionNotification('Test', 'Test message'));

        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1);
    }

    /** @test */
    public function admin_can_mark_notification_as_read(): void
    {
        $this->superAdmin->notify(new AdminActionNotification('Test', 'Test message'));

        $notificationId = $this->superAdmin->notifications()->first()->id;
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/admin/notifications/{$notificationId}/read")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(0, $this->superAdmin->unreadNotifications()->count());
    }

    /** @test */
    public function admin_can_mark_all_notifications_as_read(): void
    {
        $this->superAdmin->notify(new AdminActionNotification('Test 1', 'Message 1'));
        $this->superAdmin->notify(new AdminActionNotification('Test 2', 'Message 2'));

        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/admin/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(0, $this->superAdmin->unreadNotifications()->count());
    }
}
