<?php

namespace App\Domain\AgentTools\Exceptions;

use RuntimeException;

/**
 * Every way a tool call can be refused, with the three things the contract
 * needs: a stable code, an HTTP status, and whether the agent should carry on.
 *
 * **`recoverable` is the contract, not the status code.** It tells the worker
 * whether to hand the error to the model and continue the turn, or abandon it.
 * Keeping the decision here rather than in a status-code table on the worker
 * means one side owns it — and it is the side that knows why the call failed.
 *
 * The `message` is read by the model and, indirectly, heard by the user. It
 * must never carry a stack trace, a SQL fragment, an internal id, or anything
 * the actor could not already see in the interface.
 */
final class AgentToolFailure extends RuntimeException
{
    public function __construct(
        // Not `$code`: Exception already declares that property, and PHP will
        // not let a subclass redeclare it as readonly.
        public readonly string $errorCode,
        public readonly int $status,
        public readonly bool $recoverable,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function workerKeyInvalid(): self
    {
        return new self('worker_key_invalid', 401, false, 'Unauthorized.');
    }

    /**
     * Missing and expired are deliberately one code: telling a caller which it
     * was reveals whether a token ever existed.
     */
    public static function grantInvalid(): self
    {
        return new self('grant_invalid', 401, false, 'This session is no longer valid.');
    }

    public static function callBudgetExceeded(): self
    {
        return new self('call_budget_exceeded', 429, true,
            'No further lookups are available for this request; answer with what you have.');
    }

    public static function toolNotAllowed(): self
    {
        return new self('tool_not_allowed', 403, true,
            'That tool is not available in this conversation.');
    }

    public static function toolNotFound(): self
    {
        return new self('tool_not_found', 404, true,
            'That tool does not exist or is currently inactive.');
    }

    public static function readOnlySession(): self
    {
        return new self('read_only_session', 403, true,
            'This is a read-only support session; that action is unavailable.');
    }

    public static function validation(string $message): self
    {
        return new self('validation_error', 422, true, $message);
    }

    public static function permissionDenied(?string $slug): self
    {
        return new self('permission_denied', 403, true, $slug === null
            ? 'This account does not have permission for that.'
            : "This account does not have the '{$slug}' permission.");
    }

    public static function handlerError(): self
    {
        return new self('handler_error', 500, true,
            'That lookup failed. You can try a different one.');
    }

    public function toArray(): array
    {
        return [
            'ok' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'recoverable' => $this->recoverable,
            ],
        ];
    }
}
