<?php

namespace App\Services\Ai;

use App\Models\AlumniProfile;
use App\Models\Programme;

/**
 * Natural-language alumni search (SRS 96): turns "ML engineers from the 2015
 * IPG batch in Bengaluru" into the directory's own filters. Only the query
 * and the programme/interest vocabularies are sent to the model -- no member
 * data. The results then come from the normal, privacy-filtered directory.
 */
class AiSearchInterpreter
{
    public function __construct(private readonly LanguageModel $model) {}

    /** @return array{filters: array<string, mixed>, explanation: string}|null */
    public function interpret(string $query): ?array
    {
        $programmes = Programme::orderBy('name')->pluck('name', 'id');
        $interests = AlumniProfile::INTERESTS;
        $nullable = fn (array $type) => ['anyOf' => [$type, ['type' => 'null']]];

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name', 'programme', 'graduation_year', 'company', 'location', 'interest', 'explanation'],
            'properties' => [
                'name' => $nullable(['type' => 'string']),
                'programme' => $nullable(['type' => 'string', 'enum' => $programmes->values()->all()]),
                'graduation_year' => $nullable(['type' => 'integer']),
                'company' => $nullable(['type' => 'string']),
                'location' => $nullable(['type' => 'string']),
                'interest' => $nullable(['type' => 'string', 'enum' => array_keys($interests)]),
                'explanation' => ['type' => 'string'],
            ],
        ];

        $system = 'You convert a search request for an alumni directory into filters. '
            .'Use only what the request states; leave a filter null rather than guessing. '
            .'Programmes: choose the closest from the allowed list. Interests (what alumni are open to): '
            .collect($interests)->map(fn ($l, $k) => "{$k} = {$l}")->implode('; ').'. '
            .'"location" is a city or country. "explanation" is one short sentence restating the search, e.g. "B.Tech CSE alumni at Google in Bengaluru".';

        $out = $this->model->extract($system, "Search request: {$query}", $schema, config('ai.effort.search'));
        if ($out === null) {
            return null;
        }

        $filters = array_filter([
            'q' => $out['name'] ?? null,
            'programme' => $out['programme'] ? $programmes->search($out['programme']) ?: null : null,
            'year' => is_int($out['graduation_year'] ?? null) && $out['graduation_year'] >= 1990 && $out['graduation_year'] <= 2100 ? $out['graduation_year'] : null,
            'company' => $out['company'] ?? null,
            'location' => $out['location'] ?? null,
            'interest' => isset($interests[$out['interest'] ?? '']) ? $out['interest'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        return ['filters' => array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 100) : $v, $filters), 'explanation' => mb_substr((string) $out['explanation'], 0, 200)];
    }
}
