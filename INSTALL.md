# Installing Smart-Report

Smart-Report is installed on the **Issabel server itself** (it connects to the local MariaDB and reads recordings from the local filesystem).

The dashboard offers a **direction filter** (all / incoming / outgoing / internal / missed) and a **Missed inbound calls** section (comprehensive: queues, ring groups, direct DID, IVR→ext, overflow); the Calls page is call-centric (one row per call, expandable legs, inline recording player with seek) and exports summary or full CSV. The module reads the Issabel **Asterisk database** to classify directions and recognize extensions/queues/ring groups/DIDs/trunks; when that reference DB is unreachable it falls back to `config/external.php → routing` lists.

New in 1.1.0: **Graphical Reports** (6 chart types, PNG/PDF export) and **Queue Report** (per-queue + agent metrics, detail view, CSV export). New in 1.1.1: queue-detail link, call-legs expander, and in-page modal recording playback fixes; faster graphical reports. New in 1.1.2: graphical-reports data endpoint repaired, internal-call classification fixed, recording buttons only shown for recordings that exist, legs expander opt-in. New in 1.1.3: **Extension Report** and **Employee Report** (dial-in clock codes 8810–8899), **Live Report** over AMI (setup guide in Settings → AMI), version reporting hardened (always from code, `<!--app-version-->` marker in HTML).

## 1. Requirements check

- Issabel 4 or 5, Apache with `mod_rewrite`, PHP 5.4+ (verified with real PHP 5.4.45 / 5.6.40 / 8.2 lint + `tests/phpcompat.php`).
- PHP extensions: `pdo`, `pdo_mysql`, `json`, `session`, `filter`, `openssl`.
- The `asteriskcdrdb` database must exist and be populated (standard on Issabel).
- Call recording directory (default `/var/spool/asterisk/monitor`) must exist and be readable by the web server.

## 2. Copy the module

```bash
cd /var/www/html
cp -r /path/to/smartreport ./smartreport
chown -R asterisk:asterisk smartreport   # so the web server can write cache/logs/exports
```

## 3. Run the installer

```bash
cd /var/www/html/smartreport
sudo php install/installer.php
```

During the run the installer:

1. Detects Issabel config (`/etc/issabelpbx.conf`) and CDR config (`/etc/asterisk/cdr_mysql.conf`) and pre-fills external DB values (it prints the resolved CDR/Asterisk DB user and whether a password was set).
2. Asks for the MySQL account used to install (default `root`; you can pass `--mysql-user`, `--mysql-pass`).
3. Creates the `smartreport` database and a dedicated `smartreport` MySQL user with random password.
4. Applies `schema.sql` + `seed.sql`, registers the built-in features, and creates the `root` and `admin` users.
5. Writes `config/database.php` and `config/external.php` (the latter includes a `routing` block used as reference-data fallback).
6. Grants the module's MySQL user read access to the Asterisk reference DB (best-effort) and runs a final connectivity check, then prints the credentials once.

Non-interactive example (passwords provided or generated and printed):

```bash
php install/installer.php \
  --mysql-user=root --mysql-pass='secret' \
  --cdr-user='root' --cdr-pass='secret' \
  --chown=asterisk \
  --db-name=smartreport --db-user=smartreport \
  --root-user=root --admin-user=admin \
  --root-pass='Str0ngRoot!' --admin-pass='Str0ngAdmin!' \
  --non-interactive
```

If the module DB password should be pre-defined: `--db-pass='...'`.

### Options

| Option | Default | Purpose |
| --- | --- | --- |
| `--mysql-host` / `--mysql-port` / `--mysql-user` / `--mysql-pass` | localhost / 3306 / root / (prompt) | MySQL account used to create DB + user |
| `--db-name` / `--db-user` / `--db-pass` | smartreport / smartreport / random | Module database & dedicated user |
| `--root-user` / `--admin-user` | root / admin | Usernames of the two seeded accounts |
| `--root-pass` / `--admin-pass` | random | Their passwords |
| `--cdr-host/name/user/pass` | from `cdr_mysql.conf` | Read-only connection to `asteriskcdrdb`; pass explicitly (e.g. `--cdr-user=root --cdr-pass=...`) when auto-detect or its password is wrong |
| `--ast-host/name/user/pass` | from `issabelpbx.conf` | asterisk config DB read-only connection |
| `--monitor-dir` | /var/spool/asterisk/monitor | Recording directory |
| `--ami-host/port/user/pass` | 127.0.0.1 / 5038 / admin | AMI (unused in this release) |
| `--chown` | empty | e.g. `asterisk` to chown data dirs and the generated config files |
| `--non-interactive` | off | No prompts (uses defaults + flags) |

## 4. Access the panel

Open (use your server address):

```
http://YOUR-SERVER/smartreport/index.php
```

Anonymous visitors are redirected to `...?route=/login`. Log in with the printed `root` (or `admin`) credentials, then:

1. Go to **Settings → Profile** and change the password(s).
2. Check **Settings** → the system page shows Module DB / CDR DB / recording directory status.
3. Open **All Calls**, apply a date filter, and click **Play** / download icon next to recorded calls.

### Web routing (plain PHP URLs)

The panel uses the **plain PHP entry URL by default** — every link is `/smartreport/index.php?route=/login`, `?route=/calls`, etc. This works on any web server **without mod_rewrite, `.htaccess` or `AllowOverride`**, so the panel can never produce an Apache 404 for its own pages. Assets, forms, pagination, audio and CSV export all work in this mode.

Optional: pretty URLs (`/smartreport/login`). Set `"pretty_urls" => true` in `config/app.php`, then enable rewriting for the module directory. On Issabel/EL, run once as root:

```bash
cd /var/www/html/smartreport
sudo php install/fix-apache.php
```

This writes `/etc/httpd/conf.d/smartreport-rewrite.conf` (`AllowOverride All` for the module directory only) and reloads Apache. The router accepts both URL forms on input.

## 5. Verify call recordings

Recordings are resolved from the `recordingfile` CDR column first, then by the recording directory date layout (`YYYY/MM/DD/<uniqueid>.<ext>`). If the file is not found, the player returns "Recording file not found" instead of leaking the path. To test the directory:

```bash
ls -la /var/spool/asterisk/monitor/$(date +%Y)/$(date +%m)/$(date +%d)/ | head
```

If recordings live elsewhere, edit `config/external.php` → `monitor_dir` (or re-run the installer).

## 6. Optional: link from the Issabel menu

Create a small native Issabel module or simply add a shortcut. The simplest approach is a one-page PHP shim under `/var/www/html/modules/` won't be needed — you can add a link to the Issabel top bar or a bookmark. For deeper integration, add a menu entry via Issabel's menu management or `issabel-menumerge` pointing to `/smartreport/`.

## 7. Optional: nginx

If Smart-Report is served by nginx, add inside the `smartreport` location:

```nginx
location ~ ^/smartreport/(config|data|storage|app|cron|install|tests)(/|$) { deny all; }
location /smartreport/ {
    try_files $uri $uri/ /smartreport/index.php?$query_string;
}
```

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| "not installed" page | Run `php install/installer.php` (missing `config/database.php`). |
| 500 (empty body) after install | Config files unreadable by the web user (older installs wrote `0640 root`). Fix: `chmod 644 config/database.php config/external.php` + `chown -R asterisk:asterisk .` + restart `php-fpm`/`httpd`. This release writes `0644` and shows a readable error page instead of a blank 500. |
| CDR DB shows FAIL (Access denied for 'asteriskuser'...) | The detected CDR password was empty — re-run with `--cdr-user=<user> --cdr-pass='...'` (any MySQL account with read on `asteriskcdrdb`). |
| `php tests/phpcompat.php` autoload FAIL | Old/mixed-case files left from a previous extract; `rm -rf` and re-extract clean. The autoloader now resolves any directory casing. |
| CDR DB shows FAIL | Confirm `asteriskcdrdb` exists and the credentials in `config/external.php` work. |
| Recordings not found | Check `monitor_dir` and that the web user can read the files (`sudo -u asterisk ls ...`). |
| Pretty URLs 404 | Expected — the default is plain PHP URLs (`/smartreport/index.php?route=...`), which never 404. For clean URLs set `pretty_urls` in `config/app.php` + run `sudo php install/fix-apache.php`. |
| Language switch resets | The language is stored per-session; clear cookies if a stale `smr_lang` is set. |

## Upgrade

Back up first (`mysqldump smartreport`). Re-run `php install/installer.php --non-interactive --mysql-user=root --mysql-pass='...'` after copying over the new files. It is idempotent: schema uses `IF NOT EXISTS`, seeds use `ON DUPLICATE KEY`, features are upserted, and existing users are kept.