<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * A believable week of field work in Porto and Lisbon, for showing the product.
 *
 * Kept apart from AgendaSeeder on purpose: that one seeds the vocabulary a real
 * tenant starts with and belongs in provisioning. This one invents data, and
 * nothing that invents data should be able to run by accident against a
 * customer's database.
 *
 * Three things make it demo-worthy rather than merely present:
 *
 * **Real coordinates.** Every stop is a place that exists, so the route the
 * optimiser draws is one a person from either city can sanity-check by eye —
 * which is the whole point when the audience is deciding whether to believe the
 * feature.
 *
 * **Two cities, two rounds.** Porto on one day, Lisbon on another, each a
 * coherent day's driving. It demonstrates grouping (by city, by rep) and gives
 * the route optimiser two independent problems instead of one nonsensical
 * Porto-to-Lisbon-and-back.
 *
 * **Dates relative to today.** A demo pinned to fixed dates is empty the week
 * after it is written.
 */
class AgendaDemoSeeder extends Seeder
{
    /** Marks the rows this seeder owns, so re-running replaces rather than duplicates. */
    private const DEMO_FLAG = 'agenda_demo';

    public function run(): void
    {
        $types = AppointmentType::pluck('id', 'slug');
        $statuses = AppointmentStatus::pluck('id', 'slug');

        if ($types->isEmpty() || $statuses->isEmpty()) {
            $this->command?->warn('  Run AgendaSeeder first — this needs the types and statuses.');

            return;
        }

        // Only the rows this seeder created. A demo re-run must never touch a
        // real appointment someone entered while showing the product.
        Appointment::where('metadata->source', self::DEMO_FLAG)->forceDelete();

        $admins = Admin::where('is_active', true)->orderBy('created_at')->take(2)->get();
        $rep = fn (int $i) => $admins->get($i % max(1, $admins->count()))?->id;

        // The Monday of a week that still lies ahead. Anchoring on the current
        // week means that from Friday onwards the demo opens on appointments
        // that already happened — which is exactly the wrong first impression
        // when the audience is deciding whether to trust the product.
        $monday = CarbonImmutable::now()->startOfWeek();

        if (CarbonImmutable::now()->dayOfWeekIso >= 5) {
            $monday = $monday->addWeek();
        }

        $created = 0;

        foreach ($this->rounds($monday) as $round) {
            foreach ($round['stops'] as $index => $stop) {
                Appointment::create([
                    'appointment_type_id' => $types[$stop['type']],
                    'appointment_status_id' => $statuses[$stop['status']],
                    'title' => $stop['title'],
                    'description' => $stop['description'] ?? null,
                    'starts_at' => $round['date']->setTime($stop['hour'], $stop['minute'] ?? 0),
                    'ends_at' => $round['date']->setTime($stop['hour'], $stop['minute'] ?? 0)
                        ->addMinutes($stop['minutes'] ?? 60),
                    'assigned_admin_id' => $rep($round['rep']),
                    'location_address' => $stop['address'],
                    'location_postcode' => $stop['postcode'],
                    'location_city' => $round['city'],
                    'location_lat' => $stop['lat'],
                    'location_lng' => $stop['lng'],
                    'metadata' => [
                        'source' => self::DEMO_FLAG,
                        'phone' => $stop['phone'],
                    ],
                ]);

                $created++;
            }
        }

        $this->command?->info("  {$created} demo appointments across Porto and Lisbon, week of {$monday->toDateString()}.");
    }

    /**
     * Two rounds, each a plausible day: stops deliberately entered in booking
     * order rather than in driving order, so the optimiser has something real
     * to fix when it is demonstrated.
     */
    private function rounds(CarbonImmutable $monday): array
    {
        return [
            [
                'city' => 'Porto',
                'date' => $monday->addDays(1),   // Tuesday
                'rep' => 0,
                'stops' => [
                    [
                        'title' => 'Visita — Clínica Boavista',
                        'description' => 'Renovação do contrato anual. Levar proposta impressa.',
                        'address' => 'Av. da Boavista 1837', 'postcode' => '4100-133',
                        'lat' => 41.1580, 'lng' => -8.6291,
                        'hour' => 9, 'minutes' => 60, 'phone' => '+351 912 345 678',
                        'type' => 'visit', 'status' => 'confirmed',
                    ],
                    [
                        'title' => 'Reunião — Grupo Bolhão',
                        'description' => 'Apresentação do módulo de faturação.',
                        'address' => 'Rua Formosa 214', 'postcode' => '4000-248',
                        'lat' => 41.1495, 'lng' => -8.6069,
                        'hour' => 11, 'minutes' => 90, 'phone' => '+351 913 222 111',
                        'type' => 'meeting', 'status' => 'confirmed',
                    ],
                    [
                        'title' => 'Visita — Matosinhos Logística',
                        'description' => 'Levantamento de requisitos do armazém.',
                        'address' => 'Rua Brito Capelo 120', 'postcode' => '4450-073',
                        'lat' => 41.1826, 'lng' => -8.6889,
                        'hour' => 14, 'minutes' => 90, 'phone' => '+351 914 555 333',
                        'type' => 'visit', 'status' => 'scheduled',
                    ],
                    [
                        'title' => 'Visita — Foz Retail',
                        'address' => 'Av. do Brasil 480', 'postcode' => '4150-153',
                        'lat' => 41.1500, 'lng' => -8.6800,
                        'hour' => 16, 'minutes' => 60, 'phone' => '+351 915 777 888',
                        'type' => 'visit', 'status' => 'scheduled',
                    ],
                    [
                        'title' => 'Follow-up — Palácio da Bolsa',
                        'description' => 'Confirmar a assinatura enviada por email.',
                        'address' => 'Rua de Ferreira Borges', 'postcode' => '4050-253',
                        'lat' => 41.1414, 'lng' => -8.6153,
                        'hour' => 17, 'minutes' => 30, 'phone' => '+351 916 101 202',
                        'type' => 'callback', 'status' => 'scheduled',
                    ],
                ],
            ],
            [
                'city' => 'Lisboa',
                'date' => $monday->addDays(3),   // Thursday
                'rep' => 1,
                'stops' => [
                    [
                        'title' => 'Reunião — Sede Marquês de Pombal',
                        'description' => 'Comité de compras. Duas horas reservadas.',
                        'address' => 'Av. da Liberdade 245', 'postcode' => '1250-143',
                        'lat' => 38.7255, 'lng' => -9.1500,
                        'hour' => 9, 'minutes' => 120, 'phone' => '+351 921 111 222',
                        'type' => 'meeting', 'status' => 'confirmed',
                    ],
                    [
                        'title' => 'Visita — Parque das Nações Tech',
                        'description' => 'Demonstração no local, sala reservada.',
                        'address' => 'Alameda dos Oceanos 45', 'postcode' => '1990-392',
                        'lat' => 38.7683, 'lng' => -9.0947,
                        'hour' => 12, 'minutes' => 60, 'phone' => '+351 922 333 444',
                        'type' => 'visit', 'status' => 'confirmed',
                    ],
                    [
                        'title' => 'Visita — Belém Turismo',
                        'address' => 'Praça do Império', 'postcode' => '1400-206',
                        'lat' => 38.6979, 'lng' => -9.2065,
                        'hour' => 15, 'minutes' => 60, 'phone' => '+351 923 555 666',
                        'type' => 'visit', 'status' => 'scheduled',
                    ],
                    [
                        'title' => 'Visita — Alcântara Distribuição',
                        'address' => 'Rua da Cozinha Económica 11', 'postcode' => '1300-149',
                        'lat' => 38.7050, 'lng' => -9.1750,
                        'hour' => 16, 'minutes' => 45, 'phone' => '+351 924 777 999',
                        'type' => 'visit', 'status' => 'scheduled',
                    ],
                    [
                        'title' => 'Tarefa — Preparar proposta Oeiras',
                        'description' => 'Consolidar os números antes da visita de segunda.',
                        'address' => 'Av. dos Combatentes', 'postcode' => '2780-088',
                        'lat' => 38.6969, 'lng' => -9.3096,
                        'hour' => 18, 'minutes' => 30, 'phone' => '+351 925 010 020',
                        'type' => 'task', 'status' => 'scheduled',
                    ],
                ],
            ],
            [
                // A third, lighter day so the week is not two blocks with a hole
                // between them — and so the month view has something on a
                // different day when someone clicks around.
                'city' => 'Porto',
                'date' => $monday->addDays(4),   // Friday
                'rep' => 0,
                'stops' => [
                    [
                        'title' => 'Visita — Casa da Música (evento)',
                        'description' => 'Stand no evento sectorial. Dia inteiro.',
                        'address' => 'Av. da Boavista 604', 'postcode' => '4149-071',
                        'lat' => 41.1585, 'lng' => -8.6306,
                        'hour' => 9, 'minutes' => 480, 'phone' => '+351 917 303 404',
                        'type' => 'meeting', 'status' => 'confirmed',
                    ],
                    [
                        'title' => 'Follow-up — Campanhã',
                        'address' => 'Largo da Estação', 'postcode' => '4300-173',
                        'lat' => 41.1494, 'lng' => -8.5852,
                        'hour' => 18, 'minutes' => 30, 'phone' => '+351 918 505 606',
                        'type' => 'callback', 'status' => 'scheduled',
                    ],
                ],
            ],
        ];
    }
}
