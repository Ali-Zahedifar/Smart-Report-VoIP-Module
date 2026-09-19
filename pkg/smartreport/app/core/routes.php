<?php

use SmartReport\Controllers\AuthController;
use SmartReport\Controllers\LocaleController;

return [
    'GET /login' => ['SmartReport\\Controllers\\AuthController@showLogin', []],
    'POST /login' => ['SmartReport\\Controllers\\AuthController@login', []],
    'GET /logout' => ['SmartReport\\Controllers\\AuthController@logout', ['root', 'admin', 'viewer']],
    'POST /logout' => ['SmartReport\\Controllers\\AuthController@logout', ['root', 'admin', 'viewer']],
    'GET /lang/{code}' => ['SmartReport\\Controllers\\LocaleController@switchLanguage', []],
];