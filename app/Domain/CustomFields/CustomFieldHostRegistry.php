<?php

namespace App\Domain\CustomFields;

/**
 * The hosts this build knows about, in an explicit list.
 *
 * Explicit for the same reason AppointmentActionRegistry is: nothing should
 * become a target for runtime DDL merely by existing in a folder. Adding a
 * host is a line in a diff someone reviewed.
 */
final class CustomFieldHostRegistry
{
    /** @var array<string, CustomFieldHostInterface> */
    private array $hosts = [];

    public function register(CustomFieldHostInterface $host): void
    {
        $this->hosts[$host->key()] = $host;
    }

    public function get(string $key): ?CustomFieldHostInterface
    {
        return $this->hosts[$key] ?? null;
    }

    /**
     * The host, or an exception naming what was asked for.
     *
     * Used on every path that is about to touch schema: an unknown host must
     * stop the request, not fall through to a null and an ALTER against a
     * table name assembled from nothing.
     */
    public function require(string $key): CustomFieldHostInterface
    {
        return $this->hosts[$key] ?? throw new \InvalidArgumentException(
            "Unknown custom field host [{$key}]. Registered: ".(implode(', ', $this->keys()) ?: 'none').'.'
        );
    }

    /** @return array<int, CustomFieldHostInterface> */
    public function all(): array
    {
        return array_values($this->hosts);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->hosts);
    }
}
