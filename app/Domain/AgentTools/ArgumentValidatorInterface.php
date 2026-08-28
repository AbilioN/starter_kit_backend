<?php

namespace App\Domain\AgentTools;

interface ArgumentValidatorInterface
{
    /**
     * Validates the model's arguments against the effective JSON Schema and
     * returns them coerced.
     *
     * @throws \App\Domain\AgentTools\Exceptions\AgentToolFailure validation_error,
     *         carrying a message the model can act on — it gets one chance to
     *         correct itself, and "invalid arguments" tells it nothing.
     */
    public function validate(array $arguments, array $schema): array;
}
