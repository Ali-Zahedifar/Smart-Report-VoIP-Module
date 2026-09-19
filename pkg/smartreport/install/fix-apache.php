<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only. Run: php install/fix-apache.php');
}

$root = dirname(__DIR__);
define('SMR_DEBUG', '0');
define('SMR_ROOT', $root);

require $root . '/app/core/bootstrap.php';

function out($message)
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function web_base_url()
{
    $rootReal = realpath(SMR_ROOT);
    $candidates = array();
    if (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '') {
        $doc = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($doc !== false) {
            $candidates[] = $doc;
        }
    }
    foreach (array('/var/www/html', '/var/www', '/srv/www/html') as $dr) {
        $r = realpath($dr);
        if ($r !== false) {
            $candidates[] = $r;
        }
    }
    foreach ($candidates as $doc) {
        if ($rootReal !== false && strpos($rootReal, $doc) === 0) {
            return rtrim(substr($rootReal, strlen($doc)), '/');
        }
    }
    return null;
}

out('Smart-Report - optional Apache pretty-URL setup');
out('================================================');
out('The panel already works without this script (plain PHP URLs).');
out('This step OPTIONALLY enables clean /smartreport/login-style links.');
out('');

$moduleDir = realpath(SMR_ROOT);
if ($moduleDir === false) {
    fwrite(STDERR, 'Could not resolve module directory: ' . SMR_ROOT . PHP_EOL);
    exit(1);
}
out('Module directory: ' . $moduleDir);

$base = web_base_url();
if ($base === null) {
    fwrite(STDERR, 'Could not determine the module URL base. The plain PHP URLs remain in use.' . PHP_EOL);
    exit(1);
}
out('URL base: ' . ($base === '' ? '/' : $base));

$confText = '<Directory "' . $moduleDir . '">' . PHP_EOL .
    '    AllowOverride All' . PHP_EOL .
    '    Options -Indexes +FollowSymLinks' . PHP_EOL .
    '    Require all granted' . PHP_EOL .
    '</Directory>' . PHP_EOL;

$confCandidates = array(
    '/etc/httpd/conf.d/smartreport-rewrite.conf',
    '/etc/apache2/conf-enabled/smartreport-rewrite.conf',
    '/etc/apache2/conf.d/smartreport-rewrite.conf',
);
$sWritten = null;
foreach ($confCandidates as $candidate) {
    if (is_dir(dirname($candidate)) && is_writable(dirname($candidate))) {
        if (file_put_contents($candidate, $confText) !== false) {
            $sWritten = $candidate;
            break;
        }
    }
}

if ($sWritten === null) {
    out('WARNING: no writable Apache conf.d found. Create /etc/httpd/conf.d/smartreport-rewrite.conf with:');
    out('    <Directory "' . $moduleDir . '">');
    out('        AllowOverride All');
    out('        Options -Indexes +FollowSymLinks');
    out('        Require all granted');
    out('    </Directory>');
} else {
    out('Wrote: ' . $sWritten . ' (enables the bundled .htaccess for this directory only)');
}

$reloaded = false;
$output = array();
$rc = -1;
if (!$reloaded) {
    exec('systemctl reload httpd 2>&1', $output, $rc);
    if ($rc === 0) {
        $reloaded = true;
        out('Reloaded Apache: systemctl reload httpd [OK]');
    }
}
if (!$reloaded) {
    exec('service httpd reload 2>&1', $output, $rc);
    if ($rc === 0) {
        $reloaded = true;
        out('Reloaded Apache: service httpd reload [OK]');
    }
}
if (!$reloaded) {
    out('WARNING: could not reload Apache automatically. Run:  systemctl restart httpd');
}

out('');
out('To actually use clean pretty URLs, edit config/app.php and add:');
out('    "pretty_urls" => true,');
out('otherwise the panel keeps using plain PHP URLs (which always work).');
exit(0);