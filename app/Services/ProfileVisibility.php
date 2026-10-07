<?php

namespace App\Services;

use App\Enums\Visibility;
use App\Models\AlumniProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies alumni privacy settings (SRS 22) for a given viewer.
 *
 * Every read path -- profile pages, the directory, and directory *filters* --
 * goes through here. Filtering on a hidden field would otherwise leak it
 * (search "company = X" and see who appears), so filters are restricted to
 * profiles where that field is visible to the viewer.
 *
 * "Connections only" fields are visible to accepted connections.
 */
class ProfileVisibility
{
    public function __construct(private readonly ConnectionService $connections) {}

    /**
     * Levels this viewer may see on someone else's profile, before the
     * per-profile connections check.
     *
     * @return list<Visibility>
     */
    public function levelsFor(?User $viewer): array
    {
        $levels = [Visibility::Public];

        if ($viewer?->alumniProfile?->isVerified()) {
            $levels[] = Visibility::Alumni;
        }

        return $levels;
    }

    public function canSee(AlumniProfile $profile, string $field, ?User $viewer): bool
    {
        if ($viewer && $viewer->id === $profile->user_id) {
            return true;
        }

        $level = $profile->visibilityOf($field);

        if ($level === Visibility::Connections) {
            return $viewer !== null && $this->connections->areConnected($viewer->id, $profile->user_id);
        }

        return in_array($level, $this->levelsFor($viewer), true);
    }

    /** Constrain a query to profiles whose $field is visible to the viewer. */
    public function scopeVisible(Builder $query, string $field, ?User $viewer): Builder
    {
        $allowed = array_map(fn (Visibility $v) => $v->value, $this->levelsFor($viewer));
        $defaultVisible = in_array(AlumniProfile::PRIVACY_FIELDS[$field]->value, $allowed, true);

        return $query->where(function (Builder $q) use ($field, $allowed, $defaultVisible, $viewer) {
            $q->whereIn("visibility->{$field}", $allowed);

            if ($defaultVisible) {
                $q->orWhereNull("visibility->{$field}");
            }

            if ($viewer) {
                $q->orWhere('user_id', $viewer->id);

                $connected = $this->connections->connectedIds($viewer->id);
                if ($connected->isNotEmpty()) {
                    $q->orWhere(fn (Builder $q) => $q
                        ->where("visibility->{$field}", Visibility::Connections->value)
                        ->whereIn('user_id', $connected));
                }
            }
        });
    }

    /**
     * The profile as this viewer may see it. Hidden fields are omitted, not
     * blanked, so the client cannot tell "hidden" from "not filled in".
     *
     * @return array<string, mixed>
     */
    public function present(AlumniProfile $profile, ?User $viewer, bool $summary = false): array
    {
        $profile->loadMissing('user', 'programme.department', 'photo');
        $see = fn (string $field) => $this->canSee($profile, $field, $viewer);

        $data = [
            'id' => $profile->id,
            'name' => $profile->displayName(),
            'programme' => $profile->programme->name,
            'department' => $profile->programme->department?->name,
            'graduation_year' => $profile->graduation_year,
            'is_verified' => $profile->isVerified(),
            'photo_url' => $profile->photo?->url(true),
            'interests' => array_values(array_intersect(array_keys(AlumniProfile::INTERESTS), $profile->interests ?? [])),
        ];

        if ($see('company')) {
            $data['company'] = $profile->company;
        }
        if ($see('designation')) {
            $data['designation'] = $profile->designation;
        }
        if ($see('location')) {
            $data['location'] = collect([$profile->city, $profile->state, $profile->country])->filter()->implode(', ') ?: null;
        }

        if ($summary) {
            return $data;
        }

        $data['specialization'] = $profile->specialization;
        $data['industry'] = $profile->industry;
        $data['admission_year'] = $profile->admission_year;

        if ($see('bio')) {
            $data['bio'] = $profile->bio;
        }
        if ($see('linkedin_url')) {
            $data['linkedin_url'] = $profile->linkedin_url;
        }
        if ($see('email')) {
            $data['email'] = $profile->user->email;
        }
        if ($see('phone')) {
            $data['phone'] = $profile->user->phone;
        }
        $data['website_url'] = $profile->website_url;

        return $data;
    }
}
