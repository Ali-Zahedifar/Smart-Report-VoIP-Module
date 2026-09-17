<?php

declare(strict_types=1);

return [
    'id' => 'settings',
    'name' => 'Settings & Users',
    'version' => '1.0.0',
    'description' => 'System info, user management and module options',
    'menu' => [
        'label' => 'settings',
        'icon' => 'cog',
        'route' => '/settings',
        'order' => 90,
    ],
    'routes' => [
        'GET /settings' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@index',
            ['root', 'admin'],
        ],
        'GET /settings/users' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@users',
            ['root', 'admin'],
        ],
        'POST /settings/users' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@userCreate',
            ['root', 'admin'],
        ],
        'GET /settings/users/{id}' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@userEdit',
            ['root', 'admin'],
        ],
        'POST /settings/users/{id}' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@userUpdate',
            ['root', 'admin'],
        ],
        'POST /settings/users/{id}/toggle' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@userToggle',
            ['root', 'admin'],
        ],
        'POST /settings/users/{id}/delete' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@userDelete',
            ['root', 'admin'],
        ],
        'GET /settings/modules' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@modules',
            ['root'],
        ],
        'POST /settings/modules' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@modulesSave',
            ['root'],
        ],
        'GET /settings/profile' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@profile',
            ['root', 'admin', 'viewer'],
        ],
        'POST /settings/profile' => [
            'SmartReport\Features\Settings\Controllers\SettingsController@profileSave',
            ['root', 'admin', 'viewer'],
        ],
    ],
    'roles' => ['root', 'admin'],
    'requires_ami' => false,
    'always_enabled' => true,
];