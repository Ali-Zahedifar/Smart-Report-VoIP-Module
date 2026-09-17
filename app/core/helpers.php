<?php

declare(strict_types=1);

use SmartReport\Core\Csrf;
use SmartReport\Core\Lang;

function e($value): string
{
    if (is_null($value)) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function starts(string $haystack, string $needle): bool
{
    return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
}

function url(string $path = '/'): string
{
    if ($path === '') {
        $path = '/';
    }
    if (!starts($path, '/')) {
        $path = '/' . $path;
    }
    return SMR_BASE_URL . $path;
}

function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

function t(string $key, array $params = []): string
{
    return Lang::t($key, $params);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function set_flash(string $type, string $message): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_messages(): array
{
    if (PHP_SAPI === 'cli') {
        return [];
    }
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function format_duration(int $seconds): string
{
    if ($seconds < 0) {
        $seconds = 0;
    }
    if ($seconds < 60) {
        return $seconds . 's';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0) {
        return sprintf('%dh %02dm %02ds', $h, $m, $s);
    }
    return sprintf('%dm %02ds', $m, $s);
}

function disposition_class(string $disposition): string
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

function str_limit(string $value, int $length = 40): string
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

function human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

function redirect_to(string $path): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    header('Location: ' . url($path));
    exit;
}

function current_path(): string
{
    if (PHP_SAPI === 'cli') {
        return '/';
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = (string) parse_url($uri, PHP_URL_PATH);
    $base = SMR_BASE_URL;
    if ($base !== '' && starts($path, $base)) {
        $path = substr($path, strlen($base));
    }
    if ($path === '' || $path === false) {
        $path = '/';
    }
    if (!starts($path, '/')) {
        $path = '/' . $path;
    }
    return $path;
}

function partial(string $template, array $data = []): string
{
    return \SmartReport\Core\View::partial($template, $data);
}

function icon(string $name, int $size = 18): string
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
    $body = $icons[$name] ?? $icons['dot'];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}