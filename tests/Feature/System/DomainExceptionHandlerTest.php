<?php

namespace Tests\Feature\System;

use App\Domain\Exceptions\PlanLimitExceededException;
use App\Models\Admin;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

/**
 * The global renderers added to bootstrap/app.php on 2026-09-04.
 *
 * Until then `withExceptions` was empty, so a domain exception that no
 * controller happened to catch became a 500. Three controllers were fixed one
 * at a time by adding the catch; the two newest — AgendaController and
 * AppointmentController — call AuthorizeActionUseCase with no catch at all,
 * so a permission denial on the agenda answered 500 in production.
 */
class DomainExceptionHandlerTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('handler');
    }

    public function test_an_uncaught_domain_authorization_exception_is_a_403(): void
    {
        // NOT a super admin, and holding no roles. is_super_admin makes
        // AdminFactory return a SudoAdmin, which AuthorizeActionUseCase skips
        // outright — a test that forgets this passes vacuously.
        $admin = Admin::factory()->create(['is_super_admin' => false, 'is_active' => true]);
        Sanctum::actingAs($admin);

        // AgendaController::index() authorizes 'appointment-read' and has no
        // try/catch. Before the handler existed this assertion read 500.
        $this->getJson('/api/admin/agenda?view=week')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message']);
    }

    public function test_a_plan_limit_exception_is_a_402_from_anywhere(): void
    {
        Route::middleware('api')->get('/api/testing/plan-limit', function () {
            throw new PlanLimitExceededException('This tenant\'s plan allows a maximum of 3.');
        });

        $this->getJson('/api/testing/plan-limit')
            ->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This tenant\'s plan allows a maximum of 3.');
    }

    public function test_a_custom_field_conflict_is_a_409(): void
    {
        Route::middleware('api')->get('/api/testing/cf-conflict', function () {
            throw new \App\Domain\Exceptions\CustomFieldConflictException;
        });

        $this->getJson('/api/testing/cf-conflict')->assertStatus(409);
    }

    public function test_a_non_json_web_request_is_left_to_the_default_renderer(): void
    {
        // routes/god.php runs in the `web` group and its Livewire screens must
        // keep their HTML error page. The renderers return null for anything
        // that is neither api/* nor expecting JSON, which hands the exception
        // back to Laravel.
        Route::middleware('web')->get('/testing-web/denied', function () {
            throw new \App\Domain\Exceptions\AuthorizationException('nope');
        });

        $response = $this->get('/testing-web/denied');

        $this->assertNotSame(
            'application/json',
            explode(';', (string) $response->headers->get('content-type'))[0],
        );
    }
}
