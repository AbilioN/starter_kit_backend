<?php

namespace Tests\Feature\AgentDocuments;

use App\Models\Admin;
use App\Models\AgentDocument;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

/**
 * Giving the assistant something to read.
 *
 * The table had two search tools and no writer but a seeder, so these are the
 * first tests of anything putting a document into it.
 */
class AgentDocumentCrudTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('docs');
        Storage::fake('local');

        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
    }

    private function adminWith(array $slugs): Admin
    {
        $admin = Admin::factory()->create(['is_super_admin' => false, 'is_active' => true]);

        $role = Role::firstOrCreate(
            ['slug' => 'librarian'],
            ['name' => 'Librarian', 'description' => 'docs', 'is_active' => true],
        );
        $role->permissions()->syncWithoutDetaching(Permission::whereIn('slug', $slugs)->pluck('id'));
        $admin->roles()->attach($role->id, [
            'assigned_at' => now(), 'assigned_by' => $admin->id, 'is_active' => true,
        ]);

        Sanctum::actingAs($admin->refresh());

        return $admin;
    }

    public function test_an_admin_can_add_a_document_by_pasting_its_text(): void
    {
        $this->adminWith(['document-manage', 'document-read']);

        $this->postJson('/api/admin/agent-documents', [
            'title' => 'Regulamento da quinta',
            'description' => 'Lotações e regras de reserva.',
            'content' => 'O salão pequeno tem lotação para 40 pessoas sentadas.',
        ])->assertCreated()->assertJsonPath('data.title', 'Regulamento da quinta');

        $this->assertSame(
            'O salão pequeno tem lotação para 40 pessoas sentadas.',
            AgentDocument::first()->content,
        );
    }

    public function test_a_document_is_internal_unless_somebody_says_otherwise(): void
    {
        // Publishing makes it readable by every end user the tenant serves, so
        // it must be a deliberate act rather than a default.
        $this->adminWith(['document-manage']);

        $this->postJson('/api/admin/agent-documents', [
            'title' => 'Margens por fornecedor',
            'content' => 'O fornecedor A cobra mais 40 por cento.',
        ])->assertCreated()->assertJsonPath('data.audience', AgentDocument::AUDIENCE_INTERNAL);
    }

    public function test_uploading_a_text_file_extracts_what_the_assistant_will_search(): void
    {
        $this->adminWith(['document-manage']);

        $file = UploadedFile::fake()->createWithContent(
            'politica.txt',
            "Reservas exigem 30 por cento de sinal.\n\n\n   Cancelamentos até 14 dias.",
        );

        $this->post('/api/admin/agent-documents', [
            'title' => 'Política de reservas',
            'file' => $file,
        ], ['Accept' => 'application/json'])->assertCreated();

        $content = AgentDocument::first()->content;

        $this->assertStringContainsString('30 por cento de sinal', $content);

        // The link to the stored file. Asserted because the first version read
        // a key the upload DTO does not have, so this was silently null and
        // the panel's paperclip never appeared.
        $document = AgentDocument::first();
        $this->assertNotNull($document->file_path);
        $this->assertTrue($document->fresh()->file_path !== '');
        // Ragged whitespace is normalised on the way in: a LIKE search must not
        // miss a phrase because the source broke it across lines.
        $this->assertStringNotContainsString("\n\n\n", $content);
    }

    public function test_a_file_with_no_readable_text_is_refused_rather_than_stored_empty(): void
    {
        // Almost always a scan. Storing it would leave an assistant that
        // confidently finds nothing in a manual the tenant believes it has.
        $this->adminWith(['document-manage']);

        $this->post('/api/admin/agent-documents', [
            'title' => 'Manual digitalizado',
            'file' => UploadedFile::fake()->createWithContent('scan.txt', '   '),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, AgentDocument::count());
    }

    public function test_a_document_with_neither_text_nor_file_is_refused(): void
    {
        $this->adminWith(['document-manage']);

        $this->postJson('/api/admin/agent-documents', ['title' => 'Vazio'])->assertStatus(422);
    }

    public function test_an_executable_is_not_an_acceptable_document(): void
    {
        // UploadFileRequest has no mime allowlist at all; this endpoint asks
        // the extractor what it supports and validates against that answer.
        $this->adminWith(['document-manage']);

        $this->post('/api/admin/agent-documents', [
            'title' => 'Not a manual',
            // Prose in a .php file is detected as text/plain, so the mime rule
            // alone would let this through — which is why the extension is
            // checked too.
            'file' => UploadedFile::fake()->createWithContent('payload.php', 'just some words'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_reading_needs_the_read_permission(): void
    {
        $this->adminWith(['document-manage']);

        $this->getJson('/api/admin/agent-documents')->assertStatus(403);
    }

    public function test_writing_needs_more_than_the_read_permission(): void
    {
        // The split exists because a write decides `audience`.
        $this->adminWith(['document-read']);

        $this->postJson('/api/admin/agent-documents', [
            'title' => 'Tentativa',
            'content' => 'algo',
        ])->assertStatus(403);
    }

    public function test_the_list_never_carries_the_body_of_a_manual(): void
    {
        $this->adminWith(['document-read', 'document-manage']);

        AgentDocument::create([
            'title' => 'Manual',
            'content' => 'um texto muito longo que não pertence a um ecrã de lista',
            'audience' => AgentDocument::AUDIENCE_INTERNAL,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/admin/agent-documents')->assertOk();

        $this->assertArrayNotHasKey('content', $response->json('data.0'));
        // But asking for one on purpose returns it, for the edit screen.
        $id = $response->json('data.0.id');
        $this->getJson("/api/admin/agent-documents/{$id}")
            ->assertOk()
            ->assertJsonPath('data.content', 'um texto muito longo que não pertence a um ecrã de lista');
    }

    public function test_a_document_can_be_published_and_then_withdrawn(): void
    {
        $this->adminWith(['document-read', 'document-manage']);

        $document = AgentDocument::create([
            'title' => 'FAQ',
            'content' => 'Perguntas frequentes.',
            'audience' => AgentDocument::AUDIENCE_INTERNAL,
            'is_active' => true,
        ]);

        $this->postJson("/api/admin/agent-documents/{$document->id}", [
            'title' => 'FAQ',
            'audience' => AgentDocument::AUDIENCE_PUBLISHED,
        ])->assertOk()->assertJsonPath('data.audience', AgentDocument::AUDIENCE_PUBLISHED);

        $this->postJson("/api/admin/agent-documents/{$document->id}", [
            'title' => 'FAQ',
            'audience' => AgentDocument::AUDIENCE_INTERNAL,
        ])->assertOk()->assertJsonPath('data.audience', AgentDocument::AUDIENCE_INTERNAL);

        // Editing without resending the text must not wipe it.
        $this->assertSame('Perguntas frequentes.', $document->fresh()->content);
    }

    public function test_a_legacy_encoded_file_is_converted_rather_than_rejected_by_mysql(): void
    {
        // utf8mb4 rejects invalid byte sequences and SQLite accepts them, so
        // without this the request 500s in production with a green suite —
        // and a Portuguese tenant exporting from a legacy system is the most
        // likely person to hit it. "até" in ISO-8859-1 is 61 74 e9.
        $this->adminWith(['document-manage']);

        $latin1 = mb_convert_encoding('Cancelamentos até 14 dias perdem o sinal.', 'ISO-8859-1', 'UTF-8');

        $this->post('/api/admin/agent-documents', [
            'title' => 'Politica legada',
            'file' => UploadedFile::fake()->createWithContent('politica.txt', $latin1),
        ], ['Accept' => 'application/json'])->assertCreated();

        $content = AgentDocument::first()->content;

        $this->assertTrue(mb_check_encoding($content, 'UTF-8'), 'Stored content must be valid UTF-8.');
        $this->assertStringContainsString('até', $content);
    }

    public function test_content_over_the_cap_is_refused_rather_than_silently_halved(): void
    {
        // The endpoint accepts 20 MB and the column keeps 400k characters.
        // Truncating and answering 201 left a tenant with a manual they
        // believe is complete and an assistant blind to half of it.
        $this->adminWith(['document-manage']);

        $this->postJson('/api/admin/agent-documents', [
            'title' => 'Manual enorme',
            'content' => str_repeat('a', 400_001),
        ])->assertStatus(422);

        $this->assertSame(0, AgentDocument::count());
    }

    public function test_replacing_a_file_releases_the_one_it_replaced(): void
    {
        $this->adminWith(['document-manage']);

        $this->post('/api/admin/agent-documents', [
            'title' => 'Politica',
            'file' => UploadedFile::fake()->createWithContent('v1.txt', 'primeira versao'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = AgentDocument::first();
        $this->assertSame(1, \App\Models\File::where('uploadable_id', $document->id)->count());

        $this->post("/api/admin/agent-documents/{$document->id}", [
            'title' => 'Politica',
            'file' => UploadedFile::fake()->createWithContent('v2.txt', 'segunda versao'),
        ], ['Accept' => 'application/json'])->assertOk();

        // One file, not two. The old one used to survive forever inside the
        // tenant's storage quota and inside every backup.
        $this->assertSame(1, \App\Models\File::where('uploadable_id', $document->id)->count());
        $this->assertStringContainsString('segunda versao', $document->fresh()->content);
    }

    public function test_deleting_a_document_releases_its_file(): void
    {
        $this->adminWith(['document-manage']);

        $this->post('/api/admin/agent-documents', [
            'title' => 'Temporario',
            'file' => UploadedFile::fake()->createWithContent('doc.txt', 'algum texto'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = AgentDocument::first();
        $this->assertSame(1, \App\Models\File::where('uploadable_id', $document->id)->count());

        $this->deleteJson("/api/admin/agent-documents/{$document->id}")->assertOk();

        $this->assertSame(0, \App\Models\File::where('uploadable_id', $document->id)->count());
    }

    public function test_it_can_be_deleted(): void
    {
        $this->adminWith(['document-manage']);

        $document = AgentDocument::create([
            'title' => 'Obsoleto',
            'content' => 'texto',
            'audience' => AgentDocument::AUDIENCE_INTERNAL,
            'is_active' => true,
        ]);

        $this->deleteJson("/api/admin/agent-documents/{$document->id}")->assertOk();

        $this->assertSame(0, AgentDocument::count());
    }
}
