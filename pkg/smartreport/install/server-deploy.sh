#!/bin/sh
# =============================================================================
# Smart-Report server deploy — one command, self-verifying.
#
#   sh install/server-deploy.sh /root/smartreport-1.1.5.tar.gz \
#        --mysql-pass='...' --cdr-pass='...' --root-pass='...' --admin-pass='...'
#
# What it does:
#   0. verifies the tarball checksum when a .sha256 sits next to it
#   1. discovers the EXISTING install and how the web server reaches it
#   2. stages the tarball in a temp dir and reads its version
#   3. preserves config/*.php, data/ and storage/ from the old install
#   4. swaps atomically (old kept at smartreport.old), restores preserved files
#   5. fixes ownership/permissions, runs install/installer.php, restarts httpd
#   6. VERIFIES what the web server actually SERVES (status.php) against what
#      landed on disk — and on mismatch prints a diagnosis instead of haunting.
#
# Extra installer args are passed through verbatim. Web-root default
# /var/www/html/smartreport; override with --webroot=/some/path.
# =============================================================================
set -u

err()  { printf '\033[31m%s\033[0m\n' "$*" >&2; }
ok()   { printf '\033[32m%s\033[0m\n' "$*"; }
step() { printf '\n==> %s\n' "$*"; }

TARBALL=""
WEBROOT="/var/www/html/smartreport"
INSTALLER_ARGS=""
SELINUX=0

for arg in "$@"; do
    case "$arg" in
        --webroot=*) WEBROOT="${arg#--webroot=}" ;;
        --selinux)   SELINUX=1 ;;
        -*)          INSTALLER_ARGS="$INSTALLER_ARGS '$arg'" ;;
        *)           TARBALL="$arg" ;;
    esac
done

if [ -z "$TARBALL" ] || [ ! -f "$TARBALL" ]; then
    err "Usage: sh install/server-deploy.sh /path/to/smartreport-<ver>.tar.gz [--webroot=/var/www/html/smartreport] --mysql-pass=... --cdr-pass=... --root-pass=... --admin-pass=..."
    exit 1
fi

BASEDIR=$(dirname "$WEBROOT")
DIRNAME=$(basename "$WEBROOT")

step "0. Tarball checksum"
if [ -f "$TARBALL.sha256" ]; then
    if (cd "$(dirname "$TARBALL")" && sha256sum -c "$(basename "$TARBALL.sha256")" >/dev/null 2>&1); then
        ok "  checksum OK"
    else
        err "  checksum MISMATCH for $TARBALL — stopping (wrong/stale upload)."
        exit 1
    fi
else
    echo "  (no .sha256 next to the tarball — skipping)"
fi

step "1. Current state of the serving path"
BEFORE_SERVED=$(curl -skL --max-time 5 "http://localhost${WEBROOT##/var/www/html}/status.php" 2>/dev/null | grep '^code_version' || true)
[ -n "$BEFORE_SERVED" ] && echo "  served before: $BEFORE_SERVED" || echo "  nothing answerable at the URL before deploy"
if [ -d "$WEBROOT" ]; then
    echo "  disk tree: $WEBROOT ($(grep -o "SMR_VERSION', *'[^']*'" "$WEBROOT/app/core/bootstrap.php" 2>/dev/null | head -1))"
fi
grep -Rns "smartreport" /etc/httpd/conf /etc/httpd/conf.d 2>/dev/null | grep -Ei "alias|documentroot|include" | head -5 || true

step "2. Staging the tarball"
STAGE=$(mktemp -d)
tar -xzf "$TARBALL" -C "$STAGE" || { err "  tar extract failed"; exit 1; }
SRC="$STAGE/smartreport"
[ -d "$SRC" ] || { err "  tarball does not contain a smartreport/ directory"; exit 1; }
NEWVER=$(sed -n "s/.*SMR_VERSION', *'\([^']*\)'.*/\1/p" "$SRC/app/core/bootstrap.php" | head -1)
echo "  staged $SRC (version $NEWVER)"

step "3. Preserving config + data from the old install"
PRESERVE=$(mktemp -d)
if [ -d "$WEBROOT" ]; then
    for f in config/database.php config/external.php; do
        [ -f "$WEBROOT/$f" ] && mkdir -p "$PRESERVE/$(dirname $f)" && cp -a "$WEBROOT/$f" "$PRESERVE/$f" && echo "  kept $f"
    done
    [ -d "$WEBROOT/data" ]    && cp -a "$WEBROOT/data"    "$PRESERVE/data"    && echo "  kept data/"
    [ -d "$WEBROOT/storage" ] && cp -a "$WEBROOT/storage" "$PRESERVE/storage" && echo "  kept storage/"
fi

step "4. Atomic swap"
[ -d "$WEBROOT" ] && rm -rf "$WEBROOT.old" && mv "$WEBROOT" "$WEBROOT.old"
mv "$SRC" "$WEBROOT"
# preserved items replace the fresh ones only when they existed before
for f in config/database.php config/external.php; do
    [ -f "$PRESERVE/$f" ] && cp -a "$PRESERVE/$f" "$WEBROOT/$f"
done
[ -d "$PRESERVE/data" ]    && rm -rf "$WEBROOT/data"    && cp -a "$PRESERVE/data"    "$WEBROOT/data"
[ -d "$PRESERVE/storage" ] && rm -rf "$WEBROOT/storage" && cp -a "$PRESERVE/storage" "$WEBROOT/storage"
chown -R asterisk:asterisk "$WEBROOT" 2>/dev/null || true
chmod -R 775 "$WEBROOT/data" "$WEBROOT/storage" 2>/dev/null || true
if [ "$SELINUX" = "1" ]; then chcon -R -t httpd_sys_content_t "$WEBROOT" 2>/dev/null || true; fi
ok "  swapped; previous tree kept at $WEBROOT.old"

step "5. Installer + web server restart"
cd "$WEBROOT" || exit 1
eval php install/installer.php --non-interactive $INSTALLER_ARGS || { err "  installer failed — old tree intact at $WEBROOT.old"; exit 1; }
systemctl restart httpd 2>/dev/null || service httpd restart
# php-fpm's opcache survives an httpd restart (Rocky/Issabel run PHP via fpm):
# without restarting the fpm unit the OLD bytecode keeps serving forever.
FPM_UNITS=$(systemctl list-units --type=service --all 2>/dev/null | grep -Eio '[a-z0-9-]*php[a-z0-9-]*-fpm\.service' | sort -u)
[ -z "$FPM_UNITS" ] && FPM_UNITS="php-fpm"
for U in $FPM_UNITS; do
    systemctl restart "$U" 2>/dev/null && ok "  restarted $U (opcache cleared)" || echo "  (could not restart $U — if the panel shows an old version, restart it manually)"
done

step "6. Verify what the web server actually SERVES"
SLEPT=0
until curl -skL --max-time 5 "http://localhost/$DIRNAME/status.php" | grep -q code_version; do
    SLEPT=$((SLEPT+2)); [ $SLEPT -ge 10 ] && break; sleep 2
done
SERVED=$(curl -skL --max-time 5 "http://localhost/$DIRNAME/status.php" | grep '^code_version' | awk '{print $2}')
DISKVER="$NEWVER"
if [ "$SERVED" = "$DISKVER" ]; then
    ok "  SUCCESS: server serves version $SERVED from $(curl -skL "http://localhost/$DIRNAME/status.php" | grep '^served_from' | awk '{print $2}')"
    ok "  Done. Log in and confirm the footer says $SERVED."
else
    err "  MISMATCH: disk tree is $DISKVER but the URL serves '${SERVED:-nothing}'."
    err "  The web server is reading a DIFFERENT tree than $WEBROOT."
    echo
    echo "  What this URL really loads (headers included — Location: names the target):"
    PROBE=$(curl -siLk --max-time 5 "http://localhost/$DIRNAME/status.php")
    if [ -n "$PROBE" ]; then
        printf '%s\n' "$PROBE" | sed -n '1,12p'
    else
        echo "  (nothing answers at that URL — no smartreport tree is served there at all)"
    fi
    echo
    echo "  Candidates on disk (grep these for the served version):"
    ls -d "$BASEDIR"/*smart* "$BASEDIR"/*report* 2>/dev/null || true
    echo
    echo "  Apache mapping hints (unfiltered):"
    grep -Rnsi "smartreport\\|mana_reports" /etc/httpd 2>/dev/null | grep -Ei "alias|documentroot|rewrite|redirect|fallback" | head -10
    httpd -S 2>/dev/null | sed -n '1,12p' || true
    echo
    echo "  Fix the mapping (or replace the directory it points at), then re-run this script."
    exit 2
fi
