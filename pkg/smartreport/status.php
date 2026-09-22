<?php
/**
 * Smart-Report deploy probe — public, no DB, no secrets.
 *
 * Prints which tree PHP is actually executing at this URL: version parsed from
 * the code, the path on disk, file mtimes/hashes and a few feature checks.
 * This is the arbiter for "the panel still shows the old version": whatever
 * THIS page prints is what the web server is really running.
 *
 * PHP 5.4-safe on purpose (no short arrays beyond 5.4, no arrow fns).
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$root = __DIR__;
$bootstrap = $root . '/app/core/bootstrap.php';

echo "Smart-Report deploy probe\n";
echo "=========================\n";
echo "served_from: " . $root . "\n";

$codeVersion = '(bootstrap.php NOT FOUND)';
if (is_file($bootstrap)) {
    $bootSrc = (string) file_get_contents($bootstrap);
    if (preg_match("/define\\('SMR_VERSION',\\s*'([^']+)'\\)/", $bootSrc, $m)) {
        $codeVersion = $m[1];
    } else {
        $codeVersion = '(SMR_VERSION not parseable)';
    }
}
echo "code_version: " . $codeVersion . "\n";
echo "php: " . PHP_VERSION . "\n";

// Bytecode freshness: opcache caches COMPILED files per process. On
// Issabel/Rocky PHP runs as php-fpm, whose opcache survives `systemctl restart
// httpd` — the disk can hold the new version while fpm keeps executing the old
// bytecode. The probe runs in the web context, so opcache_get_status() shows
// exactly what the serving process has cached and when it was compiled.
$stale = false;
if (function_exists('opcache_get_status')) {
    $ocStatus = opcache_get_status(false);
    if (is_array($ocStatus) && !empty($ocStatus['scripts'])) {
        $bootReal = realpath($bootstrap);
        if ($bootReal !== false && isset($ocStatus['scripts'][$bootReal])) {
            $cachedAt = (int) $ocStatus['scripts'][$bootReal]['timestamp'];
            $diskAt = @filemtime($bootReal);
            if ($diskAt && $cachedAt < $diskAt) {
                $stale = true;
                echo "opcache_bootstrap: cached=" . date('Y-m-d H:i:s', $cachedAt) . " disk=" . date('Y-m-d H:i:s', $diskAt) . "\n";
                echo "STALE BYTECODE: fpm is executing a bootstrap compiled " . ($diskAt - $cachedAt) . "s before the current file.\n";
                echo "  FIX: systemctl restart php-fpm (restart httpd alone does NOT clear fpm's opcache).\n";
            } else {
                echo "opcache_bootstrap: cached=" . date('Y-m-d H:i:s', $cachedAt) . " disk=" . date('Y-m-d H:i:s', (int) $diskAt) . " (fresh)\n";
            }
        } else {
            echo "opcache_bootstrap: not cached (serves from disk on next request)\n";
        }
    } else {
        echo "opcache: no status available (disabled or not active in this SAPI)\n";
    }
}

$bootMtime = is_file($bootstrap) ? filemtime($bootstrap) : 0;
echo "bootstrap_mtime: " . ($bootMtime ? date('Y-m-d H:i:s', $bootMtime) . ' (' . $bootMtime . ')' : '-') . "\n";

$marker = $root . '/data/installed_version';
echo "installed_marker: " . (is_file($marker) ? trim((string) file_get_contents($marker)) : '(none)') . "\n";

$appCfg = $root . '/config/app.php';
if (is_file($appCfg)) {
    $cfg = @include $appCfg;
    echo "app_config_version: " . (is_array($cfg) && isset($cfg['version']) ? (string) $cfg['version'] : '(missing)') . "\n";
} else {
    echo "app_config_version: (config/app.php not installed yet)\n";
}

echo "file_hashes:\n";
$files = [
    'app/core/bootstrap.php',
    'app/features/calls/models/CdrModel.php',
    'app/features/livereport/views/index.php',
    'app/templates/layout.php',
    'config/languages/en.php',
];
foreach ($files as $f) {
    $p = $root . '/' . $f;
    echo "  " . (is_file($p) ? md5_file($p) : str_repeat('-', 32)) . "  " . $f . "\n";
}

echo "checks:\n";
$cdrSrc = is_file($root . '/app/features/calls/models/CdrModel.php') ? (string) file_get_contents($root . '/app/features/calls/models/CdrModel.php') : '';
echo "  has_digits_method: " . (strpos($cdrSrc, 'function digits') !== false ? 'yes' : 'NO') . "\n";
$langSrc = is_file($root . '/config/languages/en.php') ? (string) file_get_contents($root . '/config/languages/en.php') : '';
echo "  has_live_lang_keys: " . (strpos($langSrc, 'live.setup_step1') !== false ? 'yes' : 'NO') . "\n";

echo "server_time: " . date('c') . "\n";
