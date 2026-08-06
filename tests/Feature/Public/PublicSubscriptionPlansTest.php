<?php

namespace Tests\Feature\Public;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use Tests\TenantTestCase;

class PublicSubscriptionPlansTest extends TenantTestCase
{
    public function test_index_lists_only_public_and_active_plans(): void
    {
        $repo = app(SubscriptionPlanRepositoryInterface::class);

        $repo->create(name: 'Starter', slug: 'starter', priceCents: 1900, features: [], limits: [], isActive: true, isPublic: true);
        $repo->create(name: 'Partner Deal', slug: 'partner-deal', priceCents: 500, features: [], limits: [], isActive: true, isPublic: false);
        $repo->create(name: 'Retired Plan', slug: 'retired', priceCents: 2900, features: [], limits: [], isActive: false, isPublic: true);

        $response = $this->getJson('/api/public/subscription-plans');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains('starter', $slugs);
        $this->assertNotContains('partner-deal', $slugs);
        $this->assertNotContains('retired', $slugs);
    }

    public function test_show_returns_a_public_plan_by_slug(): void
    {
        app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Starter', slug: 'starter', priceCents: 1900, features: ['chat' => true], limits: [], isActive: true, isPublic: true
        );

        $response = $this->getJson('/api/public/subscription-plans/starter');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'starter')
            ->assertJsonPath('data.price_cents', 1900);
    }

    public function test_show_returns_404_for_a_private_plan(): void
    {
        app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Partner Deal', slug: 'partner-deal', priceCents: 500, features: [], limits: [], isActive: true, isPublic: false
        );

        $this->getJson('/api/public/subscription-plans/partner-deal')->assertStatus(404);
    }

    public function test_show_returns_404_for_an_unknown_slug(): void
    {
        $this->getJson('/api/public/subscription-plans/does-not-exist')->assertStatus(404);
    }
}
