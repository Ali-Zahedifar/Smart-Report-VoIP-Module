<?php

use SmartReport\Core\Config;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only. Run: php install/uninstaller.php');
}

$root = dirname(__DIR__);
define('SMR_DEBUG', '0');
define('SMR_ROOT', $root);

require $root . '/app/core/bootstrap.php';

function out($message)
{
    fwrite(STDOUT, $message . PHP_EOL);
}

$parsed = [];
for ($i = 1, $n = count($argv); $i < $n; $i++) {
    if (preg_match('/^--([a-zA-Z0-9-]+)=(.*)$/', $argv[$i], $m)) {
        $parsed[strtolower($m[1])] = $m[2];
    } elseif (preg_match('/^--([a-zA-Z0-9-]+)$/', $argv[$i], $m)) {
        $key = strtolower($m[1]);
        if (isset($argv[$i + 1]) && strpos($argv[$i + 1], '--') !== 0) {
            $parsed[$key] = $argv[++$i];
        } else {
            $parsed[$key] = true;
        }
    }
}

out('Smart-Report Uninstaller');
out('========================');
out('');

if (!isset($parsed['yes'])) {
    fwrite(STDOUT, 'This will DELETE the module database and ALL module data.' . PHP_EOL);
    fwrite(STDOUT, 'Type "yes" to continue: ');
    $confirm = trim((string) fgets(STDIN));
    if ($confirm !== 'yes') {
        out('Aborted.');
        exit(0);
    }
}

$cfgMissing = !Config::fileExists('database');
$dbName = $cfgMissing ? 'smartreport' : Config::get('database.name', 'smartreport');
$dbUser = $cfgMissing ? 'smartreport' : Config::get('database.user', 'smartreport');
$dbPass = $cfgMissing ? '' : Config::get('database.pass', '');

$dbHost = isset($parsed['mysql-host']) ? (string) $parsed['mysql-host'] : ($cfgMissing ? 'localhost' : (string) Config::get('database.host', 'localhost'));
$dbPort = isset($parsed['mysql-port']) ? (int) $parsed['mysql-port'] : ($cfgMissing ? 3306 : (int) Config::get('database.port', 3306));
$adminUser = isset($parsed['mysql-user']) ? (string) $parsed['mysql-user'] : $dbUser;
$adminPass = array_key_exists('mysql-pass', $parsed) ? (string) $parsed['mysql-pass'] : $dbPass;

$dropped = false;
if (!$cfgMissing) {
    try {
        $db = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort),
            $adminUser,
            $adminPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true, PDO::ATTR_TIMEOUT => 8]
        );
        $db->exec('DROP DATABASE IF EXISTS `' . $dbName . '`');
        $dropped = true;
        out('[OK] Dropped database: ' . $dbName);
        try {
            $db->exec("DROP USER IF EXISTS '" . $dbUser . "'@'localhost'");
            out('[OK] Dropped database user: ' . $dbUser);
        } catch (Exception $e) {
            out('[i] Could not drop database user automatically (drop it manually if needed).');
        }
    } catch (Exception $e) {
        out('[!] Could not drop database automatically: ' . $e->getMessage());
    }
} else {
    out('[!] No module DB credentials found; skipping automatic database drop.');
}

if (Config::fileExists('database')) {
    @unlink(Config::dir() . '/database.php');
    out('[OK] Removed config/database.php');
}
if (Config::fileExists('external')) {
    @unlink(Config::dir() . '/external.php');
    out('[OK] Removed config/external.php');
}

foreach ([
    SMR_DATA . '/cache',
    SMR_DATA . '/logs',
    SMR_STORAGE . '/exports',
] as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}
out('[OK] Cleared cache/log/export files.');

if (!$dropped) {
    out('');
    out('To fully remove the MySQL objects, run as a MySQL admin:');
    out('  DROP DATABASE IF EXISTS `' . $dbName . '`;');
    out('  DROP USER IF EXISTS ' . "'" . $dbUser . "'@'localhost';");
    out('');
}

out('');
out('Manual cleanup (not automated):');
out('  - If you added an Issabel menu hook, remove it.');
out('  - If you added a cron entry for scheduled reports, remove it.');
out('  - Delete the module folder: rm -rf ' . SMR_ROOT);
out('');
out('Done. Smart-Report has been uninstalled.');
exit(0);