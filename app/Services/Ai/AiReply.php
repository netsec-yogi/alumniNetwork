<?php

namespace App\Services\Ai;

/** A normalised model turn: raw content to append to history, plus parsed text and tool calls. */
final class AiReply
{
    /**
     * @param  list<mixed>  $content  raw blocks, appended verbatim as the assistant turn
     * @param  list<string>  $texts
     * @param  list<array{id: string, name: string, input: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public readonly string $stopReason,
        public readonly array $content,
        public readonly array $texts = [],
        public readonly array $toolCalls = [],
    ) {}

    public function text(): string
    {
        return trim(implode("\n\n", $this->texts));
    }
}
