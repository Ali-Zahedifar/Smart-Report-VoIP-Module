#!/usr/bin/env bash
#
# Smart-Report release builder.
#
# One-command release: optionally bumps the version, mirrors the repository
# into the packaging source (pkg/smartreport), and produces the release
# tarball + SHA256 checksum in dist/.
#
# Usage:
#   scripts/build.sh              # rebuild current version (no bump)
#   scripts/build.sh 1.1.2        # bump to 1.1.2, then build
#   scripts/build.sh --no-bump    # same as no argument (explicit)
#
# The artifact never contains: agents.md (local agent/dev notes),
# config/database.php + config/external.php (generated on the server),
# data/cache + data/logs content, storage/* runtime files.
#
# Builds are reproducible: same sources produce a byte-identical tarball
# (gzip -n strips the timestamp), so re-running the script after a no-op
# change does not churn the release checksum.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PKG_DIR="$ROOT/pkg"
PKG="$PKG_DIR/smartreport"
DIST="$ROOT/dist"
VERSION_REGEX='^[0-9]+\.[0-9]+\.[0-9]+$'

say() { printf '==> %s\n' "$*"; }
die() { printf 'build.sh: %s\n' "$*" >&2; exit 1; }

current_version() {
    sed -n "s/^define('SMR_VERSION', '\([^']*\)');.*/\1/p" "$ROOT/app/core/bootstrap.php"
}

# --- Version handling -------------------------------------------------------

BUMP_VERSION=""
case "${1:-}" in
    "")            ;;
    --no-bump|-n)  ;;
    -h|--help)     sed -n '2,16p' "$0"; exit 0 ;;
    *)             BUMP_VERSION="$1" ;;
esac

if [ -n "$BUMP_VERSION" ]; then
    echo "$BUMP_VERSION" | grep -Eq "$VERSION_REGEX" \
        || die "invalid version '$BUMP_VERSION' (expected X.Y.Z)"
fi

VERSION="$(current_version)"
[ -n "$VERSION" ] || die "could not read SMR_VERSION from app/core/bootstrap.php"
echo "$VERSION" | grep -Eq "$VERSION_REGEX" || die "SMR_VERSION '$VERSION' is not X.Y.Z"

if [ -n "$BUMP_VERSION" ] && [ "$BUMP_VERSION" != "$VERSION" ]; then
    say "Bumping version $VERSION -> $BUMP_VERSION"
    sed -i "s/^define('SMR_VERSION', '[^']*');/define('SMR_VERSION', '$BUMP_VERSION');/" \
        "$ROOT/app/core/bootstrap.php"
    sed -i "s/^    'version' => '[^']*',/    'version' => '$BUMP_VERSION',/" \
        "$ROOT/config/app.php"
    VERSION="$BUMP_VERSION"
else
    say "Building version $VERSION (no bump)"
fi

ARTIFACT="smartreport-$VERSION.tar.gz"

# --- Mirror repository -> pkg/smartreport -----------------------------------

say "Mirroring repository into pkg/smartreport"
rm -rf "$PKG"
mkdir -p "$PKG"

# tar pipe (rsync is not available in Git Bash). Dotfiles ship (.htaccess,
# .gitignore); repo-only and runtime paths are excluded here.
tar -cf - \
    --exclude='./.git' \
    --exclude='./.opencode' \
    --exclude='./.freebuff' \
    --exclude='./agents.md' \
    --exclude='./config/database.php' \
    --exclude='./config/external.php' \
    --exclude='./data/cache' \
    --exclude='./data/logs' \
    --exclude='./dist' \
    --exclude='./pkg' \
    --exclude='./scripts' \
    -C "$ROOT" . | tar -xf - -C "$PKG"

# Belt and suspenders: strip anything runtime/volatile that slipped through.
rm -f "$PKG/config/database.php" "$PKG/config/external.php"
rm -rf "$PKG/data/cache" "$PKG/data/logs"
rm -f "$PKG"/data/*.lock
find "$PKG/storage" -type f ! -name '.gitkeep' -delete

# --- Build tarball + checksum ------------------------------------------------

say "Building dist/$ARTIFACT"
mkdir -p "$DIST"
# -C pkg + relative path gives entries like "smartreport/app/...".
# Normalized mtimes/owner + name sort + gzip -n (no timestamp) make the
# artifact byte-identical for identical sources, regardless of build time.
(cd "$PKG_DIR" && tar --sort=name --owner=0 --group=0 --numeric-owner \
    --mtime='2026-01-01 00:00:00 UTC' -cf - smartreport) | gzip -n > "$DIST/$ARTIFACT"

CHECKSUM="$(sha256sum "$DIST/$ARTIFACT" | sed "s| .*$||")"
printf '%s  %s\n' "$CHECKSUM" "$ARTIFACT" > "$DIST/$ARTIFACT.sha256"

# --- Verify ------------------------------------------------------------------

say "Verifying artifact"
if tar -tzf "$DIST/$ARTIFACT" | grep -Eq '(^|/)agents\.md$|(^|/)config/(database|external)\.php$'; then
    die "forbidden file found in artifact"
fi

VERIFY_DIR="$(mktemp -d)"
trap 'rm -rf "$VERIFY_DIR"' EXIT
mkdir -p "$VERIFY_DIR/out"
tar -xzf "$DIST/$ARTIFACT" -C "$VERIFY_DIR/out"
diff -rq "$VERIFY_DIR/out/smartreport" "$PKG" > /dev/null \
    || die "extracted artifact differs from pkg/smartreport"
grep -q "define('SMR_VERSION', '$VERSION');" "$VERIFY_DIR/out/smartreport/app/core/bootstrap.php" \
    || die "artifact does not carry version $VERSION"

if command -v php > /dev/null 2>&1; then
    say "PHP compatibility harness"
    (cd "$ROOT" && php tests/phpcompat.php > /dev/null) \
        || die "tests/phpcompat.php failed"
fi

ENTRY_COUNT="$(tar -tzf "$DIST/$ARTIFACT" | grep -c . || true)"
say "Done: dist/$ARTIFACT ($ENTRY_COUNT entries)"
printf '    sha256: %s\n' "$CHECKSUM"
