<?php

namespace Database\Seeders;

use App\Application\AgentTools\CountUsersTool;
use App\Application\AgentTools\ListCustomFieldsTool;
use App\Models\AgentProfile;
use App\Models\AgentTool;
use Illuminate\Database\Seeder;

/**
 * Seeds the read-only starter catalogue and offers it to every active agent
 * profile.
 *
 * Idempotent: safe to re-run, and it never detaches a tool an operator removed
 * on purpose — attaching is additive, because the pivot is the operator's
 * surface, not the seeder's.
 */
class AgentToolSeeder extends Seeder
{
    /**
     * Registering a handler only makes it resolvable; a row here is what
     * exposes it. The description is the load-bearing part — it is what steers
     * the model towards the right tool — so it is written for the model rather
     * than for a developer.
     */
    private const CATALOGUE = [
        [
            'name' => 'count_users',
            'handler' => CountUsersTool::class,
            'description' => 'Count the users in this workspace, optionally filtered by the date they signed up.',
            'max_rows' => 1,
        ],
        [
            'name' => 'list_custom_fields',
            'handler' => ListCustomFieldsTool::class,
            'description' => 'List the custom fields this workspace has defined on its records — their names, '
                .'which kind of record they belong to, what type they are, whether they can be filtered, and '
                .'whether they are ready to use. Call this before answering anything about the extra fields, '
                .'columns or attributes this workspace tracks: they differ per workspace and are not '
                .'knowable otherwise.',
            'max_rows' => 100,
        ],
    ];

    public function run(): void
    {
        $profiles = AgentProfile::where('is_active', true)->get();

        foreach (self::CATALOGUE as $entry) {
            $tool = AgentTool::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'handler' => $entry['handler'],
                    'description' => $entry['description'],
                    'max_rows' => $entry['max_rows'],
                    'is_active' => true,
                    'is_mutating' => false,
                ],
            );

            // Additive: never detaches a tool an operator removed on purpose,
            // because the pivot is the operator's surface rather than this
            // seeder's.
            foreach ($profiles as $profile) {
                $profile->agentTools()->syncWithoutDetaching([$tool->id]);
            }

            $this->command?->info("{$entry['name']} attached to {$profiles->count()} active agent profile(s).");
        }
    }
}
