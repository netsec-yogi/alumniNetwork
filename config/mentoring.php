<?php

/*
|--------------------------------------------------------------------------
| Mentor matching (SRS 30)
|--------------------------------------------------------------------------
|
| Rule-based score out of 100. Weights are relative: they are normalised,
| so they need not sum to exactly 100. Tune them here without code changes.
|
*/

return [

    'weights' => [
        'programme' => (int) env('MENTOR_WEIGHT_PROGRAMME', 10),
        'skills' => (int) env('MENTOR_WEIGHT_SKILLS', 20),
        'career_interest' => (int) env('MENTOR_WEIGHT_CAREER_INTEREST', 20),
        'industry' => (int) env('MENTOR_WEIGHT_INDUSTRY', 15),
        'experience' => (int) env('MENTOR_WEIGHT_EXPERIENCE', 10),
        'mentor_preference' => (int) env('MENTOR_WEIGHT_PREFERENCE', 10),
        'location' => (int) env('MENTOR_WEIGHT_LOCATION', 5),
        'availability' => (int) env('MENTOR_WEIGHT_AVAILABILITY', 10),
    ],

    // Years after graduation at which the experience component maxes out.
    'experience_full_years' => 10,

    'results' => 12,

    'categories' => [
        'career' => 'Career growth',
        'technology' => 'Technology',
        'research' => 'Research',
        'entrepreneurship' => 'Entrepreneurship',
        'higher_studies' => 'Higher studies',
        'leadership' => 'Leadership',
        'industry' => 'Industry insight',
    ],

    'modes' => [
        'video' => 'Video call',
        'call' => 'Phone call',
        'chat' => 'Chat / email',
        'in_person' => 'In person',
    ],
];
