<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('log_errors', '1');

define('SMR_VERSION', '1.0.0');
define('SMR_APP', SMR_ROOT . '/app');
define('SMR_CONFIG', SMR_ROOT . '/config');
define('SMR_TEMPLATES', SMR_APP . '/templates');
define('SMR_ASSETS', SMR_ROOT . '/assets');
define('SMR_DATA', SMR_ROOT . '/data');
define('SMR_STORAGE', SMR_ROOT . '/storage');
define('SMR_CACHE', SMR_DATA . '/cache');
define('SMR_LOGS', SMR_DATA . '/logs');

if (PHP_SAPI === 'cli') {
    define('SMR_CLI', true);
    define('SMR_BASE_URL', '');
    define('SMR_REMOTE_ADDR', '');
} else {
    define('SMR_CLI', false);
    $sBase = isset($_SERVER['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') : '';
    define('SMR_BASE_URL', $sBase);
    define('SMR_REMOTE_ADDR', isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
}

spl_autoload_register(function (string $class): void {
    $prefix = 'SmartReport\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $path = SMR_APP . '/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use SmartReport\Core\Config;
use SmartReport\Core\Lang;

Config::init(['config_dir' => SMR_CONFIG]);

$sAppCfg = Config::get('app', []);
date_default_timezone_set(!empty($sAppCfg['timezone']) ? (string) $sAppCfg['timezone'] : (string) (ini_get('date.timezone') ?: 'UTC'));
ini_set('display_errors', !empty($sAppCfg['debug']) ? '1' : '0');

ini_set('error_log', SMR_LOGS . '/error.log');

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    Log::write('error', sprintf('%s in %s:%d', $message, $file, $line));
    return true;
}, E_ALL);

set_exception_handler(function (Throwable $e): void {
    Log::write('error', sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()) . "\n" . $e->getTraceAsString());
    if (SMR_CLI) {
        fwrite(STDERR, '[Error] ' . $e->getMessage() . PHP_EOL);
        return;
    }
    $code = ($e->getCode() >= 400 && $e->getCode() <= 599) ? (int) $e->getCode() : 500;
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    if ($code === 403) {
        echo '<h2>' . Lang::t('403.title') . '</h2><p>' . Lang::t('403.body') . '</p>';
        return;
    }
    echo '<h1>' . htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
});

if (!SMR_CLI) {
    $sSessionName = !empty($sAppCfg['session_name']) ? (string) $sAppCfg['session_name'] : 'smr_session';
    session_name($sSessionName);
    $iLifetime = !empty($sAppCfg['cookie_lifetime']) ? (int) $sAppCfg['cookie_lifetime'] : 86400;
    session_set_cookie_params([
        'lifetime' => $iLifetime,
        'path' => SMR_BASE_URL === '' ? '/' : SMR_BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

Lang::init(Config::get('app', []));

require SMR_APP . '/core/helpers.php';