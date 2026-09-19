<?php

return [
    'id' => 'calls',
    'name' => 'All Calls',
    'version' => '1.0.0',
    'description' => 'Browse call detail records and play or download call recordings',
    'menu' => [
        'label' => 'calls',
        'icon' => 'phone',
        'route' => '/calls',
        'order' => 20,
    ],
    'routes' => [
        'GET /calls' => [
            'SmartReport\Features\Calls\Controllers\CallsController@index',
            ['root', 'admin', 'viewer'],
        ],
        'GET /calls/export' => [
            'SmartReport\Features\Calls\Controllers\CallsController@export',
            ['root', 'admin', 'viewer'],
        ],
        'GET /calls/audio' => [
            'SmartReport\Features\Calls\Controllers\CallsController@audio',
            ['root', 'admin', 'viewer'],
        ],
    ],
    'roles' => ['root', 'admin', 'viewer'],
    'requires_ami' => false,
    'always_enabled' => true,
];