<?php

namespace App\Domain\AgentTools;

/**
 * The tools a tenant's END USER's agent may call.
 *
 * Separate from the admin registry by type, not by a flag, and reached through
 * a separate route. With one shared catalogue, "a user must not reach an admin
 * tool" would be a conditional — and a conditional can be got wrong. Here the
 * user executor has no way to resolve an admin tool at all: it holds a
 * different object.
 *
 * Its contents are **static**, fixed in code and identical for every user of
 * every tenant. Curation exists so the party paying can shape what it pays for,
 * and the end user is not the customer — the tenant is. See docs/15 §3.
 */
final class UserToolRegistry extends AgentToolRegistry {}
