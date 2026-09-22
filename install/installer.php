<?php

use SmartReport\Core\Config;
use SmartReport\Core\Database;
use SmartReport\Core\FeatureRegistry;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only. Run: php install/installer.php');
}

$root = dirname(__DIR__);
define('SMR_DEBUG', '0');
define('SMR_ROOT', $root);

require $root . '/app/core/bootstrap.php';

function parseArgs(array $argv)
{
    $out = [];
    for ($i = 1, $n = count($argv); $i < $n; $i++) {
        $arg = $argv[$i];
        if (!preg_match('/^--([a-z][a-z0-9-]*)(=(.*))?$/i', $arg, $m)) {
            continue;
        }
        $out[strtolower($m[1])] = isset($m[3]) ? $m[3] : true;
    }
    return $out;
}

function out($message)
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function ask($label, $default = '', $required = false)
{
    $suffix = $default !== '' ? ' [' . $default . ']' : '';
    do {
        fwrite(STDOUT, $label . $suffix . ': ');
        $value = trim((string) fgets(STDIN));
        if ($value === '') {
            $value = $default;
        }
    } while ($required && $value === '');
    return $value;
}

function askSecret($label)
{
    fwrite(STDOUT, $label . ': ');
    return trim((string) fgets(STDIN));
}

function randomPassword($length = 20)
{
    return substr(bin2hex(smr_random_bytes((int) ceil($length / 2))), 0, $length);
}

function readIni($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $raw = @parse_ini_file($path);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $out[$k] = $v;
            }
        } else {
            $out[$key] = $value;
        }
    }
    return $out;
}

function quote($value)
{
    return "'" . str_replace("'", "''", $value) . "'";
}

$args = [
    'mysql-host' => 'localhost',
    'mysql-port' => '3306',
    'mysql-user' => 'root',
    'mysql-pass' => '',
    'db-name' => 'smartreport',
    'db-user' => 'smartreport',
    'root-user' => 'root',
    'admin-user' => 'admin',
    'monitor-dir' => '/var/spool/asterisk/monitor',
    'ami-host' => '127.0.0.1',
    'ami-port' => '5038',
    'ami-user' => 'admin',
    'ami-pass' => '',
    'chown' => '',
];

$parsed = parseArgs($argv);
$args = array_merge($args, $parsed);

out('Smart-Report Installer');
out('======================');
out('');

out('Detecting environment...');
$issabel = readIni('/etc/issabelpbx.conf');
$cdrConf = array_merge(readIni('/etc/asterisk/cdr_mysql.conf'), readIni('/etc/asterisk/cdr_adaptive_odbc.conf'));
$hadIssabel = count($issabel) > 0;
$hadCdr = count($cdrConf) > 0;

if (!version_compare(PHP_VERSION, '5.4.0', '>=')) {
    fwrite(STDERR, 'PHP 5.4 or newer required (found ' . PHP_VERSION . ').' . PHP_EOL);
    exit(1);
}
$missingExt = [];
foreach (['pdo', 'pdo_mysql', 'json', 'session', 'filter', 'openssl'] as $ext) {
    if (!extension_loaded($ext)) {
        $missingExt[] = $ext;
    }
}
if (count($missingExt) > 0) {
    fwrite(STDERR, 'Missing PHP extensions: ' . implode(', ', $missingExt) . PHP_EOL);
    exit(1);
}
out('  PHP ' . PHP_VERSION . ' [OK]');
out('  Issabel config found: ' . ($hadIssabel ? 'yes' : 'no'));
out('  CDR config found: ' . ($hadCdr ? 'yes' : 'no'));
out('');

$interactive = !isset($parsed['non-interactive']);
if ($interactive && isset($parsed['mysql-pass']) === false) {
    $args['mysql-pass'] = askSecret('MySQL install account password (--mysql-user: ' . $args['mysql-user'] . ')');
}

$adminConn = null;
try {
    $adminConn = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $args['mysql-host'], (int) $args['mysql-port']),
        $args['mysql-user'],
        (string) $args['mysql-pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true, PDO::ATTR_TIMEOUT => 8]
    );
    out('[OK] Connected to MySQL at ' . $args['mysql-host']);
} catch (Exception $e) {
    fwrite(STDERR, 'Could not connect to MySQL as ' . $args['mysql-user'] . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$dbName = (string) $args['db-name'];
$dbUser = (string) $args['db-user'];
$dbPass = isset($parsed['db-pass']) && $parsed['db-pass'] !== '' ? (string) $parsed['db-pass'] : randomPassword();

out('');
out('Creating module database "' . $dbName . '"...');
$adminConn->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

out('Creating module user "' . $dbUser . '"...');
try {
    $adminConn->exec("CREATE USER IF NOT EXISTS '" . $dbUser . "'@'localhost' IDENTIFIED BY " . quote($dbPass));
} catch (Exception $e) {
    // user may already exist on older MariaDB/MySQL
}
try {
    $adminConn->exec("ALTER USER '" . $dbUser . "'@'localhost' IDENTIFIED BY " . quote($dbPass));
} catch (Exception $e) {
    $adminConn->exec("SET PASSWORD FOR '" . $dbUser . "'@'localhost' = PASSWORD(" . quote($dbPass) . ")");
}
$adminConn->exec('GRANT ALL PRIVILEGES ON `' . $dbName . '`.* TO ' . quote($dbUser) . '@localhost');
$adminConn->exec('FLUSH PRIVILEGES');

$moduleCfg = [
    'host' => (string) $args['mysql-host'],
    'port' => 3306,
    'name' => $dbName,
    'user' => $dbUser,
    'pass' => $dbPass,
    'charset' => 'utf8mb4',
];

$db = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $args['mysql-host'], 3306, $dbName),
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::ATTR_TIMEOUT => 8,
    ]
);

out('Applying schema...');
$db->exec((string) file_get_contents(SMR_ROOT . '/install/schema.sql'));
out('Applying seed data...');
$db->exec((string) file_get_contents(SMR_ROOT . '/install/seed.sql'));

out('');
out('Configuring external connections...');

$asteriskHost = isset($parsed['ast-host']) ? (string) $parsed['ast-host'] : ($hadIssabel && !empty($issabel['AMPDBHOST']) ? (string) $issabel['AMPDBHOST'] : 'localhost');
    $asteriskName = isset($parsed['ast-name']) ? (string) $parsed['ast-name'] : ($hadIssabel && !empty($issabel['AMPDBNAME']) ? (string) $issabel['AMPDBNAME'] : 'asterisk');
    
    // Auto-use root credentials for asterisk DB if on same host and no explicit creds provided
    $asteriskUser = isset($parsed['ast-user']) ? (string) $parsed['ast-user'] : ($hadIssabel && !empty($issabel['AMPDBUSER']) ? (string) $issabel['AMPDBUSER'] : 'asteriskuser');
    $asteriskPass = isset($parsed['ast-pass']) ? (string) $parsed['ast-pass'] : ($hadIssabel && !empty($issabel['AMPDBPASS']) ? (string) $issabel['AMPDBPASS'] : '');
    
    // Auto-detect if asterisk DB is on same host as module DB, offer to use root creds
    $useRootForAsterisk = ($asteriskHost === $args['mysql-host'] || $asteriskHost === 'localhost') && $asteriskPass === '' && !isset($parsed['ast-pass']);
    if ($useRootForAsterisk) {
        $asteriskUser = $args['mysql-user'];
        $asteriskPass = (string) $args['mysql-pass'];
        out('  Asterisk: Auto-using root credentials (same host)');
    }

    $cdrHost = isset($parsed['cdr-host']) ? (string) $parsed['cdr-host'] : ($hadCdr && !empty($cdrConf['dbhost']) ? (string) $cdrConf['dbhost'] : 'localhost');
    $cdrName = isset($parsed['cdr-name']) ? (string) $parsed['cdr-name'] : ($hadCdr && !empty($cdrConf['dbname']) ? (string) $cdrConf['dbname'] : 'asteriskcdrdb');
    
    // Auto-detect if CDR DB is on same host as module DB, offer to use root creds
    $cdrUser = isset($parsed['cdr-user']) ? (string) $parsed['cdr-user'] : ($hadCdr && !empty($cdrConf['dbuser']) ? (string) $cdrConf['dbuser'] : 'asteriskuser');
    $cdrPass = isset($parsed['cdr-pass']) ? (string) $parsed['cdr-pass'] : ($hadCdr && !empty($cdrConf['dbpass']) ? (string) $cdrConf['dbpass'] : '');
    
    $useRootForCdr = ($cdrHost === $args['mysql-host'] || $cdrHost === 'localhost') && $cdrPass === '' && !isset($parsed['cdr-pass']);
    if ($useRootForCdr) {
        $cdrUser = $args['mysql-user'];
        $cdrPass = (string) $args['mysql-pass'];
        out('  CDR: Auto-using root credentials (same host)');
    }

out('  CDR:      ' . $cdrUser . '@' . $cdrHost . '/' . $cdrName . ' (password: ' . ($cdrPass !== '' ? 'set' : 'EMPTY') . ($useRootForCdr ? ' [auto-root]' : '') . ')');
    out('  Asterisk: ' . $asteriskUser . '@' . $asteriskHost . '/' . $asteriskName . ' (password: ' . ($asteriskPass !== '' ? 'set' : 'EMPTY') . ($useRootForAsterisk ? ' [auto-root]' : '') . ')');

out('Granting read access to the Asterisk reference database...');
$grantedRef = false;
try {
    if ($asteriskName !== '' && $dbUser !== '' && $asteriskName !== $dbName) {
        $adminConn->exec('GRANT SELECT ON `' . $asteriskName . '`.* TO ' . quote($dbUser) . '@localhost');
        $adminConn->exec('FLUSH PRIVILEGES');
        $grantedRef = true;
    }
} catch (Exception $e) {
    $grantedRef = false;
}
out('  Routing reference grants: ' . ($grantedRef ? 'OK' : 'skipped (not applicable)'));

$external = [
    'cdr' => ['host' => $cdrHost, 'port' => 3306, 'name' => $cdrName, 'user' => $cdrUser, 'pass' => $cdrPass, 'charset' => 'utf8mb4'],
    'asterisk' => ['host' => $asteriskHost, 'port' => 3306, 'name' => $asteriskName, 'user' => $asteriskUser, 'pass' => $asteriskPass, 'charset' => 'utf8mb4'],
    'ami' => [
        'enabled' => false,
        'host' => (string) $args['ami-host'],
        'port' => (int) $args['ami-port'],
        'username' => (string) $args['ami-user'],
        'password' => (string) $args['ami-pass'],
    ],
    'routing' => [
        'extensions' => [], // optional manual overrides when the asterisk DB is unreachable
        'queues' => [],
        'ringgroups' => [],
        'dids' => [],
        'trunks' => [],
        'contexts' => ['in' => ['from-pstn', 'from-did', 'from-trunk'], 'out' => ['outbound-allroutes', 'out-']],
        'channels' => ['trunk' => ['PJSIP/', 'DAHDI/', 'IAX2/']],
    ],
    'monitor_dir' => (string) $args['monitor-dir'],
];

out('Writing config files...');
Config::write('database', $moduleCfg);
Config::write('external', $external);

out('Registering features...');
$featureStats = FeatureRegistry::sync();

$rootUser = (string) $args['root-user'];
$adminUser = (string) $args['admin-user'];
$rootPass = isset($parsed['root-pass']) && $parsed['root-pass'] !== '' ? (string) $parsed['root-pass'] : randomPassword(14);
$adminPass = isset($parsed['admin-pass']) && $parsed['admin-pass'] !== '' ? (string) $parsed['admin-pass'] : randomPassword(14);
$usersCreated = [];

$rootExists = (int) $db->query('SELECT COUNT(*) FROM smr_users WHERE role = ' . "'root'")->fetchColumn();
if ($rootExists === 0) {
    $db->prepare('INSERT INTO smr_users (username, password_hash, role, display_name, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())')
        ->execute([$rootUser, smr_password_hash($rootPass), 'root', 'Module Owner']);
    $usersCreated[] = $rootUser . ' (root)';
}

$adminExists = (int) $db->query('SELECT COUNT(*) FROM smr_users WHERE role = ' . "'admin'")->fetchColumn();
if ($adminExists === 0) {
    $db->prepare('INSERT INTO smr_users (username, password_hash, role, display_name, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())')
        ->execute([$adminUser, smr_password_hash($adminPass), 'admin', 'Administrator']);
    $usersCreated[] = $adminUser . ' (admin)';
}

out('Setting up data directories...');
foreach ([SMR_DATA, SMR_CACHE, SMR_LOGS, SMR_STORAGE, SMR_STORAGE . '/exports'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @chmod($dir, 0775);
    if ($args['chown'] !== '') {
        @chown($dir, $args['chown']);
    }
}
foreach (['database', 'external'] as $cfgName) {
    $cfgPath = SMR_CONFIG . '/' . $cfgName . '.php';
    if (is_file($cfgPath) && is_writable($cfgPath)) {
        @chmod($cfgPath, 0644);
        if ($args['chown'] !== '') {
            @chown($cfgPath, $args['chown']);
        }
    }
}
@file_put_contents(SMR_LOGS . '/app.log', '', FILE_APPEND);
@chmod(SMR_LOGS . '/app.log', 0664);

out('');
out('Final checks...');

// Stale-deploy guard: warn when the extracted tree's version differs from a
// previously installed marker (user upgrading by copying over an old dir).
$installedMarker = SMR_DATA . '/installed_version';
$prevVersion = is_file($installedMarker) ? trim((string) file_get_contents($installedMarker)) : '';
if ($prevVersion !== '' && $prevVersion !== SMR_VERSION) {
    out('  NOTE: previous install reported version ' . $prevVersion . ', this tree is ' . SMR_VERSION . '.');
    out('        If the panel still shows the old version afterwards, the web root was');
    out('        not fully replaced - re-extract the tarball into a clean directory.');
}
@file_put_contents($installedMarker, SMR_VERSION);

Database::reset();
try {
    Database::main()->fetchValue('SELECT 1');
    out('  Module DB: OK');
} catch (Exception $e) {
    out('  Module DB: FAILED - ' . $e->getMessage());
}
try {
    $hasCdr = false;
    foreach (Database::external('cdr')->fetchAll('SHOW TABLES') as $row) {
        if (in_array('cdr', array_values($row), true)) {
            $hasCdr = true;
        }
    }
    out('  CDR DB: ' . ($hasCdr ? 'OK (cdr table found)' : 'connected (no cdr table?)'));
} catch (Exception $e) {
    out('  CDR DB: FAILED - ' . $e->getMessage());
}

out('');
out('Web panel routing...');
out('  Plain PHP URLs: ON (index.php?route=...) - works without mod_rewrite/AllowOverride');
out('  Pretty URLs: optional - set "pretty_urls" => true in config/app.php and enable rewriting');

out('');
out('========================================================================');
out(' Smart-Report installation complete');
out('========================================================================');
out(' features created/updated : ' . $featureStats['created'] . ' / ' . $featureStats['updated']);
out(' module database          : ' . $dbName . ' (user: ' . $dbUser . ')');
out(' recording directory      : ' . $external['monitor_dir']);
out(' installed code version   : ' . SMR_VERSION);
out('');
if (!empty($usersCreated) && !isset($parsed['root-pass']) && !isset($parsed['admin-pass'])) {
    out(' USER CREDENTIALS (shown only this time - write them down):');
    if (in_array($rootUser . ' (root)', $usersCreated, true)) {
        out('   Root  ' . $rootUser . ' : ' . $rootPass);
    }
    if (in_array($adminUser . ' (admin)', $usersCreated, true)) {
        out('   Admin  ' . $adminUser . ' : ' . $adminPass);
    }
} else {
    out(' User credentials came from --root-pass / --admin-pass or already existed.');
}
out('');
out(' Web panel:  http://YOUR-SERVER/' . basename(SMR_ROOT) . '/index.php   (plain PHP URLs)');
out(' Log in and change passwords right away.');
out('========================================================================');
out('');
out(' IMPORTANT - restart the web server AND php-fpm now (fpm opcache survives an');
out(' httpd-only restart and keeps serving the OLD bytecode):');
out('   systemctl restart httpd');
out('   systemctl restart php-fpm');
out(' Then verify the deployed version (must print ' . SMR_VERSION . '):');
out('   curl -sL http://localhost/' . basename(SMR_ROOT) . '/status.php');
out(' (the status.php probe prints the code version the web server is really running;');
out('  -L follows the login redirect the marker page sits behind).');
out(' If it prints an older version, the web root was not fully replaced -');
out(' re-extract the tarball into a clean directory (rm -rf the old one first).');

exit(0);