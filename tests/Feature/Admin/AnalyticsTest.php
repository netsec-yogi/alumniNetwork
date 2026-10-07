<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\EngagementActivity;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    public function test_retention_cohorts_and_trends(): void
    {
        $returning = $this->verifiedAlumnus(['graduation_year' => 2012]);
        $lapsed = $this->verifiedAlumnus(['graduation_year' => 2013]);
        $new = $this->verifiedAlumnus(['graduation_year' => 2021]);
        $act = fn ($p, $date, $mode = 'volunteer') => EngagementActivity::create(['alumni_profile_id' => $p->id, 'activity_type' => 'X', 'activity_date' => $date, 'engagement_mode' => $mode, 'entity_id' => random_int(1, 1e9)]);
        $act($returning, now()->subMonths(18));
        $act($returning, now()->subMonth());
        $act($lapsed, now()->subMonths(15));
        $act($new, now(), 'experiential');

        $this->actingAs($this->admin(RoleName::AlumniAdmin))->get(route('admin.analytics'))->assertInertia(fn (Assert $p) => $p
            ->where('retention.previous', 2)
            ->where('retention.retained', 1)
            ->where('retention.rate', 50)
            ->where('retention.new', 1)
            ->where('engaged.11.value', 1)
            ->where('cohorts.0.label', '2010–2014')
            ->where('cohorts.0.value', 50));

        $this->actingAs($this->admin(RoleName::EventManager))->get(route('admin.analytics'))->assertForbidden();
    }
}
