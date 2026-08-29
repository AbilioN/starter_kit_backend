<?php

namespace App\Domain\AgentTools;

use stdClass;

/**
 * Makes a handler's JSON Schema safe to put on the wire.
 *
 * PHP has one array type and JSON has two. An empty `properties => []` encodes
 * as `[]`, and OpenAI rejects the whole request:
 *
 *   Invalid schema for function 'my_profile': [] is not of type 'object'
 *
 * Which means **every tool that takes no arguments breaks the turn** — and it
 * breaks it for all the other tools too, since one malformed entry fails the
 * whole call. Found by running it, not by reading it: nothing in PHP-land
 * distinguishes the two shapes.
 *
 * Applied at the wire boundary rather than in the handlers, so a tool author
 * keeps writing ordinary arrays and cannot get this wrong.
 */
final class ToolSchema
{
    public static function forWire(array $schema): array
    {
        if (array_key_exists('properties', $schema) && $schema['properties'] === []) {
            $schema['properties'] = new stdClass();
        }

        return $schema;
    }
}
