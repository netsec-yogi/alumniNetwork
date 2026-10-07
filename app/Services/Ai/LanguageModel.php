<?php

namespace App\Services\Ai;

/** The model behind the AI features. Bound to AnthropicLanguageModel; tests bind a fake. */
interface LanguageModel
{
    /**
     * Structured extraction: returns JSON matching $schema, or null when the
     * model declines or the call fails (callers degrade gracefully).
     *
     * @param  array<string, mixed>  $schema  JSON schema (object, additionalProperties false)
     * @return array<string, mixed>|null
     */
    public function extract(string $system, string $prompt, array $schema, string $effort = 'low'): ?array;

    /**
     * One model turn with tools. $messages and $tools use the SDK's array shapes.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     */
    public function converse(string $system, array $messages, array $tools, string $effort = 'medium'): AiReply;
}
