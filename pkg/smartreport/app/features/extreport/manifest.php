<?php

return [
    'id' => 'extreport',
    'name' => 'Extension Report',
    'version' => '1.0.0',
    'description' => 'Per-extension productivity: call counts, talk time, inbound/outbound split, missed inbound',
    'menu' => [
        'label' => 'extreport',
        'icon' => 'users',
        'route' => '/ext-report',
        'order' => 32,
    ],
    'routes' => [
        'GET /ext-report' => [
            'SmartReport\\Features\\Extreport\\Controllers\\ExtReportController@index',
            ['root', 'admin', 'viewer'],
        ],
        'GET /ext-report/detail' => [
            'SmartReport\\Features\\Extreport\\Controllers\\ExtReportController@detail',
            ['root', 'admin', 'viewer'],
        ],
        'GET /ext-report/data' => [
            'SmartReport\\Features\\Extreport\\Controllers\\ExtReportController@data',
            ['root', 'admin', 'viewer'],
        ],
        'GET /ext-report/export' => [
            'SmartReport\\Features\\Extreport\\Controllers\\ExtReportController@export',
            ['root', 'admin', 'viewer'],
        ],
    ],
    'roles' => ['root', 'admin', 'viewer'],
    'requires_ami' => false,
    'always_enabled' => false,
];
