<?php

/*
|--------------------------------------------------------------------------
| Engagement score (SRS 53)
|--------------------------------------------------------------------------
|
| Points per activity type. The score is always computed from the
| engagement_activities rows, never stored, so changing a weight here
| re-scores history consistently. Unlisted types score 0 but are still
| counted in CASE reporting (SRS 55).
|
*/

return [

    'weights' => [
        'PROFILE_COMPLETED' => 10,
        'EVENT_ATTENDED' => 10,
        'MENTORSHIP_COMPLETED' => 20,
        'JOB_POSTED' => 15,
        'JOB_REFERRAL' => 15,
        'VOLUNTEERED' => 20,
        'DONATION' => 25,
        'GUEST_LECTURE' => 15,
        'COMMUNITY_POST' => 5,
        'CONNECTION_CREATED' => 1,
    ],

    // CASE engagement modes (SRS 55), for report labels.
    'modes' => [
        'philanthropic' => 'Philanthropic',
        'volunteer' => 'Volunteer',
        'experiential' => 'Experiential',
        'communication' => 'Communications',
    ],
];
