<?php

return [
    'id' => 'queuereport',
    'name' => 'Queue Report',
    'version' => '1.0.0',
    'description' => 'Detailed queue performance metrics and agent statistics',
    'menu' => [
        'label' => 'queue_report',
        'icon' => 'list',
        'route' => '/queue-report',
        'order' => 40,
    ],
    'routes' => [
        'GET /queue-report' => [
            'SmartReport\Features\QueueReport\Controllers\QueueReportController@index',
            ['root', 'admin', 'viewer'],
        ],
        'GET /queue-report/data' => [
            'SmartReport\Features\QueueReport\Controllers\QueueReportController@data',
            ['root', 'admin', 'viewer'],
        ],
        'GET /queue-report/detail' => [
            'SmartReport\Features\QueueReport\Controllers\QueueReportController@detail',
            ['root', 'admin', 'viewer'],
        ],
        'GET /queue-report/export' => [
            'SmartReport\Features\QueueReport\Controllers\QueueReportController@export',
            ['root', 'admin', 'viewer'],
        ],
    ],
    'roles' => ['root', 'admin', 'viewer'],
    'requires_ami' => false,
    'always_enabled' => false,
];