<?php

return [
    'id' => 'reports',
    'name' => 'Graphical Reports',
    'version' => '1.0.0',
    'description' => 'Visual reports and charts for call analytics',
    'menu' => [
        'label' => 'reports',
        'icon' => 'chart',
        'route' => '/reports',
        'order' => 30,
    ],
    'routes' => [
        'GET /reports' => [
            'SmartReport\Features\Reports\Controllers\ReportsController@index',
            ['root', 'admin', 'viewer'],
        ],
        'GET /reports/data' => [
            'SmartReport\Features\Reports\Controllers\ReportsController@data',
            ['root', 'admin', 'viewer'],
        ],
    ],
    'roles' => ['root', 'admin', 'viewer'],
    'requires_ami' => false,
    'always_enabled' => false,
];