<?php

namespace Tests\Support;

use App\Services\Ai\AiReply;
use App\Services\Ai\LanguageModel;

/** Scripted stand-in for the model; records every request so tests can assert what was sent. */
class FakeLanguageModel implements LanguageModel
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    /** @param  list<array<string, mixed>|AiReply|null>  $responses  consumed in order */
    public function __construct(private array $responses = []) {}

    public function extract(string $system, string $prompt, array $schema, string $effort = 'low'): ?array
    {
        $this->calls[] = compact('system', 'prompt', 'schema', 'effort');

        return array_shift($this->responses);
    }

    public function converse(string $system, array $messages, array $tools, string $effort = 'medium'): AiReply
    {
        $this->calls[] = compact('system', 'messages', 'tools', 'effort');

        return array_shift($this->responses) ?? new AiReply('end_turn', [], ['(no scripted reply)']);
    }
}
