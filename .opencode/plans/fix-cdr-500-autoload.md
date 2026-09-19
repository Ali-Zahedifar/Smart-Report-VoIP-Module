# Fix: CDR credentials + empty-body 500 + autoload casing (build 1.0.0)

## Context
- Server 500 (blank body, `require(): Failed opening .../config/database.php`) — root cause: `Config::write()` created files `0640 root:root`; PHP-FPM user cannot read -> uncatchable fatal. Fixed by chmod 0644 + `is_readable` guard.
- `/smartreport/login` -> **Apache** 404: `.htaccess` rewrite not honored (`AllowOverride`). Server-side only (user applies).
- Autoload: 5 feature classes fail `class_exists()` on the server; tree+casing verified correct -> resolved empirically with a case-insensitive walker that is immune to any casing state.

## Apply the following exact replacements (old -> new)

### 1. `app/core/bootstrap.php` — autoloader (replace lines 31-49 block)

OLD:
```php
spl_autoload_register(function ($class) {
    $prefix = 'SmartReport\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $path = SMR_APP . '/' . str_replace('\\', '/', $rel) . '.php';
    if (!is_file($path)) {
        $pos = strrpos($rel, '\\');
        if ($pos === false) {
            return;
        }
        $dir = strtolower(substr($rel, 0, $pos));
        $path = SMR_APP . '/' . $dir . '/' . substr($rel, $pos + 1) . '.php';
    }
    if (is_file($path)) {
        require $path;
    }
});
```

NEW:
```php
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
```

### 2. `app/core/Config.php` — guard before fatal require

OLD:
```php
        $path = self::dir() . '/' . $name . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('Configuration file not found: ' . $name);
        }
        $data = require $path;
```

NEW:
```php
        $path = self::dir() . '/' . $name . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('Configuration file not found: ' . $name);
        }
        if (!is_readable($path)) {
            throw new RuntimeException(
                'Configuration file is not readable by the current user: ' . $path
                . ' (blank HTTP 500? fix with: chmod 644 ' . $path . ' then chown -R <web-user> ' . SMR_ROOT . ')'
            );
        }
        $data = require $path;
```

### 3. `app/core/Config.php` — write mode

OLD: `@chmod($path, 0640);`
NEW: `@chmod($path, 0644);`

### 4. `install/installer.php` — `readIni()` flatten (replace the whole function)

OLD:
```php
function readIni($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $raw = @parse_ini_file($path);
    return is_array($raw) ? $raw : [];
}
```

NEW:
```php
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
```

### 5. `install/installer.php` — diagnostics after CDR/asterisk resolution

Insert after the `$cdrPass = ...` line and before `$external = [`:

```php
out('  CDR:      ' . $cdrUser . '@' . $cdrHost . '/' . $cdrName . ' (password: ' . ($cdrPass !== '' ? 'set' : 'EMPTY') . ')');
out('  Asterisk: ' . $asteriskUser . '@' . $asteriskHost . '/' . $asteriskName . ' (password: ' . ($asteriskPass !== '' ? 'set' : 'EMPTY') . ')');
```

### 6. `install/installer.php` — chmod/chown config files (insert after the data-dirs `foreach` block, before the `@file_put_contents(SMR_LOGS . '/app.log', '', FILE_APPEND);` line)

```php
foreach (['database', 'external'] as $cfgName) {
    $cfgPath = SMR_CONFIG . '/' . $cfgName . '.php';
    if (is_file($cfgPath) && is_writable($cfgPath)) {
        @chmod($cfgPath, 0644);
        if ($args['chown'] !== '') {
            @chown($cfgPath, $args['chown']);
        }
    }
}
```

## Validate (Windows dev box)
```
New-PSDrive -Name P54 -PSProvider FileSystem 2>$null
& C:\Users\Hades\AppData\Local\Temp\opencode\php54\php.exe -l app/core/bootstrap.php
& C:\Users\Hades\AppData\Local\Temp\opencode\php54\php.exe -l app/core/Config.php
& C:\Users\Hades\AppData\Local\Temp\opencode\php54\php.exe -l install/installer.php
# repeat -l + phpcompat under 5.4.45, 5.6.40 (C:\Users\Hades\AppData\Local\Temp\opencode\php56\php.exe), 8.2 (C:\xampp\php\php.exe)
C:\xampp\php\php.exe tests/phpcompat.php       # expect PASS
```
readIni unit test: temp files with `[global]`/`[adaptive_odbc]`/`[issabelpbx]` sections -> assert flat keys `dbhost`/`dbpass`/`AMPDBNAME`.
XAMPP smoke: re-install (drop DB, fresh install), then `/login`, `/`, `/calls` all 200.

## Rebuild
- Stage `pkg\smartreport\` from the working tree excluding `config/database.php`, `config/external.php`, `data/`, `storage/exports`, `dist/`, `.git`.
- `tar -czf dist/smartreport-1.0.0.tar.gz -C pkg smartreport`
- `Get-FileHash dist\smartreport-1.0.0.tar.gz -Algorithm SHA256` -> update `.sha256`.
- Verify archive contains `tests/phpcompat.php`, lowercase feature dirs, and the new autoloader.

## Deploy (server, after user applies AllowOverride All for /var/www/html + `systemctl restart httpd`)
1. Clean re-extract: `rm -rf /var/www/html/smartreport` then `tar -xzf smartreport-1.0.0.tar.gz -C /var/www/html --strip-components=1`.
2. `chown -R asterisk:asterisk /var/www/html/smartreport` (match FPM user in `/etc/php-fpm.d/www.conf`).
3. `sudo php install/installer.php --non-interactive --mysql-user=root --mysql-pass='<admin-db-pass>' --cdr-user=root --cdr-pass='<cdr-db-pass>' --chown=asterisk`
4. `systemctl restart php-fpm httpd`.
5. Gate: `php tests/phpcompat.php` -> PASS; `curl -sk https://127.0.0.1/smartreport/login` -> 200; panel login; Settings -> CDR DB OK; All Calls lists records.
6. If the 5 FAILs persist post-walker: `cd /var/www/html/smartreport && php -r 'define("SMR_ROOT","/var/www/html/smartreport"); require "app/core/bootstrap.php"; var_dump(class_exists("SmartReport\\Features\\Calls\\Controllers\\CallsController")); var_dump(is_file("app/features/calls/controllers/CallsController.php"));'`

## Docs
- DEPLOY.md/INSTALL.md rows: pretty-URL Apache 404 -> AllowOverride All; empty-body 500 -> config readability (fixed 1.0.0, `chmod 644 config/*.php`); autoload FAIL -> clean re-extract + case-insensitive walker; `--cdr-user/--cdr-pass` overrides.