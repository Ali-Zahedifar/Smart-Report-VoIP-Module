<?php

use SmartReport\Core\Csrf;
use SmartReport\Core\Lang;

function e($value)
{
    if (is_null($value)) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function starts($haystack, $needle)
{
    return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
}

function url($path = '/')
{
    if ($path === '') {
        $path = '/';
    }
    if (!starts($path, '/')) {
        $path = '/' . $path;
    }
    if (smr_pretty_urls()) {
        return SMR_BASE_URL . $path;
    }
    $query = '';
    $qPos = strpos($path, '?');
    if ($qPos !== false) {
        $query = substr($path, $qPos + 1);
        $path = substr($path, 0, $qPos);
    }
    return SMR_BASE_URL . '/index.php?route=' . rawurlencode($path) . ($query !== '' ? '&' . $query : '');
}

function asset($path)
{
    return SMR_BASE_URL . '/assets/' . ltrim($path, '/');
}

/**
 * Whether the router may emit pretty URL paths (e.g. /calls). Default is OFF:
 * the panel runs on plain PHP URLs (/index.php?route=...) that work on any
 * Apache/nginx setup without mod_rewrite or AllowOverride. To opt into pretty
 * URLs, set "pretty_urls" => true in config/app.php AND enable rewriting for
 * the module directory; the router accepts both forms on input regardless.
 */
function smr_pretty_urls()
{
    if (SMR_CLI) {
        return true;
    }
    static $mode = null;
    if ($mode !== null) {
        return $mode;
    }
    $sApp = \SmartReport\Core\Config::get('app', []);
    $mode = !empty($sApp['pretty_urls']);
    return $mode;
}

function t($key, array $params = [])
{
    return Lang::t($key, $params);
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function set_flash($type, $message)
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_messages()
{
    if (PHP_SAPI === 'cli') {
        return [];
    }
    $messages = isset($_SESSION['_flash']) ? $_SESSION['_flash'] : [];
    unset($_SESSION['_flash']);
    return $messages;
}

function format_duration($seconds)
{
    $seconds = (int) $seconds;
    if ($seconds < 0) {
        $seconds = 0;
    }
    if ($seconds < 60) {
        return $seconds . 's';
    }
    $h = (int) floor($seconds / 3600);
    $m = (int) floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    if ($h > 0) {
        return sprintf('%dh %02dm %02ds', $h, $m, $s);
    }
    return sprintf('%dm %02ds', $m, $s);
}

function disposition_class($disposition)
{
    switch (strtoupper($disposition)) {
        case 'ANSWERED':
            return 'success';
        case 'NO ANSWER':
        case 'CANCEL':
            return 'warning';
        case 'BUSY':
        case 'FAILED':
        case 'CONGESTION':
            return 'danger';
        default:
            return 'neutral';
    }
}

function direction_badge_class($direction)
{
    switch ($direction) {
        case 'in':
            return 'info';
        case 'out':
            return 'primary';
        case 'int':
            return 'neutral';
        default:
            return 'neutral';
    }
}

function missed_badge_class($reason)
{
    switch ($reason) {
        case 'busy':
        case 'failed':
            return 'danger';
        case 'voicemail':
            return 'info';
        default:
            return 'warning';
    }
}

function str_limit($value, $length = 40)
{
    if (function_exists('mb_strlen')) {
        if (mb_strlen($value) <= $length) {
            return $value;
        }
        return mb_substr($value, 0, $length) . '…';
    }
    if (strlen($value) <= $length) {
        return $value;
    }
    return substr($value, 0, $length) . '…';
}

function human_size($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

function redirect_to($path)
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    header('Location: ' . url($path));
    exit;
}

function current_path()
{
    if (PHP_SAPI === 'cli') {
        return '/';
    }
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $path = (string) parse_url($uri, PHP_URL_PATH);
    $base = SMR_BASE_URL;
    if ($base !== '' && starts($path, $base)) {
        $path = substr($path, strlen($base));
    }
    if ($path === '/index.php') {
        $path = isset($_GET['route']) && (string) $_GET['route'] !== '' ? '/' . ltrim((string) $_GET['route'], '/') : '/';
    } elseif (starts($path, '/index.php/')) {
        $path = '/' . substr($path, strlen('/index.php/'));
    }
    if ($path === '' || $path === false) {
        $path = '/';
    }
    if (!starts($path, '/')) {
        $path = '/' . $path;
    }
    return $path;
}

function partial($template, array $data = [])
{
    return \SmartReport\Core\View::partial($template, $data);
}

function icon($name, $size = 18)
{
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.96.35 1.9.68 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.33 1.85.56 2.81.68A2 2 0 0 1 22 16.92z"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'play' => '<polygon points="6 3 20 12 6 21 6 3"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'dot' => '<circle cx="12" cy="12" r="5"/>',
    ];
    $body = isset($icons[$name]) ? $icons[$name] : $icons['dot'];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

/**
 * PHP 5.4/5.5 compatibility helpers.
 */

function smr_random_bytes($length)
{
    $length = (int) $length;
    if ($length <= 0) {
        return '';
    }
    if (function_exists('random_bytes')) {
        return random_bytes($length); // PHP 7.0+
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if ($bytes !== false && $strong) {
            return $bytes;
        }
    }
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= chr(mt_rand(0, 255));
    }
    return $out;
}

function smr_hash_equals($known, $user)
{
    $known = (string) $known;
    $user = (string) $user;
    if (function_exists('hash_equals')) {
        return hash_equals($known, $user); // PHP 5.6+
    }
    $len = strlen($known);
    if ($len !== strlen($user)) {
        return false;
    }
    $result = 0;
    for ($i = 0; $i < $len; $i++) {
        $result |= ord($known[$i]) ^ ord($user[$i]);
    }
    return $result === 0;
}

function smr_password_hash($password)
{
    $password = (string) $password;
    if (PHP_VERSION_ID >= 50500 && function_exists('password_hash') && defined('PASSWORD_DEFAULT')) {
        return password_hash($password, PASSWORD_DEFAULT); // PHP 5.5+
    }
    $salt = bin2hex(smr_random_bytes(16));
    return crypt($password, '$2y$10$' . $salt);
}

function smr_password_verify($password, $hash)
{
    $password = (string) $password;
    $hash = (string) $hash;
    if ($hash === '' || strpos($hash, '$') === false) {
        return false;
    }
    if (PHP_VERSION_ID >= 50500 && function_exists('password_verify')) {
        return password_verify($password, $hash); // PHP 5.5+
    }
    return smr_hash_equals(crypt($password, $hash), $hash);
}