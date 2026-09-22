<?php

return [
    'id' => 'empreport',
    'name' => 'Employee Report',
    'version' => '1.0.0',
    'description' => 'Employee work sessions (dial-in clock codes) and call activity per employee',
    'menu' => [
        'label' => 'empreport',
        'icon' => 'clock',
        'route' => '/employee-report',
        'order' => 34,
    ],
    'routes' => [
        'GET /employee-report' => [
            'SmartReport\\Features\\Empreport\\Controllers\\EmpReportController@index',
            ['root', 'admin', 'viewer'],
        ],
        'GET /employee-report/detail' => [
            'SmartReport\\Features\\Empreport\\Controllers\\EmpReportController@detail',
            ['root', 'admin', 'viewer'],
        ],
        'GET /employee-report/export' => [
            'SmartReport\\Features\\Empreport\\Controllers\\EmpReportController@export',
            ['root', 'admin', 'viewer'],
        ],
        'GET /employee-report/manage' => [
            'SmartReport\\Features\\Empreport\\Controllers\\EmpReportController@manage',
            ['root'],
        ],
        'POST /employee-report/save' => [
            'SmartReport\\Features\\Empreport\\Controllers\\EmpReportController@save',
            ['root'],
        ],
        'POST /employee-report/delete' => [
            'SmartReport\\Features\\Empreport\\Controllers\\EmpReportController@delete',
            ['root'],
        ],
    ],
    'roles' => ['root', 'admin', 'viewer'],
    'requires_ami' => false,
    'always_enabled' => false,
];
