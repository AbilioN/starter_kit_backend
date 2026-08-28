<?php

namespace App\Providers;

use App\Domain\AgentTools\AgentGrantStoreInterface;
use App\Domain\AgentTools\AgentToolRegistry;
use App\Domain\AgentTools\ArgumentValidatorInterface;
use App\Infrastructure\Services\JsonSchemaArgumentValidator;
use App\Infrastructure\Services\RedisAgentGrantStore;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the agent tool machinery, and holds the catalogue of handlers.
 *
 * **The handler list is explicit, not discovered.** Nothing else in this
 * codebase is tag-discovered (DomainServiceProvider binds everything by hand),
 * and here auto-discovery would mean a class becomes callable by an AI agent
 * merely by existing in a folder. Adding a tool should be a line in a diff that
 * someone reviewed.
 *
 * Being listed here only makes a handler *resolvable*. Whether it is exposed at
 * all, to which agent, how it is described and how much it may return are
 * landlord rows an operator edits — no deploy.
 */
class AgentToolServiceProvider extends ServiceProvider
{
    /**
     * @var array<int, class-string<\App\Domain\AgentTools\AgentToolInterface>>
     */
    private const HANDLERS = [
        // Being listed here only makes a handler resolvable. Whether it is
        // exposed at all, to which agent, and how it is described are landlord
        // rows an operator edits.
        \App\Application\AgentTools\CountUsersTool::class,
    ];

    public function register(): void
    {
        $this->app->bind(AgentGrantStoreInterface::class, RedisAgentGrantStore::class);
        $this->app->bind(ArgumentValidatorInterface::class, JsonSchemaArgumentValidator::class);

        $this->app->singleton(AgentToolRegistry::class, function ($app) {
            $registry = new AgentToolRegistry();

            foreach (self::HANDLERS as $handler) {
                $registry->register($app->make($handler));
            }

            return $registry;
        });
    }
}
