<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the rate limiting for different types of requests.
    | Rate limiting helps prevent abuse and ensures fair usage of resources.
    |
    */

    'withdraw' => [
        'max_attempts' => 5, // Maximum withdrawal attempts per minute
        'decay_minutes' => 1,
    ],

    'admin-withdraw' => [
        'max_attempts' => 20, // Maximum admin withdrawal actions per minute
        'decay_minutes' => 1,
    ],

    'api' => [
        'max_attempts' => 60, // Maximum API requests per minute
        'decay_minutes' => 1,
    ],

    'login' => [
        'max_attempts' => 5, // Maximum login attempts per minute
        'decay_minutes' => 1,
    ],
];
