<?php
declare(strict_types=1);

define('SMR_START', microtime(true));
define('SMR_ROOT', __DIR__);

require SMR_ROOT . '/app/core/bootstrap.php';

use SmartReport\Core\Router;

$router = Router::instance();
$router->dispatch();