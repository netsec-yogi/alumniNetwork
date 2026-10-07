<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Illuminate\Support\Facades\Log;

/**
 * Claude via the official Anthropic PHP SDK.
 *
 * Thinking is left at the model default (adaptive); effort is set per
 * feature. Server-side refusal fallbacks are enabled so a policy decline on
 * the primary model is retried on its default fallback within the call.
 */
class AnthropicLanguageModel implements LanguageModel
{
    private const BETAS = ['server-side-fallback-2026-07-01'];

    public function __construct(private readonly Client $client) {}

    public function extract(string $system, string $prompt, array $schema, string $effort = 'low'): ?array
    {
        try {
            $message = $this->client->beta->messages->create(
                model: config('ai.model'),
                maxTokens: 4000,
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: ['effort' => $effort, 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                fallbacks: 'default',
                betas: self::BETAS,
            );
        } catch (APIException $e) {
            Log::warning('AI extraction failed.', ['error' => $e->getMessage()]);

            return null;
        }

        if ($message->stopReason === 'refusal') {
            return null;
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                return is_array($data) ? $data : null;
            }
        }

        return null;
    }

    public function converse(string $system, array $messages, array $tools, string $effort = 'medium'): AiReply
    {
        try {
            $message = $this->client->beta->messages->create(
                model: config('ai.model'),
                maxTokens: 8000,
                system: $system,
                messages: $messages,
                tools: $tools,
                outputConfig: ['effort' => $effort],
                fallbacks: 'default',
                betas: self::BETAS,
            );
        } catch (APIException $e) {
            Log::warning('AI conversation turn failed.', ['error' => $e->getMessage()]);

            return new AiReply('error', [], ['Sorry — the assistant is unavailable right now. Please try again shortly.']);
        }

        $texts = [];
        $calls = [];
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $texts[] = $block->text;
            } elseif ($block->type === 'tool_use') {
                $calls[] = ['id' => $block->id, 'name' => $block->name, 'input' => (array) $block->input];
            }
        }

        return new AiReply((string) $message->stopReason, $message->content, $texts, $calls);
    }
}
