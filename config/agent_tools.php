<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Worker key — and the feature's kill switch
    |--------------------------------------------------------------------------
    |
    | The shared secret the AI worker presents on every tool call. An EMPTY key
    | disables the feature outright: the endpoint 404s and no grant is ever
    | minted, so a payload leaving this installation is byte-identical to one
    | from before agent tools existed.
    |
    | Empty is the shipped default, deliberately. Whether an AI agent may read a
    | tenant's data is the operator's decision, not something that should arrive
    | switched on with a deploy — the same reasoning that ships error tracking
    | with an empty DSN.
    |
    */

    'worker_key' => env('AGENT_TOOLS_WORKER_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Grant
    |--------------------------------------------------------------------------
    |
    | How long a one-turn credential stays valid. Long enough to cover
    | max_rounds model calls plus their tool calls, short enough that a token
    | found in a log later is worthless. The grant is deleted when the reply
    | lands, so the normal lifetime is far shorter than this.
    |
    */

    'grant_ttl' => (int) env('AGENT_TOOLS_GRANT_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Budgets
    |--------------------------------------------------------------------------
    |
    | max_tool_calls bounds one turn's spend on lookups; max_rounds bounds how
    | many times the model may come back asking for more. Both are sent to the
    | worker in the payload rather than configured there, so one installation
    | cannot drift from the other.
    |
    */

    'max_rounds' => (int) env('AGENT_TOOLS_MAX_ROUNDS', 3),
    'max_tool_calls' => (int) env('AGENT_TOOLS_MAX_CALLS', 6),

    /*
    |--------------------------------------------------------------------------
    | Result caps
    |--------------------------------------------------------------------------
    |
    | A model will happily ask for "all users". Rows are capped per tool (the
    | catalogue row may lower this, never raise it) and the serialized result is
    | capped in bytes as a backstop against a few very wide rows. Both caps are
    | REPORTED to the model: a silent truncation makes it reason confidently
    | over partial data, which is worse than an error.
    |
    */

    'max_rows' => (int) env('AGENT_TOOLS_MAX_ROWS', 50),
    'max_result_bytes' => (int) env('AGENT_TOOLS_MAX_RESULT_BYTES', 24000),

    /*
    |--------------------------------------------------------------------------
    | Callback endpoint
    |--------------------------------------------------------------------------
    |
    | Sent to the worker inside the grant, rather than configured on the worker,
    | so this installation's internal topology stays a server-side decision.
    |
    | Points at `webserver` (nginx), NOT `app`. The app container is php-fpm and
    | speaks FastCGI on 9000, not HTTP — an endpoint of http://app never
    | connects, and the failure looks like a dead backend rather than a
    | misconfiguration.
    |
    */

    'endpoint' => env('AGENT_TOOLS_ENDPOINT', 'http://webserver/api/internal/agent/tool-call'),

    // The end-user agent's callback. Which of the two a turn is given is decided
    // server-side from the actor type, so the worker never has to know what kind
    // of person started it.
    'user_endpoint' => env('AGENT_TOOLS_USER_ENDPOINT', 'http://webserver/api/internal/agent/user/tool-call'),

];
