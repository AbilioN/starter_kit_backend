<?php

namespace Tests\Feature\GodAdmin;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Livewire\SubscriptionPlans\Form;
use App\Models\GodAdmin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TenantTestCase;

class SubscriptionPlanIconTest extends TenantTestCase
{
    private GodAdmin $godAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();

        Storage::fake('public');

        $this->godAdmin = GodAdmin::create([
            'name' => 'Root',
            'email' => 'root@starterkit.test',
            'password' => 'secret-password',
        ]);
    }

    public function test_uploading_an_icon_generates_three_sizes(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('name', 'Starter')
            ->set('slug', 'starter')
            ->set('isPublic', true)
            ->set('tertiaryColor', '#FF6600')
            ->set('iconUpload', UploadedFile::fake()->image('icon.png', 800, 800))
            ->call('save')
            ->assertRedirect('/god/subscription-plans');

        $plan = app(SubscriptionPlanRepositoryInterface::class)->findBySlug('starter');

        $this->assertTrue($plan->isPublic);
        $this->assertSame('#FF6600', $plan->tertiaryColor);
        $this->assertArrayHasKey('small', $plan->iconPaths);
        $this->assertArrayHasKey('medium', $plan->iconPaths);
        $this->assertArrayHasKey('large', $plan->iconPaths);

        Storage::disk('public')->assertExists($plan->iconPaths['small']);
        Storage::disk('public')->assertExists($plan->iconPaths['medium']);
        Storage::disk('public')->assertExists($plan->iconPaths['large']);
    }

    public function test_a_plan_defaults_to_private_and_no_icon(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('name', 'Internal')
            ->set('slug', 'internal')
            ->call('save')
            ->assertRedirect('/god/subscription-plans');

        $plan = app(SubscriptionPlanRepositoryInterface::class)->findBySlug('internal');

        $this->assertFalse($plan->isPublic);
        $this->assertEmpty($plan->iconPaths);
    }
}
