<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

class AdminProfileTest extends TenantTestCase
{
    private function actingAsAdmin(bool $isTenantOwner = false): array
    {
        $this->actingAsTenant();
        $admin = Admin::factory()->create([
            'is_tenant_owner' => $isTenantOwner,
            'is_active' => true,
        ]);

        return [$admin, $admin->createToken('t')->plainTextToken];
    }

    private function auth(string $token): self
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$token]);
    }

    // ── Leitura ──────────────────────────────────────────────────────────

    public function test_profile_exposes_avatar_url_and_notification_email(): void
    {
        [, $token] = $this->actingAsAdmin();

        $this->auth($token)->getJson('/api/admin/me')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'id', 'name', 'email', 'is_active', 'is_super_admin', 'is_tenant_owner',
                'avatar_path', 'avatar_url', 'notification_email',
                'last_login_at', 'created_at', 'updated_at',
            ]])
            ->assertJsonPath('data.avatar_url', null)
            ->assertJsonPath('data.notification_email', null);
    }

    /**
     * Guarda de regressão: get e update montavam arrays à mão e tinham divergido
     * (um devolvia is_tenant_owner/created_at, o outro updated_at). Agora ambos
     * passam por AdminProfilePresenter — este teste falha se voltarem a divergir.
     */
    public function test_get_and_update_return_the_same_key_set(): void
    {
        [, $token] = $this->actingAsAdmin();

        $get = $this->auth($token)->getJson('/api/admin/me')->json('data');
        $patch = $this->auth($token)->patchJson('/api/admin/me', ['name' => 'Novo Nome'])->json('data');

        $this->assertSame(array_keys($get), array_keys($patch));
    }

    // ── Avatar ───────────────────────────────────────────────────────────

    public function test_admin_can_upload_an_avatar(): void
    {
        Storage::fake('public');
        [$admin, $token] = $this->actingAsAdmin();

        $response = $this->auth($token)->post('/api/admin/me/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg', 200, 200),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.avatar_url'));

        $path = $admin->fresh()->avatar_path;
        $this->assertStringStartsWith('admin-avatars/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_uploading_a_new_avatar_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        [$admin, $token] = $this->actingAsAdmin();

        $this->auth($token)->post('/api/admin/me/avatar', [
            'avatar' => UploadedFile::fake()->image('primeira.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(200);
        $firstPath = $admin->fresh()->avatar_path;

        $this->auth($token)->post('/api/admin/me/avatar', [
            'avatar' => UploadedFile::fake()->image('segunda.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(200);
        $secondPath = $admin->fresh()->avatar_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_removing_the_avatar_clears_it_and_is_idempotent(): void
    {
        Storage::fake('public');
        [$admin, $token] = $this->actingAsAdmin();

        $this->auth($token)->post('/api/admin/me/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(200);
        $path = $admin->fresh()->avatar_path;

        $this->auth($token)->deleteJson('/api/admin/me/avatar')
            ->assertStatus(200)
            ->assertJsonPath('data.avatar_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($admin->fresh()->avatar_path);

        // Segunda chamada: 200 e não erro.
        $this->auth($token)->deleteJson('/api/admin/me/avatar')
            ->assertStatus(200)
            ->assertJsonPath('data.avatar_url', null);
    }

    public function test_avatar_rejects_non_image_files(): void
    {
        Storage::fake('public');
        [, $token] = $this->actingAsAdmin();

        $this->auth($token)->post('/api/admin/me/avatar', [
            'avatar' => UploadedFile::fake()->create('doc.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    /**
     * A regra `image` do Laravel aceita SVG. Como o ficheiro é servido a partir
     * da própria origem do painel, um SVG com <script> seria XSS armazenado —
     * daí o `mimes` explícito.
     */
    public function test_avatar_rejects_svg(): void
    {
        Storage::fake('public');
        [, $token] = $this->actingAsAdmin();

        $this->auth($token)->post('/api/admin/me/avatar', [
            'avatar' => UploadedFile::fake()->create('evil.svg', 2, 'image/svg+xml'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_avatar_rejects_files_over_two_megabytes(): void
    {
        Storage::fake('public');
        [, $token] = $this->actingAsAdmin();

        $this->auth($token)->post('/api/admin/me/avatar', [
            'avatar' => UploadedFile::fake()->create('grande.jpg', 3000, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    // ── notification_email (gate por campo) ──────────────────────────────

    public function test_tenant_owner_can_set_notification_email(): void
    {
        [$owner, $token] = $this->actingAsAdmin(isTenantOwner: true);

        $this->auth($token)->patchJson('/api/admin/me', [
            'name' => $owner->name,
            'notification_email' => 'alertas@acme.test',
        ])->assertStatus(200)->assertJsonPath('data.notification_email', 'alertas@acme.test');

        $this->assertSame('alertas@acme.test', $owner->fresh()->notification_email);
    }

    public function test_tenant_owner_can_clear_notification_email(): void
    {
        [$owner, $token] = $this->actingAsAdmin(isTenantOwner: true);
        $owner->update(['notification_email' => 'alertas@acme.test']);

        $this->auth($token)->patchJson('/api/admin/me', ['notification_email' => null])
            ->assertStatus(200)
            ->assertJsonPath('data.notification_email', null);

        $this->assertNull($owner->fresh()->notification_email);
    }

    public function test_non_owner_cannot_set_notification_email(): void
    {
        [$admin, $token] = $this->actingAsAdmin(isTenantOwner: false);

        $this->auth($token)->patchJson('/api/admin/me', [
            'name' => $admin->name,
            'notification_email' => 'alertas@acme.test',
        ])->assertStatus(403);

        $this->assertNull($admin->fresh()->notification_email);
    }

    /** O gate é por CAMPO, não pela rota: um não-owner ainda pode mudar o nome. */
    public function test_non_owner_can_still_update_their_name(): void
    {
        [$admin, $token] = $this->actingAsAdmin(isTenantOwner: false);

        $this->auth($token)->patchJson('/api/admin/me', ['name' => 'Nome Novo'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Nome Novo');
    }

    public function test_notification_email_must_be_a_valid_address(): void
    {
        [, $token] = $this->actingAsAdmin(isTenantOwner: true);

        $this->auth($token)->patchJson('/api/admin/me', ['notification_email' => 'nao-e-email'])
            ->assertStatus(422);
    }

    // ── Senha ────────────────────────────────────────────────────────────

    /**
     * Afirmação sobre a tabela de tokens, não sobre um segundo request: depois
     * da primeira requisição autenticada o guard do Laravel mantém o utilizador
     * resolvido em memória, então um segundo `getJson` com outro token devolveria
     * 200 mesmo com o token já apagado — mediria o guard, não o comportamento.
     */
    public function test_changing_password_revokes_other_tokens_but_keeps_the_caller(): void
    {
        $this->actingAsTenant();
        $admin = Admin::factory()->create(['is_active' => true]);

        $callerToken = $admin->createToken('este-browser');
        $outroToken = $admin->createToken('outro-dispositivo');

        $this->auth($callerToken->plainTextToken)->patchJson('/api/admin/me/password', [
            'current_password' => 'password123',
            'password' => 'nova-senha-secreta',
            'password_confirmation' => 'nova-senha-secreta',
        ])->assertStatus(200);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $callerToken->accessToken->getKey()]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $outroToken->accessToken->getKey()]);

        // E o token de quem chamou continua a servir requests de facto.
        $this->auth($callerToken->plainTextToken)->getJson('/api/admin/me')->assertStatus(200);
    }

    public function test_changing_password_with_wrong_current_password_fails(): void
    {
        [, $token] = $this->actingAsAdmin();

        $this->auth($token)->patchJson('/api/admin/me/password', [
            'current_password' => 'senha-errada',
            'password' => 'nova-senha-secreta',
            'password_confirmation' => 'nova-senha-secreta',
        ])->assertStatus(422);
    }

    /**
     * A auditoria é imutável: um hash bcrypt gravado ali fica para sempre.
     * HasAuditLog filtra os atributos $hidden antes de escrever.
     */
    public function test_password_change_does_not_leak_the_hash_into_the_audit_log(): void
    {
        [$admin, $token] = $this->actingAsAdmin();

        $this->auth($token)->patchJson('/api/admin/me/password', [
            'current_password' => 'password123',
            'password' => 'nova-senha-secreta',
            'password_confirmation' => 'nova-senha-secreta',
        ])->assertStatus(200);

        $logs = AuditLog::where('model_id', $admin->id)->get();
        $this->assertNotEmpty($logs, 'esperava pelo menos uma linha de auditoria');

        foreach ($logs as $log) {
            $this->assertArrayNotHasKey('password', (array) ($log->old_values ?? []));
            $this->assertArrayNotHasKey('password', (array) ($log->new_values ?? []));
        }
    }
}
