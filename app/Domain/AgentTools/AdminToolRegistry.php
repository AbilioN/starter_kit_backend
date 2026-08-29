<?php

namespace App\Domain\AgentTools;

/**
 * The tools a tenant ADMIN's agent may call.
 *
 * A distinct type rather than a second instance of AgentToolRegistry, so the
 * two catalogues cannot be confused at an injection point. See UserToolRegistry
 * for why the separation is a type and not a flag.
 */
final class AdminToolRegistry extends AgentToolRegistry {}
