<?php

namespace Database\Seeders;

use App\Application\AgentTools\CountUsersTool;
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
    public function run(): void
    {
        $tool = AgentTool::updateOrCreate(
            ['name' => 'count_users'],
            [
                'handler' => CountUsersTool::class,
                'description' => 'Count the users in this workspace, optionally filtered by the date they signed up.',
                'max_rows' => 1,
                'is_active' => true,
                'is_mutating' => false,
            ],
        );

        $profiles = AgentProfile::where('is_active', true)->get();

        foreach ($profiles as $profile) {
            $profile->agentTools()->syncWithoutDetaching([$tool->id]);
        }

        $this->command?->info("count_users attached to {$profiles->count()} active agent profile(s).");
    }
}
