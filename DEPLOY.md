# Deploying Smart-Report to Issabel

This runbook covers uploading the release to an Issabel 4 or 5 server and validating it after installation. The release has been verified locally end-to-end: every PHP file lints under **real PHP 5.4.45, 5.6.40 and 8.2**, the included `tests/phpcompat.php` harness reports `PASS`, and a full HTTP smoke test of every page and action returned green.

**What's new in 1.1.3:** three new report modules, AMI live monitoring, and version-reporting hardening —

- **Extension Report** (`/ext-report`): per-extension productivity over any date range — total/inbound/outbound/internal call counts, talk time, average talk, missed inbound, first/last call of the day, a talk-by-hour chart, per-extension call detail with recording playback, and CSV export. A call is attributed to every extension that appears on it (origin, destination, or a channel peer such as `SIP/105-…` / `Local/105@…`).
- **Employee Report** (`/employee-report`): work-time tracking via dial-in **clock codes 8810–8899**. Each employee gets a personal code (root assigns them under *Manage Employees*); dialing the code from any phone toggles a work session — the code call answers and hangs up. Sessions are derived from the CDR when the page loads (no dialplan database writes), so clock calls are excluded from all call reports automatically. The report shows per-employee sessions, total work time, calls, talk time, talk-per-hour, missed inbound and a live clocked-in/out status, with per-employee detail and CSV export.
- **Live Report** (`/live`): current calls and queue state from the **Asterisk Manager Interface** — active/talking/ringing/waiting counters, per-channel state and duration, queue member states and waiting callers, auto-refreshing every 5 s. Root can enable **listen / whisper / barge** (expert feature, off by default; maximum mode selectable): pressing Listen opens a muted private channel on the admin's own phone via AMI `ChanSpy` with `qS/wqS/WqS` options — the spied parties never hear the join tone. Every spy action is audit-logged. AMI credentials live in `config/external.php` (`external.ami`) and a full setup guide (manager.conf template) ships in **Settings → AMI**.
- **Version reporting hardened.** The panel version now always comes from the code constant `SMR_VERSION` — a stale `config/app.php` (the most likely cause of a deploy reporting 1.1.1 after installing 1.1.2) can no longer misreport it. The layout HTML carries an `<!--app-version x.y.z-->` marker for instant verification (View Source), assets are cache-busted with `?v=`, and the installer writes `data/installed_version` and warns when it differs from the tree being installed.

**What's new in 1.1.2:** customer-feedback batch from the first production deploy —

- **Graphical Reports render again.** A private-method visibility bug (`CdrModel::isMissedCall()` called from the reports controller) made `/reports/data` return HTTP 500, so every chart sat empty with the loading state dismissed. The method is now public; the data endpoint answers JSON again.
- **Internal-call classification fixed.** Trunk peers (e.g. `11577`, `21577`), full external numbers and Persian-digit CLIDs (e.g. `109۰۳۳۴۳۹۳۶۳`) no longer show up as "internal" calls: numbers are normalized to ASCII digits first (the old code silently stripped Persian digits, turning `109۰۳۳۴۳۹۳۶۳` into `109`), and "internal" now requires *both* sides to be known local extensions — anything else is inbound/outbound. The threshold is configurable via `internal_max_length` in `config/external.php` (default 3).
- **Honest recording buttons.** Play/Download now appear only when the recording file actually exists on disk (server-side check); everything else shows a muted "No recording" dash — on All Calls, Missed and Internal lists alike.
- **Call-legs expander is now opt-in.** The per-call legs table is a VoIP-expert diagnostic, so it is hidden by default; root can re-enable it under Settings → Module Options → Expert options (`ui.show_legs`).
- **Simplified lists.** The dashboard direction tabs are All / Incoming / Outgoing only (internal traffic has its own section, missed calls their own card); the All Calls filter no longer offers "Internal"; the Missed list no longer offers a Status dropdown (only unanswered outcomes exist there by definition).
- **Defaults & cosmetics.** Missed/Internal lists default to today (1 day) instead of 7 days; Internal Calls gets a proper building icon instead of a funnel; the direction doughnut only renders non-zero buckets.

**What's new in 1.1.1:** fixes and refinements to the call-center reporting UI —

- **Queue Report detail links fixed.** The "View Detail" link could lose its `queue=` parameter (an empty `queue=` from the shared filter string overrode the real value), producing a "Queue not specified" redirect. The explicit queue now always wins; the detail heading renders correctly in both languages.
- **Legs expander fixed on all tables.** The dot + count button now expands call legs on Calls, Missed and Internal lists. (Linkedids contain a dot, which broke the old CSS-selector-based toggle; the handler is now id-based and bound once, delegated.)
- **Recording playback opens in a modal on the same page** (Esc / backdrop / Close to dismiss, download button inside). Missed/Internal lists no longer navigate to a bare audio page. If the recording file is missing on disk, the modal shows a friendly "no recording" message instead of an empty player.
- **Graphical Reports hardening.** Root's per-chart enable/disable card is now functional (saves per-chart visibility with CSRF protection; changes apply without reload). The missed-calls trend computes from one range query instead of one query set per day — noticeably faster on busy servers — and now honours the queue filter. Date inputs no longer visibly jump on load: defaults (last 30 days) render server-side.
- **Queue Performance drill-down.** Clicking a queue's bar in the Graphical Reports queue chart opens that queue's detail view for the selected date range.
- **Packaging/compat.** PHP 5.4–8.x compatibility harness passes; `RecordingService::resolve()` no longer triggers a PHP 8.4 deprecation; duplicate Chart.js include removed from the queue detail page.

**What's new in 1.1.0:** a dashboard **direction filter** (all / incoming / outgoing / internal) that recomputes the stat cards and lists, a **Missed inbound calls** section (voicemail / no-answer / busy / cancelled / failed buckets), a **call-centric Calls page** — one row per call (by `linkedid`, entry leg shown, extra legs expandable inline, direction badges, inline player per recorded call with working seek), and **two CSV exports** (summary and full detail). Call direction and internal/trunk detection read the Issabel **Asterisk database** (users, devices, ring groups, queues, DIDs, trunks) automatically, with a config fallback (`external.routing`) when the reference DB is unreachable.

> **If you already extracted the earlier release** and got a blank page / no installer output (CLI) or an empty-body HTTP 500 (browser): the old build had autoloader path-casing bugs that only fail on Linux, and it wrote `config/*.php` as `0640 root` which the web server couldn't read. This release fixes both (a case-insensitive autoloader, `0644` config files, and a readable error page). Replace the folder instead of extracting over it — see "Replace a broken earlier deploy" below.

## Artifact

- `dist/smartreport-1.1.3.tar.gz` — the release archive.

The SHA256 checksum is provided alongside the artifact in `dist/smartreport-1.1.3.tar.gz.sha256`. Verify it after upload:

```bash
sha256sum -c /root/smartreport-1.1.3.tar.gz.sha256   # run from the directory containing the tarball
```

The archive extracts to a single `smartreport/` directory and contains **no** `config/database.php`, `config/external.php`, caches, logs, exports, or local agent notes (`agents.md`) — the installer generates the config files on the server.

### Rebuilding the artifact (from the repository)

The release tarball is built from `pkg/smartreport/` (the packaging source) by the one-command release script. From the repository root:

```bash
scripts/build.sh             # rebuild the current version
scripts/build.sh 1.1.3       # bump SMR_VERSION + config/app.php, then build
```

The script mirrors the repository into `pkg/smartreport`, produces `dist/smartreport-<version>.tar.gz` + `.sha256`, verifies the artifact (exclusions, extracted content, carried version) and runs the PHP compatibility harness. Builds are reproducible: identical sources yield a byte-identical tarball.

`agents.md` is developer/agent documentation and intentionally stays out of the artifact (it lives only in the repository checkout). The two `config/*.php` files are generated on the server and must never be packaged.

## Requirements on the server

- Issabel 4 or 5 (this module also runs on plain CentOS/EL + Apache).
- Apache with `mod_rewrite` and `AllowOverride All` for `/var/www/html`.
- PHP 5.4+ (tested 5.4–8.4) with `pdo`, `pdo_mysql`, `json`, `session`, `filter`, `openssl`.
- MariaDB with the existing `asteriskcdrdb` database and the call recording directory (`/var/spool/asterisk/monitor` by default).
- A MySQL admin account (default `root`) to create the module database/user.

## 1. Upload (WinSCP)

1. Connect to the server with WinSCP over SFTP/SCP as `root`.
2. Drag `dist/smartreport-1.1.3.tar.gz` to `/root/`.

## 2. Extract and set permissions (SSH)

```bash
cd /var/www/html
tar -xzf /root/smartreport-1.1.3.tar.gz
chown -R asterisk:asterisk /var/www/html/smartreport
chmod -R 775 /var/www/html/smartreport/data /var/www/html/smartreport/storage
```

If SELinux is enforcing:

```bash
chcon -R -t httpd_sys_content_t /var/www/html/smartreport
```

### Replace a broken earlier deploy (blank page / silent installer)

The first release published to this server shipped with class files in `app/core/`, but its autoloader looked for `app/Core/` (CamelCase). On Linux that mismatch made every page fail with `Class ... not found` and the installer exit without output. This release fixes the autoloader and adds `tests/phpcompat.php` to prove class resolution on any filesystem. To replace the broken copy:

```bash
cd /var/www/html
rm -rf /var/www/html/smartreport
tar -xzf /root/smartreport-1.1.3.tar.gz
chown -R asterisk:asterisk /var/www/html/smartreport
chmod -R 775 /var/www/html/smartreport/data /var/www/html/smartreport/storage   # created by the installer
```

Then run the installer (below) and continue with the validation checklist.

## 3. Run the installer

```bash
cd /var/www/html/smartreport
sudo php install/installer.php
```

The installer detects Issabel's `issabelpbx.conf` and Asterisk's `cdr_mysql.conf`, creates the `smartreport` database and a dedicated MySQL user, applies schema/seed data, registers the three features, writes `config/database.php` + `config/external.php`, and prints the `root`/`admin` passwords **once** — copy them.

Fully unattended example:

```bash
sudo php install/installer.php --non-interactive \
  --mysql-user=root --mysql-pass='YOUR-ROOT-DB-PASS' \
  --cdr-user='root' --cdr-pass='YOUR-CDR-DB-PASS' \
  --chown=asterisk \
  --root-pass='Str0ngRoot!' --admin-pass='Str0ngAdmin!'
```

`--cdr-user`/`--cdr-pass` override the automatically-detected CDR credentials — use any MySQL account with read access to `asteriskcdrdb`; if the run shows an empty CDR password on your server, pass these explicitly. `--chown=asterisk` makes the generated config files readable and correctly owned for the web server user. `--ast-user`/`--ast-pass` do the same for the Asterisk DB probe.

The installer reports the web panel mode: the panel runs on **plain PHP URLs** by default (`/smartreport/index.php?route=/login` and so on), which work on any Apache/nginx setup **without mod_rewrite or AllowOverride**. Pretty URLs are an optional opt-in (see below).

Then restart Apache:

```bash
systemctl restart httpd   # or: service httpd restart
```

## 4. Open the panel

```
http://YOUR-SERVER/smartreport/index.php
```

(Anonymous visitors are redirected to `...?route=/login`.) Log in with the `root` credentials printed by the installer.

***Plain PHP URLs are the supported mode** — they do not depend on `AllowOverride`, `.htaccess` or `mod_rewrite`, so the panel cannot hit an Apache 404.

Optional — clean pretty URLs (`/smartreport/login`, `/smartreport/calls`):

1. Set `"pretty_urls" => true` in `config/app.php`.
2. Enable rewriting for the module directory. On Issabel/EL the bundled helper does this (writes `/etc/httpd/conf.d/smartreport-rewrite.conf`, `AllowOverride All` for this directory only, reloads Apache):

```bash
cd /var/www/html/smartreport
sudo php install/fix-apache.php
```

The router accepts both URL forms on input, so switching back is just editing the config value.

## 5. Post-install validation checklist

- [ ] **Settings** loads and its status shows **Module DB OK** and **CDR DB OK**.
- [ ] **Dashboard** shows today's totals and a populated *Recent calls* table.
- [ ] **All Calls** lists records; date/`src`/`dst`/`clid`/disposition filters work; pagination works (change rows-per-page).
- [ ] **All Calls → Direction** filter narrows to incoming / outgoing / internal / missed and back to all.
- [ ] A call with extra legs (ring group, queue, transfer) shows one row; its **expand** button reveals the leg sub-table.
- [ ] A recorded call's inline **player** streams audio and seek/scrub works (server replies `206 Partial Content`).
- [ ] **CSV export (summary)** and **CSV export (full)** both download for the current filter.
- [ ] **Dashboard** direction tabs recompute the cards, *Recent calls* and the *Missed inbound* list; missed calls show a bucket label (voicemail / no-answer / busy / cancelled / failed).
- [ ] **Graphical Reports** (`/reports`) loads 6 charts (direction, hour, missed trend, talk time, queue perf, agent perf); **Download PNG** and **Download PDF** buttons work; date inputs show the last 30 days immediately (no visible jump on load).
- [ ] Root only: the **chart visibility** card appears under the charts; untick a chart, save, and it disappears (applies without reload); as `admin`/`viewer` the card is hidden and saving is rejected server-side.
- [ ] Clicking a bar in the **Queue Performance** chart opens that queue's detail view for the selected date range.
- [ ] **Queue Report** (`/queue-report`) shows the queue summary table; **View Detail opens the correct queue's detail page from every row, including filtered lists** (no "Queue not specified" redirect); CSV export works.
- [ ] On Calls / Missed / Internal lists, the **legs button (dot + number) expands and collapses** the call-legs sub-table.
- [ ] **Recording playback opens a modal on the same page** (Missed/Internal play button); a call without a recording file on disk shows a "no recording" message instead of an empty player; the download icon still fetches the file.
- [ ] **Settings → Users / Modules / Profile** load; change the root password and confirm re-login.
- [ ] Language switch (top bar) toggles to Persian and the layout becomes right-to-left.
- [ ] Role check: a user with role `viewer` can open dashboard/calls/reports/queue-report/export but is **denied** `/settings` (403).

If a recording does not play, confirm the path exists and is readable by the web user:

```bash
sudo -u asterisk ls -la /var/spool/asterisk/monitor/$(date +%Y)/$(date +%m)/$(date +%d)/ | head
```

## Upgrading an existing installation

```bash
mysqldump smartreport > /root/smartreport-backup-$(date +%F).sql
# extract the new archive over the existing folder, keeping config/database.php and config/external.php
cd /var/www/html
tar -xzf /root/smartreport-1.1.3.tar.gz
cd smartreport
sudo php install/installer.php --non-interactive --mysql-user=root --mysql-pass='YOUR-ROOT-DB-PASS'
```

The installer is idempotent: schema uses `IF NOT EXISTS`, seeds use `ON DUPLICATE KEY`, features are upserted, and existing users/passwords are kept.

## Uninstalling

```bash
cd /var/www/html/smartreport
sudo php install/uninstaller.php --yes --mysql-user=root --mysql-pass='YOUR-ROOT-DB-PASS'
```

This drops the module database and MySQL user, removes the generated config files, and clears cache/log/export files. Then delete the folder:

```bash
rm -rf /var/www/html/smartreport
```

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| "Not installed" page | `config/database.php` is missing — run the installer. |
| Blank page / installer produces no output | The earlier beta release had an autoloader casing bug (`app/Core` vs `app/core`) that fails on Linux; replace the folder with this release (`rm -rf` + re-extract). |
| HTTP 500 (empty body) | A config file unreadable by the web server user (older releases wrote `config/*.php` as `0640 root`). Fix: `chmod 644 config/database.php config/external.php` + `chown -R asterisk:asterisk /var/www/html/smartreport` + `systemctl restart php-fpm httpd`. This release writes `0644` and returns a readable error page instead of a blank 500. |
| CDR DB shows **FAIL - Access denied for 'asteriskuser'... (using password: NO)** | The detected CDR password was empty. Re-run with `--cdr-user=<user> --cdr-pass='...'` (any MySQL account with read on `asteriskcdrdb`), or make `/etc/asterisk/cdr_mysql.conf` readable by the installer so its `dbpass` is picked up. |
| CDR DB shows FAIL (other) | Verify `asteriskcdrdb` credentials in `config/external.php` (re-run the installer to refresh). |
| `Issabel config found: no` / CDR auto-detect wrong | `/etc/issabelpbx.conf` or the cdr conf is absent or unreadable. Pass `--cdr-user/--cdr-pass` (and `--ast-user/--ast-pass`) explicitly. |
| `php tests/phpcompat.php` reports autoload FAIL | Stale/mixed-case files left in the folder. Fix: `rm -rf smartreport` and re-extract clean. This release's autoloader resolves any directory casing, but a clean tree is always recommended. |
| Recordings not found | Check `monitor_dir` in `config/external.php` and that the web user can read the files. |
| Player cannot seek to a position | Expected on very old builds; 1.1.0+ streams with `Range` support (`206`), which browser sliders require. |
| Call direction shows only "Unknown" | The panel could not reach the Asterisk DB for reference data (users/trunks/DIDs). Verify the `asterisk` credentials in `config/external.php` (re-run the installer, which also grants the module's MySQL user `SELECT` on the reference DB); otherwise populate the `external.routing` fallback lists. Direction detection only applies to CDRs that contain a `linkedid` column. |
| Pretty URLs 404 | Expected unless pretty URLs are enabled — the panel default is plain PHP URLs (`/smartreport/index.php?route=...`), which never 404. For clean URLs see "Optional — clean pretty URLs" above. |
| Permission denied writing | `chown -R asterisk:asterisk` the module folder (web user must own `data/` and `storage/`). |
| Language switch resets | Language is stored per session; clear a stale `smr_lang` cookie. |

## Notes

- **PHP 5.4 compatibility is verified with a real PHP 5.4.45 binary** (`php -l` on every file passes; `php tests/phpcompat.php` → `PASS`). Issabel 4 ships PHP 5.4 and Issabel 5 ships PHP 7.x; this release runs unchanged on both.
- `php tests/phpcompat.php` is a regression harness shipped in the archive: it autoloads every declared class (catching path/casing problems on case-sensitive filesystems) and scans for any PHP 5.5+/5.6+/7.x/8.x-only constructs, exiting non-zero if found.
- **Chart.js and jsPDF are bundled locally** in `assets/js/vendor/` — no CDN required, works offline.
- The module never stores CDR or recordings; it reads `asteriskcdrdb` and the monitor directory read-only.
- `config/database.php` and `config/external.php` are ignored by git and excluded from the artifact; treat them as secrets.
