<?php

return [
    'name' => 'Smart-Report',
    'version' => '1.1.1',
    'debug' => false,
    'session_name' => 'smr_session',
    'cookie_lifetime' => 86400,
    'timezone' => null,
    'default_language' => 'en',
    'languages' => [
        'en' => 'English',
        'fa' => 'فارسی',
    ],
    'login_max_attempts' => 5,
    'login_lockout_seconds' => 60,
    'pagination_default' => 50,
    'pagination_choices' => [25, 50, 100, 250, 500],
];