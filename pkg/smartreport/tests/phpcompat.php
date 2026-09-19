<?php
/**
 * Smart-Report PHP compatibility + autoload regression harness.
 *
 * Usage (from the application root):
 *   php tests/phpcompat.php
 *
 * Checks:
 *   1. Every declared class/interface/trait can be autoloaded (catches
 *      filesystem path/casing bugs that only appear on case-sensitive
 *      filesystems such as Linux).
 *   2. No PHP 5.5+/5.6+/7.x/8.x-only syntax or functions are used that
 *      would break on PHP 5.4 (Issabel 4). Known compat shims in
 *      app/core/helpers.php are allowed for the crypto/password markers.
 *
 * Exit code 0 = all good, 1 = failures found.
 */

define('SMR_ROOT', dirname(__DIR__));
define('SMR_COMPAT_SCRIPT', true);

require SMR_ROOT . '/app/core/bootstrap.php';

$failures = array();
$checks = array();

$excludeDirs = array(
    'data',
    'storage',
    'dist',
    'pkg',
    '.git',
);

function smr_compat_iterate($dir, $excludeDirs)
{
    $out = array();
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isDir()) {
            continue;
        }
        $fileName = $file->getFilename();
        if (substr($fileName, -4) !== '.php') {
            continue;
        }
        $rel = substr($file->getPathname(), strlen(SMR_ROOT) + 1);
        $rel = str_replace('\\', '/', $rel);
        $skip = false;
        foreach ($excludeDirs as $ex) {
            if (strpos($rel, $ex . '/') === 0) {
                $skip = true;
                break;
            }
        }
        if (!$skip) {
            $out[] = $rel;
        }
    }
    sort($out);
    return $out;
}

function smr_compat_print($ok, $label, $detail)
{
    if (!$ok) {
        $GLOBALS['failures'][] = $label . ($detail !== '' ? ': ' . $detail : '');
    }
    printf('[%s] %s%s' . "\n", $ok ? ' OK ' : 'FAIL', $label, $detail !== '' ? ' -> ' . $detail : '');
}

$files = smr_compat_iterate(SMR_ROOT, $excludeDirs);

/* ---------------------------------------------------------------- */
/* 1. Autoload / class-resolution probe                              */
/* ---------------------------------------------------------------- */
$declared = array();
foreach ($files as $rel) {
    $src = (string) file_get_contents(SMR_ROOT . '/' . $rel);
    $ns = '';
    if (preg_match('/\bnamespace\s+([A-Za-z_\\\]+);/', $src, $m)) {
        $ns = trim($m[1], '\\');
    }
    if (preg_match_all('/\b(?:abstract\s+|final\s+)*(class|interface|trait)\s+([A-Za-z_]\w*)(?=\s*(?:extends|implements|\{|\(|\())/', $src, $mm)) {
        foreach ($mm[1] as $i => $type) {
            if ($ns !== '') {
                $declared[] = array('type' => $type, 'name' => $ns . '\\' . $mm[2][$i], 'file' => $rel);
            }
        }
    }
}

foreach ($declared as $d) {
    $ok = false;
    if ($d['type'] === 'class') {
        $ok = class_exists($d['name']);
    } elseif ($d['type'] === 'interface') {
        $ok = interface_exists($d['name']);
    } else {
        $ok = trait_exists($d['name']);
    }
    smr_compat_print($ok, 'resolve ' . $d['name'], $d['file']);
    $checks[] = 1;
}

/* ---------------------------------------------------------------- */
/* 2. PHP 5.5+ / 5.6+ / 7.x / 8.x syntax & function scan            */
/* ---------------------------------------------------------------- */
$markers = array(
    array('array class const', '/\bconst\s+\w+\s*=\s*(\[|array\s*\()/'),
    array('null coalescing operator', '/\?\?/'),
    array('spaceship operator', '/<(?:\x3d)>/'),
    array('power operator', '/(?<![\/\*])\*\*/'),
    array('variadic param', '/\.\.\.(?:\$|\[)/'),
    array('return type', '/\bfunction\s+(?:[A-Za-z_]\w*\s*)?\([^)]*\)\s*:\s*[A-Za-z_\\\\]/'),
    array('nullable type', '/\?\s*(?:string|int|float|bool|array)\b/'),
    array('declared strict_types', '/\bdeclare\s*\(\s*strict_types\b/'),
    array('anonymous class', '/new\s+class\s*[{(]/'),
    array('arrow fn expression', '/\bfn\s*\(/'),
    array('class constant access', '/::(?:class)\b/'),
    array('finally', '/\bfinally\s*\{/'),
    array('yield', '/\byield\s*(?:\[|\;|\-|\>|\()/'),
    array('array_column', '/\barray_c[o]lumn\s*\(/'),
    array('intdiv', '/\bintdiv\s*\(/'),
    array('match expression', 'MATCH_EXPR'),
    array('str_contains', '/\bstr_[c]ontains\s*\(/'),
    array('str_starts_with', '/\bstr_[s]tarts_with\s*\(/'),
    array('str_ends_with', '/\bstr_[e]nds_with\s*\(/'),
    array('array_key_first', '/\barray_key_[f]irst\s*\(/'),
    array('array_key_last', '/\barray_key_[l]ast\s*\(/'),
    array('get_debug_type', '/\bget_[d]ebug_type\s*\(/'),
    array('foreach destructuring', '/\bforeach\s*\([^)]*as\s*\[/'),
    array('raw random_bytes', '/\brandom_bytes\s*\(/'),
    array('raw random_int', '/\brandom_int\s*\(/'),
    array('raw password_hash', '/\bpassword_hash\s*\(/'),
    array('raw password_verify', '/\bpassword_verify\s*\(/'),
    array('raw hash_equals', '/\bhash_equals\s*\(/'),
    array('raw sodium_crypto', '/\bsodium_crypt[o]genbytes\s*\(/'),
);

$allowedHelpers = array('app/core/helpers.php');

foreach ($files as $rel) {
    $src = (string) file_get_contents(SMR_ROOT . '/' . $rel);
    foreach ($markers as $mrk) {
        $isCrypto = in_array($mrk[0], array(
            'raw random_bytes', 'raw random_int', 'raw password_hash',
            'raw password_verify', 'raw hash_equals', 'raw sodium_crypto',
        ), true);
        if ($isCrypto && in_array($rel, $allowedHelpers, true)) {
            continue;
        }
        if ($mrk[1] === 'MATCH_EXPR') {
            $offsets = smr_compat_match_expr($src);
        } else {
            $offsets = array();
            if (preg_match_all($mrk[1], $src, $hm, PREG_OFFSET_CAPTURE)) {
                foreach ($hm[0] as $hit) {
                    $offsets[] = $hit[1];
                }
            }
        }
        if (count($offsets) > 0) {
            smr_compat_print(false, $mrk[0], $rel . ' (line ' . smr_compat_lineOf($src, $offsets[0]) . ')');
            $checks[] = 1;
        }
    }
}

/* ---------------------------------------------------------------- */
/* Summary                                                           */
/* ---------------------------------------------------------------- */
if (count($failures) === 0) {
    echo "\nPHP compatibility + autoload scan: PASS (no 5.5+/5.6+/7.x/8.x-only constructs found)\n";
    exit(0);
}

echo "\nPHP compatibility + autoload scan: " . count($failures) . " issue(s) found\n";
exit(1);

function smr_compat_lineOf($src, $offset)
{
    if ($offset === false || $offset < 0 || $offset >= strlen($src)) {
        return '?';
    }
    return substr_count(substr($src, 0, $offset), "\n") + 1;
}

function smr_compat_match_expr($src)
{
    $out = array();
    if (!preg_match_all('/\bmatch\s*\(/', $src, $hm, PREG_OFFSET_CAPTURE)) {
        return $out;
    }
    foreach ($hm[0] as $hit) {
        $offset = $hit[1];
        $before = substr($src, max(0, $offset - 32), $offset < 32 ? $offset : 32);
        if ($before === '') {
            $out[] = $offset;
            continue;
        }
        if (preg_match('/(?:->|::|function)\s*$/', $before)) {
            continue;
        }
        $out[] = $offset;
    }
    return $out;
}