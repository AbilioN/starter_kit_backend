<?php

namespace Database\Seeders;

use App\Models\AgentProfile;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Landlord seeder, not tenant. Invoke directly, after SubscriptionPlanSeeder:
 * php artisan db:seed --class=AgentProfileSeeder
 *
 * Seeds the AI agent catalog and assigns it per plan. Nothing else creates
 * agent profiles - they are otherwise curated one by one in the GodAdmin UI -
 * so an environment without this seeder has an empty `agent_profiles` table,
 * which means SyncAgentProfilesForTenantUseCase activates no Assistant rows,
 * which means the chat widget offers no agent to talk to. The AI pipeline is
 * then fully wired and completely invisible: the worker is up, the queue is
 * empty, and nothing looks broken.
 *
 * `model` is per profile on purpose - it is what ProcessOpenAIRequest sends,
 * so a cheap model can back the everyday agent while a stronger one backs the
 * analyst, without a tenant seeing either name. Leaving it null falls back to
 * the worker's OPENAI_MODEL.
 *
 * The plan assignment is the product decision, not a detail: `free` gets no
 * agents (matching its `features.ai_agent = false`), `pro` gets the two
 * general-purpose ones, `enterprise` gets all three. A profile assigned to a
 * plan whose features say ai_agent is off would still sync into the tenant -
 * the two are read by different code paths - so they are kept consistent here.
 *
 * Idempotent: profiles are matched by name, and plan assignment is a sync().
 * Assigning does NOT reach tenants by itself - a tenant picks up the change on
 * its next plan sync (TenantSubscriptionPlanSeeder, or a plan change through
 * the app). The prompts/models here are read live at send time and are never
 * copied into a tenant, so editing one takes effect immediately.
 */
class AgentProfileSeeder extends Seeder
{
    private const PROFILES = [
        [
            'name' => 'Aria',
            'description' => 'General-purpose assistant for everyday questions.',
            'avatar' => null,
            'model' => 'gpt-4o-mini',
            'plans' => ['pro', 'enterprise'],
            'system_prompt' => <<<'PROMPT'
                You are Aria, a helpful assistant inside a company's admin panel.
                Answer briefly and practically, in the language the user writes in.
                You can see only the conversation you are part of - if you are asked
                about data you cannot see, say so and suggest where in the panel it
                lives instead of guessing.
                PROMPT,
        ],
        [
            'name' => 'Max',
            'description' => 'Support agent - triages issues and drafts replies to customers.',
            'avatar' => null,
            'model' => 'gpt-4o-mini',
            'plans' => ['pro', 'enterprise'],
            'system_prompt' => <<<'PROMPT'
                You are Max, a customer-support specialist. When given a customer
                problem, reply with: a one-line summary, the most likely cause, and a
                short draft answer the agent can send. Keep the draft polite and free
                of internal jargon. Ask for the missing detail when the report is too
                vague to act on - do not invent order numbers, dates or names.
                PROMPT,
        ],
        [
            'name' => 'Nova',
            'description' => 'Data analyst - reads numbers and explains what changed.',
            'avatar' => null,
            'model' => 'gpt-4o',
            'plans' => ['enterprise'],
            'system_prompt' => <<<'PROMPT'
                You are Nova, a data analyst. Given figures or a metric, explain what
                changed, the most plausible driver, and what to check next. Lead with
                the conclusion, then the reasoning. State the uncertainty when the
                numbers do not support a firm answer - a confident wrong read costs
                more than an honest "not enough data".
                PROMPT,
        ],
    ];

    public function run(): void
    {
        foreach (self::PROFILES as $profile) {
            $agent = AgentProfile::updateOrCreate(
                ['name' => $profile['name']],
                [
                    'description' => $profile['description'],
                    'avatar' => $profile['avatar'],
                    'system_prompt' => $profile['system_prompt'],
                    'model' => $profile['model'],
                    'is_active' => true,
                ],
            );

            $planIds = SubscriptionPlan::whereIn('slug', $profile['plans'])->pluck('id')->all();
            $agent->subscriptionPlans()->sync($planIds);

            $plans = implode(', ', $profile['plans']);
            $this->command?->info("Agent '{$profile['name']}' ({$profile['model']}) available on: {$plans}.");
        }
    }
}
