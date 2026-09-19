<?php

use SmartReport\Core\Config;
use SmartReport\Core\Lang;
use SmartReport\Core\Log;

error_reporting(E_ALL);
ini_set('log_errors', '1');

define('SMR_VERSION', '1.1.0');
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

function smr_case_path($dir, $segment)
{
    $direct = $dir . '/' . $segment;
    if (file_exists($direct)) {
        return $direct;
    }
    $entries = @scandir($dir);
    if ($entries === false) {
        return null;
    }
    $lower = strtolower($segment);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (strtolower($entry) === $lower) {
            return $dir . '/' . $entry;
        }
    }
    return null;
}

spl_autoload_register(function ($class) {
    $prefix = 'SmartReport\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $segments = explode('\\', $rel);
    $file = array_pop($segments) . '.php';
    $dir = SMR_APP;
    foreach ($segments as $segment) {
        if ($segment === '') {
            return;
        }
        $dir = smr_case_path($dir, $segment);
        if ($dir === null) {
            return;
        }
    }
    $path = smr_case_path($dir, $file);
    if ($path !== null) {
        require $path;
    }
});

Config::init(['config_dir' => SMR_CONFIG]);

$sAppCfg = Config::get('app', []);
date_default_timezone_set(!empty($sAppCfg['timezone']) ? (string) $sAppCfg['timezone'] : (string) (ini_get('date.timezone') ?: 'UTC'));
ini_set('display_errors', !empty($sAppCfg['debug']) ? '1' : '0');

ini_set('error_log', SMR_LOGS . '/error.log');

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    Log::write('error', sprintf('%s in %s:%d', $message, $file, $line));
    return true;
}, E_ALL);

set_exception_handler(function ($e) {
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
    $sCookiePath = SMR_BASE_URL === '' ? '/' : SMR_BASE_URL . '/';
    session_set_cookie_params($iLifetime, $sCookiePath, '', false, true);
    session_start();
}

Lang::init(Config::get('app', []));

require SMR_APP . '/core/helpers.php';