<?php

namespace App\Application\AgentTools\User;

use App\Domain\AgentTools\AgentToolInterface;

/**
 * Marks a handler whose result can only ever contain the acting user's own data.
 *
 * End users have no roles and no permissions — permissions describe who may
 * administer the tenant, and end users administer nothing. So a user tool is
 * not *authorized*; it is built so that it cannot return anyone else's data:
 *
 *  - identity comes only from `$context->actorId`, never from an argument;
 *  - any identifier the model passes is checked against the actor BEFORE use.
 *
 * The interface cannot enforce that — PHP has no way to prove a query is
 * scoped. What it does is make the claim explicit at the type level, so a
 * handler that appears in the user registry without it is visible in review,
 * and so the required self-scoping test has something to enumerate.
 *
 * See docs/15 §5.
 */
interface SelfScopedTool extends AgentToolInterface {}
