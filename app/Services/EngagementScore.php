<?php

namespace App\Services;

use App\Models\EngagementActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Engagement score from activity rows and configured weights (SRS 53). */
class EngagementScore
{
    /** SQL expression: points for one activity row. Weights are integers from config. */
    public function pointsExpression(): Expression
    {
        $cases = collect(config('engagement.weights'))
            ->map(fn ($points, $type) => sprintf("WHEN '%s' THEN %d", addslashes($type), (int) $points))
            ->implode(' ');

        return DB::raw("SUM(CASE activity_type {$cases} ELSE 0 END)");
    }

    public function forProfile(int $profileId, ?Carbon $from = null, ?Carbon $to = null): int
    {
        return (int) $this->window(EngagementActivity::query(), $from, $to)
            ->where('alumni_profile_id', $profileId)
            ->toBase() // Eloquent's value() does not resolve raw expressions
            ->value($this->pointsExpression());
    }

    /** Most engaged alumni in a window. */
    public function leaders(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null)
    {
        return $this->window(EngagementActivity::query(), $from, $to)
            ->select('alumni_profile_id', DB::raw($this->pointsExpression()->getValue(DB::connection()->getQueryGrammar()).' as score'), DB::raw('count(*) as activities'))
            ->groupBy('alumni_profile_id')
            ->orderByDesc('score')
            ->limit($limit)
            ->with('profile.user:id,name', 'profile.programme:id,code')
            ->get();
    }

    public function window(Builder $query, ?Carbon $from, ?Carbon $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->where('activity_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('activity_date', '<=', $to->toDateString()));
    }
}
