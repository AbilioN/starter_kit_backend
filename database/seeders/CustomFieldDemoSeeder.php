<?php

namespace Database\Seeders;

use App\Application\UseCases\CustomField\CreateFieldDefinitionUseCase;
use App\Models\CustomFieldDefinition;
use Illuminate\Database\Seeder;

/**
 * One realistic tenant-defined field, so the feature has something to be
 * looked at through.
 *
 * "Contract number" on appointments: filterable (so it exercises the
 * VARCHAR-plus-index path rather than the cheap TEXT one), coloured, with an
 * icon, placed in the card's badge slot, and named in two languages — which
 * between them cover every decision the definition row carries.
 *
 * Idempotent. Re-running must not spend a second `num`: handles are never
 * reused, so a seeder that created a duplicate on every run would quietly
 * consume a tenant's plan quota and its index budget.
 */
class CustomFieldDemoSeeder extends Seeder
{
    private const HOST = 'appointments';

    public function run(): void
    {
        $exists = CustomFieldDefinition::query()
            ->where('host', self::HOST)
            ->whereHas('labels', fn ($q) => $q->where('label', 'Contract number'))
            ->exists();

        if ($exists) {
            $this->say('Demo custom field already present; leaving it alone.');

            return;
        }

        app(CreateFieldDefinitionUseCase::class)->execute(
            hostKey: self::HOST,
            fieldType: 'text',
            labels: [
                'en' => ['label' => 'Contract number', 'help_text' => 'The signed contract this visit belongs to'],
                'pt' => ['label' => 'Nº de contrato', 'help_text' => 'O contrato assinado a que esta visita pertence'],
            ],
            isFilterable: true,
            presentation: [
                'slot' => 'card.badges',
                'section' => 'general',
                'icon' => 'mdi-file-document',
                'colour' => '#185FA5',
                // Both colours, always. The server does not know whether the
                // reader is in light or dark mode.
                'colour_dark' => '#7EB6E8',
                'position' => 1,
            ],
        );

        $this->say('Seeded the demo custom field (pending — run fields:reconcile to create the column).');
    }

    /** Seeders are invoked as `(new Seeder)->run()` during provisioning, where $command is null. */
    private function say(string $message): void
    {
        $this->command?->info($message);
    }
}
