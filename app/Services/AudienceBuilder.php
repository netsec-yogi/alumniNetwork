<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Turns a segment definition into a user query (SRS 49), e.g.
 * {"roles":["alumni"],"programmes":[1],"graduation_from":2010,"graduation_to":2018,"cities":["Bengaluru"]}
 *
 * Only active accounts with a verified email are ever included. Email is
 * further limited to people whose latest communications consent is granted.
 */
class AudienceBuilder
{
    public const FILTERS = ['roles', 'programmes', 'graduation_from', 'graduation_to', 'graduation_years', 'cities', 'countries', 'industries', 'interests', 'communities'];

    public function query(array $a): Builder
    {
        $roles = $a['roles'] ?? [RoleName::Alumni->value];
        $alumniOnlyFilters = collect(['programmes', 'graduation_from', 'graduation_to', 'graduation_years', 'cities', 'countries', 'industries', 'interests'])
            ->contains(fn ($k) => ! empty($a[$k]));

        return User::query()
            ->where('status', UserStatus::Active)
            ->whereNotNull('email_verified_at')
            ->role($roles)
            // Alumni must be verified to count as alumni.
            ->when(in_array(RoleName::Alumni->value, $roles, true), fn ($q) => $q->where(fn ($q) => $q
                ->whereDoesntHave('roles', fn ($r) => $r->where('name', RoleName::Alumni->value))
                ->orWhereHas('alumniProfile', fn ($p) => $p->verified())))
            ->when($alumniOnlyFilters, fn ($q) => $q->whereHas('alumniProfile', fn ($p) => $p
                ->verified()
                ->when($a['programmes'] ?? null, fn ($p, $v) => $p->whereIn('programme_id', $v))
                ->when($a['graduation_from'] ?? null, fn ($p, $v) => $p->where('graduation_year', '>=', $v))
                ->when($a['graduation_to'] ?? null, fn ($p, $v) => $p->where('graduation_year', '<=', $v))
                ->when($a['graduation_years'] ?? null, fn ($p, $v) => $p->whereIn('graduation_year', $v))
                ->when($a['cities'] ?? null, fn ($p, $v) => $p->whereIn('city', $v))
                ->when($a['countries'] ?? null, fn ($p, $v) => $p->whereIn('country', $v))
                ->when($a['industries'] ?? null, fn ($p, $v) => $p->whereIn('industry', $v))
                ->when($a['interests'] ?? null, fn ($p, $v) => $p->where(fn ($p) => collect($v)->each(fn ($i) => $p->orWhereJsonContains('interests', $i))))))
            ->when($a['communities'] ?? null, fn ($q, $v) => $q->whereHas('communityMemberships', fn ($m) => $m->whereIn('community_id', $v)->where('status', 'active')));
    }

    /** Users whose most recent communications consent is "granted". */
    public function emailable(Builder $query): Builder
    {
        $latest = DB::table('consents')
            ->select('user_id', DB::raw('MAX(id) as id'))
            ->where('consent_type', Consent::COMMUNICATIONS)
            ->groupBy('user_id');

        return $query->whereIn('users.id', DB::table('consents as c')
            ->joinSub($latest, 'l', 'l.id', '=', 'c.id')
            ->where('c.granted', true)
            ->select('c.user_id'));
    }

    /** @return array{total: int, email: int, sample: list<string>} */
    public function preview(array $audience): array
    {
        $q = $this->query($audience);

        return [
            'total' => (clone $q)->count(),
            'email' => $this->emailable(clone $q)->count(),
            'sample' => (clone $q)->inRandomOrder()->limit(5)->pluck('name')->all(),
        ];
    }
}
