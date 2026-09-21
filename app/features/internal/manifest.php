<?php

return [
    'id' => 'internal',
    'name' => 'Internal Calls',
    'version' => '1.0.0',
    'description' => 'View and filter internal calls',
    'menu' => [
        'label' => 'internal',
        'icon' => 'filter',
        'route' => '/internal',
        'order' => 25,
    ],
    'routes' => [
        'GET /internal' => [
            'SmartReport\Features\Internal\Controllers\InternalController@index',
            ['root', 'admin', 'viewer'],
        ],
    ],
    'roles' => ['root', 'admin', 'viewer'],
    'requires_ami' => false,
    'always_enabled' => false,
];