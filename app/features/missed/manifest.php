<?php

return [
    'id' => 'missed',
    'name' => 'Missed Calls',
    'version' => '1.0.0',
    'description' => 'View and filter missed inbound calls',
    'menu' => [
        'label' => 'missed',
        'icon' => 'alert-triangle',
        'route' => '/missed',
        'order' => 30,
    ],
    'routes' => [
        'GET /missed' => [
            'SmartReport\Features\Missed\Controllers\MissedController@index',
            ['root', 'admin', 'viewer'],
        ],
    ],
    'roles' => ['root', 'admin', 'viewer'],
    'requires_ami' => false,
    'always_enabled' => false,
];