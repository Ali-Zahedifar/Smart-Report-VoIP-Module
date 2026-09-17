<?php

declare(strict_types=1);

return [
    'id' => 'dashboard',
    'name' => 'Dashboard',
    'version' => '1.0.0',
    'description' => 'Home overview with today call summary',
    'menu' => [
        'label' => 'dashboard',
        'icon' => 'dashboard',
        'route' => '/',
        'order' => 10,
    ],
    'routes' => [
        'GET /' => [
            'SmartReport\Features\Dashboard\Controllers\DashboardController@index',
            ['root', 'admin', 'viewer'],
        ],
    ],
    'roles' => ['root', 'admin', 'viewer'],
    'requires_ami' => false,
    'always_enabled' => true,
];