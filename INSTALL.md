# Installing Smart-Report

Smart-Report is installed on the **Issabel server itself** (it connects to the local MariaDB and reads recordings from the local filesystem).

## 1. Requirements check

- Issabel 4 or 5, Apache with `mod_rewrite`, PHP 7.4+.
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

1. Detects Issabel config (`/etc/issabelpbx.conf`) and CDR config (`/etc/asterisk/cdr_mysql.conf`) and pre-fills external DB values.
2. Asks for the MySQL account used to install (default `root`; you can pass `--mysql-user`, `--mysql-pass`).
3. Creates the `smartreport` database and a dedicated `smartreport` MySQL user with random password.
4. Applies `schema.sql` + `seed.sql`, registers the built-in features, and creates the `root` and `admin` users.
5. Writes `config/database.php` and `config/external.php`.
6. Runs a final connectivity check and prints the credentials once.

Non-interactive example (passwords provided or generated and printed):

```bash
php install/installer.php \
  --mysql-user=root --mysql-pass='secret' \
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
| `--cdr-host/name/user/pass` | from `cdr_mysql.conf` | astleriskcdrdb read-only connection |
| `--ast-host/name/user/pass` | from `issabelpbx.conf` | asterisk config DB read-only connection |
| `--monitor-dir` | /var/spool/asterisk/monitor | Recording directory |
| `--ami-host/port/user/pass` | 127.0.0.1 / 5038 / admin | AMI (unused in this release) |
| `--chown` | empty | e.g. `asterisk` to chown data dirs |
| `--non-interactive` | off | No prompts (uses defaults + flags) |

## 4. Access the panel

Open (use your server address):

```
http://YOUR-SERVER/smartreport/
```

Log in with the printed `root` (or `admin`) credentials, then:

1. Go to **Settings → Profile** and change the password(s).
2. Check **Settings** → the system page shows Module DB / CDR DB / recording directory status.
3. Open **All Calls**, apply a date filter, and click **Play** / download icon next to recorded calls.

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
location ~ ^/smartreport/(config|data|storage|app|cron|install)(/|$) { deny all; }
location /smartreport/ {
    try_files $uri $uri/ /smartreport/index.php?$query_string;
}
```

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| "not installed" page | Run `php install/installer.php` (missing `config/database.php`). |
| 500 after install | Check `data/logs/app.log`. Likely a DB connectivity or extension issue. |
| CDR DB shows FAIL | Confirm `asteriskcdrdb` exists and the credentials in `config/external.php` work. |
| Recordings not found | Check `monitor_dir` and that the web user can read the files (`sudo -u asterisk ls ...`). |
| Language switch resets | The language is stored per-session; clear cookies if a stale `smr_lang` is set. |

## Upgrade

Back up first (`mysqldump smartreport`). Re-run `php install/installer.php --non-interactive --mysql-user=root --mysql-pass='...'` after copying over the new files. It is idempotent: schema uses `IF NOT EXISTS`, seeds use `ON DUPLICATE KEY`, features are upserted, and existing users are kept.