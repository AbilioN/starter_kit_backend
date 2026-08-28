<?php

namespace App\Infrastructure\Services;

use App\Domain\AgentTools\ArgumentValidatorInterface;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;

/**
 * Validates a model's tool arguments against a deliberate SUBSET of JSON Schema.
 *
 * The subset is: `type: object`, `properties` with a scalar or array `type`,
 * `format: date`, `minLength`/`maxLength`, `minimum`/`maximum`, `enum`,
 * `required`, and `additionalProperties: false`. That covers every schema a
 * starter tool needs and nothing else.
 *
 * A full validator would mean a new dependency, which is a decision worth
 * making deliberately rather than in passing. The important property is not
 * completeness but that **the schema sent to OpenAI is the one enforced here** —
 * one source, no drift. If a tool ever needs `oneOf` or nested objects, add the
 * library then; do not quietly widen this by hand.
 *
 * Messages are written for the model to act on. "Invalid arguments" wastes the
 * one correction attempt it gets.
 */
class JsonSchemaArgumentValidator implements ArgumentValidatorInterface
{
    public function validate(array $arguments, array $schema): array
    {
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        if (($schema['additionalProperties'] ?? true) === false) {
            $unknown = array_diff(array_keys($arguments), array_keys($properties));

            if ($unknown !== []) {
                throw AgentToolFailure::validation(sprintf(
                    'Unknown argument(s): %s. Accepted: %s.',
                    implode(', ', $unknown),
                    implode(', ', array_keys($properties)) ?: 'none',
                ));
            }
        }

        foreach ($required as $name) {
            if (! array_key_exists($name, $arguments) || $arguments[$name] === null) {
                throw AgentToolFailure::validation("Missing required argument: {$name}.");
            }
        }

        $clean = [];

        foreach ($properties as $name => $rules) {
            if (! array_key_exists($name, $arguments) || $arguments[$name] === null) {
                continue;
            }

            $clean[$name] = $this->validateOne($name, $arguments[$name], (array) $rules);
        }

        return $clean;
    }

    private function validateOne(string $name, mixed $value, array $rules): mixed
    {
        $value = $this->checkType($name, $value, $rules['type'] ?? 'string');

        if (($rules['format'] ?? null) === 'date') {
            $this->checkDate($name, (string) $value);
        }

        if (isset($rules['enum']) && ! in_array($value, $rules['enum'], true)) {
            throw AgentToolFailure::validation(sprintf(
                '%s must be one of: %s.', $name, implode(', ', array_map('strval', $rules['enum'])),
            ));
        }

        if (is_string($value)) {
            if (isset($rules['minLength']) && mb_strlen($value) < $rules['minLength']) {
                throw AgentToolFailure::validation("{$name} must be at least {$rules['minLength']} characters.");
            }

            if (isset($rules['maxLength']) && mb_strlen($value) > $rules['maxLength']) {
                throw AgentToolFailure::validation("{$name} must be at most {$rules['maxLength']} characters.");
            }
        }

        if (is_int($value) || is_float($value)) {
            if (isset($rules['minimum']) && $value < $rules['minimum']) {
                throw AgentToolFailure::validation("{$name} must be at least {$rules['minimum']}.");
            }

            if (isset($rules['maximum']) && $value > $rules['maximum']) {
                throw AgentToolFailure::validation("{$name} must be at most {$rules['maximum']}.");
            }
        }

        return $value;
    }

    private function checkType(string $name, mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => is_string($value)
                ? $value
                : throw AgentToolFailure::validation("{$name} must be a string."),
            // A model routinely sends "7" for an integer. Accepting a numeric
            // string is not laxness — rejecting it would spend the correction
            // attempt on something that carried the right value all along.
            'integer' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)
                ? (int) $value
                : throw AgentToolFailure::validation("{$name} must be a whole number."),
            'number' => is_numeric($value)
                ? $value + 0
                : throw AgentToolFailure::validation("{$name} must be a number."),
            'boolean' => is_bool($value)
                ? $value
                : throw AgentToolFailure::validation("{$name} must be true or false."),
            'array' => is_array($value)
                ? $value
                : throw AgentToolFailure::validation("{$name} must be a list."),
            default => $value,
        };
    }

    private function checkDate(string $name, string $value): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw AgentToolFailure::validation(
                "{$name} must be a date in YYYY-MM-DD format (for example 2026-08-01)."
            );
        }
    }
}
