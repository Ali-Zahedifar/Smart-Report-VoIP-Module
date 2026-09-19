# Uninstalling Smart-Report

> Back up anything you need first: `mysqldump -u smartreport -p smartreport > smartreport-backup.sql` and copy the module folder if you want to keep recordings/reports.

## Automated removal

```bash
cd /var/www/html/smartreport
sudo php install/uninstaller.php --yes
```

The uninstaller, using credentials in `config/database.php`:

1. Drops the `smartreport` database (all module data — settings, users, audit log, saved reports).
2. Removes `config/database.php` and `config/external.php`.
3. Empties cache, logs, and queued exports.
4. Prints SQL to run as a MySQL admin if the MySQL user could not be removed:

```sql
DROP USER IF EXISTS 'smartreport'@'localhost';
```

Without `--yes` it prompts for confirmation first.

## Manual cleanup

- **Issabel menu hook** — if you added a menu entry / `issabel-menumerge`, remove it.
- **Cron entries** — remove any scheduled-report cron lines added by a future release.
- **Module folder** — `rm -rf /var/www/html/smartreport`

## What remains untouched

- Your Asterisk CDR data (`asteriskcdrdb`) — Smart-Report never modifies it.
- Call recording files under `/var/spool/asterisk/monitor` — untouched.
- Asterisk configuration — Smart-Report does not modify dialplan or manager settings.

Re-installing afterwards is safe: `php install/installer.php` recreates everything.