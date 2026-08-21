<?php

namespace Tests\Feature\Backup;

use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Livewire\Backups\Index as BackupsIndex;
use App\Livewire\InfrastructureProviders\Form as ProviderForm;
use App\Livewire\SubscriptionPlans\Form as PlanForm;
use App\Models\GodAdmin;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * The operator-facing half: a backup destination is curated like any other
 * provider, and the plan is where period and capacity are sold.
 */
class BackupGodAdminTest extends TenantTestCase
{
    private GodAdmin $godAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();

        $this->godAdmin = GodAdmin::create([
            'name' => 'Root',
            'email' => 'root@starterkit.test',
            'password' => 'secret-password',
        ]);
    }

    public function test_godadmin_can_create_a_backup_destination(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(ProviderForm::class)
            ->set('type', 'backup')
            ->set('name', 'Offsite B2')
            ->set('configKey', 'k')
            ->set('configSecret', 's')
            ->set('configBucket', 'offsite-backups')
            ->set('configPathPrefix', 'kit')
            ->call('save')
            ->assertHasNoErrors();

        $providers = app(InfrastructureProviderRepositoryInterface::class)->findByType('backup');

        $this->assertCount(1, $providers);
        $this->assertSame('offsite-backups', $providers[0]->config['bucket']);
        $this->assertSame('kit', $providers[0]->config['path_prefix']);
    }

    /**
     * A backup destination with no bucket looks configured in the panel and
     * writes nowhere — the form has to refuse it.
     */
    public function test_a_backup_destination_requires_a_bucket(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(ProviderForm::class)
            ->set('type', 'backup')
            ->set('name', 'Broken')
            ->set('configKey', 'k')
            ->set('configSecret', 's')
            ->call('save')
            ->assertHasErrors('configBucket');
    }

    public function test_plan_form_writes_the_backup_policy_into_limits(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(PlanForm::class)
            ->set('name', 'Business')
            ->set('slug', 'business')
            ->set('priceCents', 9900)
            ->set('backupFrequencyHours', '168')
            ->set('backupRetentionDays', 90)
            ->set('backupMaxTotalMb', '20480')
            ->call('save')
            ->assertHasNoErrors();

        $plan = app(SubscriptionPlanRepositoryInterface::class)->findBySlug('business');

        $this->assertSame(168, $plan->limits['backup_frequency_hours']);
        $this->assertSame(90, $plan->limits['backup_retention_days']);
        $this->assertSame(20480, $plan->limits['backup_max_total_mb']);
        $this->assertTrue($plan->features['backup']);
    }

    public function test_a_blank_frequency_means_never(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(PlanForm::class)
            ->set('name', 'Free')
            ->set('slug', 'free')
            ->set('backupFrequencyHours', '')
            ->set('backupMaxTotalMb', '')
            ->call('save')
            ->assertHasNoErrors();

        $plan = app(SubscriptionPlanRepositoryInterface::class)->findBySlug('free');

        $this->assertNull($plan->limits['backup_frequency_hours']);
        $this->assertNull($plan->limits['backup_max_total_mb']);
    }

    public function test_the_backups_page_renders_for_the_landlord(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(BackupsIndex::class)
            ->assertOk()
            ->assertSee('Backups');
    }
}
