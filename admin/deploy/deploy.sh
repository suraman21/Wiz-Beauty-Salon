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

FORCE=0
for arg in "$@"; do
    case "$arg" in
        --force|-f) FORCE=1 ;;
        *) fail "unknown argument: $arg" ;;
    esac
done

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

if [ "$REMOTE_SHA" = "$LOCAL_SHA" ] && [ "$FORCE" -eq 0 ]; then
    exit 0                      # nothing new
fi

log "deploying $LOCAL_SHA -> $REMOTE_SHA"

if [ "$LOCAL_SHA" != "$REMOTE_SHA" ]; then
    git reset --hard --quiet "origin/$BRANCH" || fail "git reset failed"
fi

# ---------------------------------------------------------------- self-update
# The release ships the current version of this script; adopt it so a stale
# local copy can never hold the pipeline back. Safe: the running process keeps
# its own inode.
if [ -f "$REPO_DIR/admin/deploy/deploy.sh" ] && \
   ! cmp -s "$REPO_DIR/admin/deploy/deploy.sh" "$REPO_DIR/deploy.sh"; then
    cp "$REPO_DIR/admin/deploy/deploy.sh" "$REPO_DIR/deploy.sh"
    chmod +x "$REPO_DIR/deploy.sh"
    log "deploy script updated — new version applies from the next run"
fi

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
ls -1t /home/arkeonet/backups/wiz/site-*.tar.gz 2>/dev/null | tail -n +11 | xargs -r rm -f || true
ls -1t /home/arkeonet/backups/wiz/app-*.tar.gz  2>/dev/null | tail -n +11 | xargs -r rm -f || true

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

# ---------------------------------------------------------------- front controller
install -m 0644 "$APPDIR/deploy/index.php" "$DOCROOT/index.php"

# ---------------------------------------------------------------- rewrite rules
# Merge our block into the existing .htaccess instead of replacing it: cPanel
# writes PHP handler directives there and must keep them. Any previous WIZ
# block — including the legacy "static site" one — is stripped first so the
# rules cannot double up or shadow each other.
HT="$DOCROOT/.htaccess"
BLOCK="$APPDIR/deploy/htaccess"
if [ -f "$BLOCK" ]; then
    if [ -f "$HT" ]; then
        awk '
            /^# BEGIN WIZ static site$/ {skip=1}
            /^# BEGIN WIZ APP$/        {skip=1}
            !skip                      {print}
            /^# END WIZ static site$/  {skip=0}
            /^# END WIZ APP$/          {skip=0}
        ' "$HT" > "$HT.tmp"
        printf '\n' >> "$HT.tmp"
    else
        : > "$HT.tmp"
    fi
    cat "$BLOCK" >> "$HT.tmp"
    mv "$HT.tmp" "$HT"
    chmod 644 "$HT"
    log "rewrite rules merged into $HT"
fi

# ---------------------------------------------------------------- migrations
if [ -f "$APPDIR/database/migrate.php" ] && [ -f "$APPDIR/vendor/autoload.php" ]; then
    ( cd "$APPDIR" && /opt/cpanel/ea-php84/root/usr/bin/php database/migrate.php migrate ) \
        || fail "migrations failed"
fi

# ---------------------------------------------------------------- health check
# Checked against the origin directly with a Host header. Going out through the
# public hostname would route back through Cloudflare, whose bot rules can
# answer the server's own request with 403 and make a good deploy look broken.
CODE="000"
for attempt in 1 2 3 4 5; do
    CODE="$(curl -s -o /dev/null -w '%{http_code}' -m 15 \
        -H "Host: wiz.arkeonethiopia.com" \
        "http://127.0.0.1/?cb=$STAMP-$attempt")"
    [ "$CODE" = "200" ] && break
    sleep 3
done

if [ "$CODE" = "200" ]; then
    log "origin health check: 200 OK"
else
    log "WARNING: origin returned $CODE after deploy"
fi

log "done — $(cat "$REPO_DIR/RELEASE" 2>/dev/null || echo "$REMOTE_SHA")"
