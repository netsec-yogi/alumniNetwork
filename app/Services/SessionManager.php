<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Active session listing and revocation (SRS 16). Relies on the database
 * session driver, whose rows carry user_id, IP and user agent.
 */
class SessionManager
{
    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user, ?string $currentId): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($s) => [
                // Session ids are bearer secrets; the UI only ever gets a hash.
                'key' => hash('sha256', $s->id),
                'ip_address' => $s->ip_address,
                'agent' => $this->describeAgent((string) $s->user_agent),
                'is_current' => $s->id === $currentId,
                'last_active' => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
            ]);
    }

    /** Revoke one session, identified by the hashed key shown in the UI. */
    public function revoke(User $user, string $key, ?string $currentId): bool
    {
        $sessions = $this->query()->where('user_id', $user->getAuthIdentifier())->pluck('id');
        $target = $sessions->first(fn ($id) => hash_equals(hash('sha256', $id), $key));

        if ($target === null || $target === $currentId) {
            return false;
        }

        return $this->query()->where('id', $target)->delete() > 0;
    }

    /** Revoke every session for the user, optionally keeping the current one. */
    public function revokeAll(User $user, ?string $exceptId = null): int
    {
        return $this->query()
            ->where('user_id', $user->getAuthIdentifier())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->delete();
    }

    private function query()
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }

    private function describeAgent(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return "{$browser} on {$os}";
    }
}
