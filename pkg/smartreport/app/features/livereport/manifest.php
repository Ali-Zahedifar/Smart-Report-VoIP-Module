<?php

return [
    'id' => 'livereport',
    'name' => 'Live Report',
    'version' => '1.0.0',
    'description' => 'Live calls and queue state via the Asterisk Manager Interface, with optional listen/whisper/barge',
    'menu' => [
        'label' => 'livereport',
        'icon' => 'radio',
        'route' => '/live',
        'order' => 10,
    ],
    'routes' => [
        'GET /live' => [
            'SmartReport\\Features\\Livereport\\Controllers\\LiveController@index',
            ['root', 'admin', 'viewer'],
        ],
        'GET /live/data' => [
            'SmartReport\\Features\\Livereport\\Controllers\\LiveController@data',
            ['root', 'admin', 'viewer'],
        ],
        'POST /live/spy' => [
            'SmartReport\\Features\\Livereport\\Controllers\\LiveController@spy',
            ['root', 'admin'],
        ],
        'POST /live/settings' => [
            'SmartReport\\Features\\Livereport\\Controllers\\LiveController@saveSettings',
            ['root'],
        ],
    ],
    'roles' => ['root', 'admin', 'viewer'],
    'requires_ami' => true,
    'always_enabled' => false,
];
