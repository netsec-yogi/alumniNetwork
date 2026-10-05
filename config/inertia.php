<?php

/*
 | Only the keys that differ from the package defaults. Pages live in
 | resources/js/Pages (capitalised), which assertInertia() checks against.
 */
return [

    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['vue', 'ts'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],

];
