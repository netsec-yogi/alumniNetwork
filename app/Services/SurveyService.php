<?php

namespace App\Services;

use App\Models\EngagementActivity;
use App\Models\EventRegistration;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Surveys (module 31): who may answer, recording answers, and aggregate results. */
class SurveyService
{
    public function __construct(
        private readonly AudienceBuilder $audiences,
        private readonly EngagementRecorder $engagement,
    ) {}

    /** Users entitled to answer: an event's checked-in attendees, or an audience segment. */
    public function eligibleQuery(Survey $survey): Builder
    {
        if ($survey->event_id) {
            return User::query()->whereIn('id', EventRegistration::where('event_id', $survey->event_id)->whereNotNull('checked_in_at')->select('user_id'));
        }

        return $this->audiences->query($survey->audience ?? []);
    }

    public function isEligible(User $user, Survey $survey): bool
    {
        return $this->eligibleQuery($survey)->whereKey($user->id)->exists();
    }

    public function hasResponded(User $user, Survey $survey): bool
    {
        return $survey->responses()->where('respondent_hash', $survey->respondentHash($user))->exists();
    }

    public function publish(Survey $survey): int
    {
        if ($survey->questions()->count() === 0) {
            throw new InvalidArgumentException('Add at least one question before publishing.');
        }
        $survey->forceFill(['status' => 'published'])->save();

        $count = 0;
        $this->eligibleQuery($survey)->select('users.id', 'users.name', 'users.email')->chunkById(500, function ($users) use ($survey, &$count) {
            foreach ($users as $u) {
                $u->notify(new Notice("Survey: {$survey->title}", route('surveys.show', $survey, false), 'Your answers help shape alumni programmes.'));
                $count++;
            }
        }, 'users.id', 'id');

        return $count;
    }

    /** @param  array<int|string, mixed>  $answers  keyed by question id */
    public function submit(User $user, Survey $survey, array $answers): void
    {
        if (! $survey->isOpen() || ! $this->isEligible($user, $survey)) {
            throw new InvalidArgumentException('This survey isn’t open to you.');
        }

        $clean = [];
        $errors = [];
        foreach ($survey->questions as $q) {
            $value = $answers[$q->id] ?? null;
            $result = $this->validateAnswer($q, $value);
            if ($result === false) {
                $errors["answers.{$q->id}"] = $q->required && $this->isBlank($value) ? 'Please answer this question.' : 'That answer isn’t valid.';
            } elseif (! $this->isBlank($result)) {
                $clean[$q->id] = $result;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($user, $survey, $clean) {
            $hash = $survey->respondentHash($user);
            if ($survey->responses()->where('respondent_hash', $hash)->lockForUpdate()->exists()) {
                throw new InvalidArgumentException('You’ve already answered this survey.');
            }

            $response = $survey->responses()->make();
            $response->forceFill(['respondent_hash' => $hash, 'user_id' => $survey->is_anonymous ? null : $user->id])->save();
            foreach ($clean as $questionId => $value) {
                $response->answers()->make()->forceFill(['survey_question_id' => $questionId, 'value' => $value])->save();
            }
        });

        // Participation (not the answers) counts as engagement.
        $this->engagement->record($user, 'SURVEY_RESPONSE', EngagementActivity::MODE_COMMUNICATION, $survey);
    }

    /** @return mixed|false false when invalid */
    private function validateAnswer(SurveyQuestion $q, mixed $v): mixed
    {
        if ($this->isBlank($v)) {
            return $q->required ? false : null;
        }

        return match ($q->type) {
            'single' => is_string($v) && in_array($v, $q->options ?? [], true) ? $v : false,
            'multiple' => is_array($v) && $v !== [] && array_diff($v, $q->options ?? []) === [] ? array_values(array_unique($v)) : false,
            'text' => is_string($v) && mb_strlen($v) <= 2000 ? trim($v) : false,
            'rating' => is_numeric($v) && (int) $v >= 1 && (int) $v <= 5 ? (int) $v : false,
            'nps' => is_numeric($v) && (int) $v >= 0 && (int) $v <= 10 ? (int) $v : false,
            default => false,
        };
    }

    private function isBlank(mixed $v): bool
    {
        return $v === null || $v === '' || $v === [];
    }

    /** @return list<array<string, mixed>> */
    public function results(Survey $survey): array
    {
        $answers = DB::table('survey_answers as a')
            ->join('survey_responses as r', 'r.id', '=', 'a.survey_response_id')
            ->where('r.survey_id', $survey->id)
            ->get(['a.survey_question_id', 'a.value'])
            ->groupBy('survey_question_id');

        return $survey->questions->map(function (SurveyQuestion $q) use ($answers) {
            $values = collect($answers[$q->id] ?? [])->map(fn ($a) => json_decode($a->value, true));
            $base = ['id' => $q->id, 'prompt' => $q->prompt, 'type' => $q->type, 'answered' => $values->count()];

            return $base + match ($q->type) {
                'single', 'multiple' => ['options' => collect($q->options)->map(fn ($o) => [
                    'label' => $o,
                    'value' => $values->filter(fn ($v) => is_array($v) ? in_array($o, $v, true) : $v === $o)->count(),
                ])->values()],
                'rating' => ['average' => $values->count() ? round($values->avg(), 2) : null, 'options' => collect(range(1, 5))->map(fn ($n) => ['label' => "{$n} ★", 'value' => $values->filter(fn ($v) => (int) $v === $n)->count()])],
                // Net Promoter Score: % promoters (9-10) minus % detractors (0-6).
                'nps' => ['nps' => $values->count() ? (int) round(($values->filter(fn ($v) => $v >= 9)->count() - $values->filter(fn ($v) => $v <= 6)->count()) / $values->count() * 100) : null],
                'text' => ['texts' => $values->take(200)->values()],
                default => [],
            };
        })->values()->all();
    }
}
