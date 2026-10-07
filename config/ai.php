<?php

/*
|--------------------------------------------------------------------------
| AI features (SRS 96)
|--------------------------------------------------------------------------
|
| Optional layer: the portal is fully functional with AI_ENABLED=false.
| When enabled, member data sent to the model is limited to what the
| requesting user may already see, and every feature runs under the same
| authorisation as the rest of the app. Disclose this processing in the
| privacy policy before enabling it in production.
|
*/

return [

    'enabled' => (bool) env('AI_ENABLED', false) && (env('ANTHROPIC_API_KEY') || env('ANTHROPIC_AUTH_TOKEN')),

    'model' => env('AI_MODEL', 'claude-opus-5-5'),

    // Effort per feature: extraction and re-ranking are simple, the assistant reasons over tool results.
    'effort' => [
        'search' => env('AI_EFFORT_SEARCH', 'low'),
        'matching' => env('AI_EFFORT_MATCHING', 'low'),
        'assistant' => env('AI_EFFORT_ASSISTANT', 'medium'),
    ],

    'assistant' => [
        'max_tool_rounds' => 6,
        'history_turns' => 10,
    ],
];
