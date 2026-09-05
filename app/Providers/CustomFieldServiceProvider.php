<?php

namespace App\Providers;

use App\Domain\CustomFields\CustomFieldHostRegistry;
use App\Domain\CustomFields\FieldSchemaPlanner;
use App\Domain\CustomFields\FieldTypeRegistry;
use App\Domain\CustomFields\Hosts\AppointmentsHost;
use App\Domain\CustomFields\Hosts\UsersHost;
use App\Domain\CustomFields\Types\TextType;
use App\Domain\Services\SchemaIntrospectorInterface;
use App\Domain\Services\SchemaReconcilerInterface;
use App\Infrastructure\Services\MySqlSchemaIntrospector;
use App\Infrastructure\Services\MySqlSchemaReconciler;
use Illuminate\Support\ServiceProvider;

/**
 * Tenant-defined fields: the hosts, the types, and the two halves of the
 * reconciler.
 *
 * Both registries are assembled BY HAND, the way AgendaServiceProvider
 * assembles the card's action menu and for the same reason: nothing should
 * become a target for runtime DDL, or a storage decision a tenant can pick,
 * merely by existing in a folder. Adding a host or a type is a line in a diff
 * someone reviewed.
 */
class CustomFieldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CustomFieldHostRegistry::class, function () {
            $registry = new CustomFieldHostRegistry;

            // appointments is the only real business entity the kit has — and
            // the one whose migration says outright that "every vertical
            // brings its own noun".
            $registry->register(new AppointmentsHost);

            // Registered 2026-09-05, once UserController actually implemented
            // the create/update/delete that routes/api.php had been
            // advertising. Until then there was no write path a value could
            // arrive through, and registering it would have let a tenant spend
            // an ALTER and one of that table's scarce index slots on a column
            // nothing could fill.
            $registry->register(new UsersHost);

            return $registry;
        });

        $this->app->singleton(FieldTypeRegistry::class, function () {
            $registry = new FieldTypeRegistry;

            // One type in part 1, on purpose: `text` is the only one that
            // exercises the decision the rest of the feature is built on —
            // filterability choosing the column TYPE, not just the index.
            // The other six arrive with the authoring UI that can draw them.
            $registry->register(new TextType);

            return $registry;
        });

        $this->app->singleton(FieldSchemaPlanner::class);

        // MySQL-only, and there is deliberately no SQLite sibling: it would be
        // a code path production never runs, written so a suite could feel
        // covered. Feature tests bind a fake here instead — the shape
        // RunBackupTest already uses for mysqldump.
        $this->app->bind(SchemaIntrospectorInterface::class, MySqlSchemaIntrospector::class);
        $this->app->bind(SchemaReconcilerInterface::class, MySqlSchemaReconciler::class);
    }
}
