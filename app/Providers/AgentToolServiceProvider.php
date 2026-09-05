<?php

namespace App\Providers;

use App\Domain\AgentTools\AdminToolRegistry;
use App\Domain\AgentTools\AgentGrantStoreInterface;
use App\Domain\AgentTools\ArgumentValidatorInterface;
use App\Domain\AgentTools\UserToolRegistry;
use App\Infrastructure\Services\JsonSchemaArgumentValidator;
use App\Infrastructure\Services\RedisAgentGrantStore;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the agent tool machinery, and holds both catalogues of handlers.
 *
 * **Both lists are explicit, not discovered.** Nothing else in this codebase is
 * tag-discovered, and here auto-discovery would mean a class becomes callable by
 * an AI agent merely by existing in a folder. Adding a tool should be a line in
 * a diff that someone reviewed.
 *
 * The two registries are separate types reached through separate routes, so the
 * admin executor cannot resolve a user tool and vice versa — the boundary is
 * structural rather than a conditional that could be got wrong (docs/15 §4).
 */
class AgentToolServiceProvider extends ServiceProvider
{
    /**
     * Curated: being listed here only makes a handler resolvable. Whether it is
     * exposed at all, to which agent, and how it is described are landlord rows
     * an operator edits.
     *
     * @var array<int, class-string<\App\Domain\AgentTools\AgentToolInterface>>
     */
    private const ADMIN_HANDLERS = [
        \App\Application\AgentTools\CountUsersTool::class,
        // Answers questions about the fields THIS workspace invented, which is
        // the one thing no amount of general knowledge can cover. It reads
        // through ProjectCustomFieldsUseCase so a field hidden from the
        // actor's roles is not described to them by the assistant either.
        \App\Application\AgentTools\ListCustomFieldsTool::class,
    ];

    /**
     * Static: identical for every user of every tenant, with no catalogue row
     * and nothing for an operator to curate. This list IS the user's tool set.
     *
     * Every handler here must be self-scoped — it takes identity only from the
     * grant, and validates any identifier the model passes against the actor
     * before using it. That is what replaces the permission check an admin tool
     * gets, so a new entry here needs its self-scoping test (docs/15 §7).
     *
     * @var array<int, class-string<\App\Domain\AgentTools\AgentToolInterface>>
     */
    private const USER_HANDLERS = [
        \App\Application\AgentTools\User\MyProfileTool::class,
        \App\Application\AgentTools\User\MyChatsTool::class,
        \App\Application\AgentTools\User\MyUnreadCountTool::class,
        \App\Application\AgentTools\User\MyRecentMessagesTool::class,
        \App\Application\AgentTools\User\MyNotificationsTool::class,
        // Tenant-published documents. Not self-scoped and deliberately so: a
        // tenant publishes these FOR its users, which is a different category
        // from another user's data (docs/15 §6).
        \App\Application\AgentTools\User\ListDocumentsTool::class,
        \App\Application\AgentTools\User\SearchDocumentsTool::class,
    ];

    public function register(): void
    {
        $this->app->bind(AgentGrantStoreInterface::class, RedisAgentGrantStore::class);
        $this->app->bind(ArgumentValidatorInterface::class, JsonSchemaArgumentValidator::class);

        $this->app->singleton(AdminToolRegistry::class, function ($app) {
            $registry = new AdminToolRegistry();

            foreach (self::ADMIN_HANDLERS as $handler) {
                $registry->register($app->make($handler));
            }

            return $registry;
        });

        $this->app->singleton(UserToolRegistry::class, function ($app) {
            $registry = new UserToolRegistry();

            foreach (self::USER_HANDLERS as $handler) {
                $registry->register($app->make($handler));
            }

            return $registry;
        });
    }
}
