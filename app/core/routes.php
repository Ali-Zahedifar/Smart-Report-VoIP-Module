<?php

declare(strict_types=1);

use SmartReport\Controllers\AuthController;
use SmartReport\Controllers\LocaleController;

return [
    'GET /login' => [AuthController::class . '@showLogin', []],
    'POST /login' => [AuthController::class . '@login', []],
    'GET /logout' => [AuthController::class . '@logout', ['root', 'admin', 'viewer']],
    'POST /logout' => [AuthController::class . '@logout', ['root', 'admin', 'viewer']],
    'GET /lang/{code}' => [LocaleController::class . '@switch', []],
];