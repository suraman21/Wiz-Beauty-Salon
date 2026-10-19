#!/usr/bin/env bash
#
# WIZ server-side deploy.
#
# Pulls the `deploy` branch published by GitHub Actions and syncs it into place:
#
#   <repo>/site/*   ->  /home/arkeonet/wiz.arkeonethiopia.com/   (React build, public)
#   <repo>/admin/*  ->  /home/arkeonet/wiz-app/                  (PHP app, outside webroot)
#
# The PHP app is never placed inside the webroot. Only the front-controller shim
# and the rewrite rules are copied into the docroot.
#
# Safe to run repeatedly. Installed as a cron job:
#   * * * * * /home/arkeonet/wiz-deploy/deploy.sh >> /home/arkeonet/logs/wiz-deploy.log 2>&1
#
set -euo pipefail

REPO_DIR="/home/arkeonet/wiz-deploy"
DOCROOT="/home/arkeonet/wiz.arkeonethiopia.com"
APPDIR="/home/arkeonet/wiz-app"
BRANCH="deploy"
LOCK="/tmp/wiz-deploy.lock"

log() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }
fail() { log "ERROR: $*"; exit 1; }

# ---------------------------------------------------------------- single run
exec 9>"$LOCK"
if ! flock -n 9; then
    log "another deploy is running; exiting"
    exit 0
fi

cd "$REPO_DIR" || fail "repo dir missing: $REPO_DIR"

# ---------------------------------------------------------------- fetch
git fetch --quiet origin "$BRANCH" || fail "git fetch failed"
REMOTE_SHA="$(git rev-parse "origin/$BRANCH")"
LOCAL_SHA="$(git rev-parse HEAD 2>/dev/null || echo none)"

if [ "$REMOTE_SHA" = "$LOCAL_SHA" ]; then
    exit 0                      # nothing new
fi

log "deploying $LOCAL_SHA -> $REMOTE_SHA"

git reset --hard --quiet "origin/$BRANCH" || fail "git reset failed"

# ---------------------------------------------------------------- sanity
[ -d "$REPO_DIR/site" ]  || fail "release has no site/ directory"
[ -d "$REPO_DIR/admin" ] || fail "release has no admin/ directory"
[ -f "$REPO_DIR/site/index.html" ] || fail "site build is missing index.html"

# ---------------------------------------------------------------- backups
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p /home/arkeonet/backups/wiz
tar czf "/home/arkeonet/backups/wiz/site-$STAMP.tar.gz" -C "$DOCROOT" . 2>/dev/null || true
if [ -d "$APPDIR" ]; then
    tar czf "/home/arkeonet/backups/wiz/app-$STAMP.tar.gz" \
        --exclude='storage' -C "$APPDIR" . 2>/dev/null || true
fi
# keep the last 10 of each
ls -1t /home/arkeonet/backups/wiz/site-*.tar.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
ls -1t /home/arkeonet/backups/wiz/app-*.tar.gz  2>/dev/null | tail -n +11 | xargs -r rm -f

# ---------------------------------------------------------------- app (outside webroot)
mkdir -p "$APPDIR"
# never overwrite runtime state
rsync -a --delete \
    --exclude '.env' \
    --exclude 'storage/logs/*' \
    --exclude 'storage/cache/*' \
    --exclude 'storage/sessions/*' \
    --exclude 'storage/uploads/*' \
    --exclude 'storage/backups/*' \
    --exclude 'vendor/' \
    "$REPO_DIR/admin/" "$APPDIR/"

# ---------------------------------------------------------------- site (public)
rsync -a --delete \
    --exclude 'index.php' \
    --exclude '.htaccess' \
    --exclude 'uploads/' \
    "$REPO_DIR/site/" "$DOCROOT/"

# ---------------------------------------------------------------- front controller + rules
install -m 0644 "$APPDIR/deploy/index.php" "$DOCROOT/index.php" 2>/dev/null || true
install -m 0644 "$APPDIR/deploy/htaccess"  "$DOCROOT/.htaccess" 2>/dev/null || true

# ---------------------------------------------------------------- migrations
if [ -f "$APPDIR/database/migrate.php" ] && [ -f "$APPDIR/vendor/autoload.php" ]; then
    ( cd "$APPDIR" && /opt/cpanel/ea-php84/root/usr/bin/php database/migrate.php migrate ) \
        || fail "migrations failed"
fi

# ---------------------------------------------------------------- health check
CODE="$(curl -s -o /dev/null -w '%{http_code}' -m 20 "https://wiz.arkeonethiopia.com/?cb=$STAMP")"
if [ "$CODE" != "200" ]; then
    log "WARNING: home page returned $CODE after deploy"
else
    log "home page 200 OK"
fi

log "done — $(cat "$REPO_DIR/RELEASE" 2>/dev/null || echo "$REMOTE_SHA")"
