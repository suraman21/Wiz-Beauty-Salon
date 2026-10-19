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
PHP_BIN="/opt/cpanel/ea-php84/root/usr/bin/php"

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
# local copy can never hold the pipeline back. The swap is a rename, so the
# running process keeps executing the file it started with.
if [ -f "$REPO_DIR/admin/deploy/deploy.sh" ] && \
   ! cmp -s "$REPO_DIR/admin/deploy/deploy.sh" "$REPO_DIR/deploy.sh"; then
    # Replace by rename, never in place: `cp` over a running script rewrites the
    # same inode, and bash reads its own source as it executes — the run then
    # dies at a random line with a quoting error. A rename gives the new file a
    # new inode, so the running shell keeps reading the old one in full.
    cp "$REPO_DIR/admin/deploy/deploy.sh" "$REPO_DIR/deploy.sh.new"
    chmod +x "$REPO_DIR/deploy.sh.new"
    mv -f "$REPO_DIR/deploy.sh.new" "$REPO_DIR/deploy.sh"
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
# --delete cleans the release tree, but storage/ holds live sessions, logs and
# uploads: excluding the directory itself keeps rsync out of it entirely.
rsync -a --delete \
    --exclude '.env' \
    --exclude 'storage/' \
    --exclude 'vendor/' \
    "$REPO_DIR/admin/" "$APPDIR/"

# ---------------------------------------------------------------- runtime state
# These live outside the release: rsync must never own them.
mkdir -p "$APPDIR/storage/logs" "$APPDIR/storage/sessions" "$APPDIR/storage/cache" \
         "$APPDIR/storage/uploads" "$APPDIR/storage/backups"
chmod 775 "$APPDIR/storage"
chmod 775 "$APPDIR/storage/logs" "$APPDIR/storage/sessions" "$APPDIR/storage/cache" \
          "$APPDIR/storage/uploads" "$APPDIR/storage/backups"

if [ ! -f "$APPDIR/.env" ]; then
    log "WARNING: $APPDIR/.env is missing — /admin cannot reach the database"
fi

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
DEPLOY_FAILED=0
if [ -f "$APPDIR/database/migrate.php" ]; then
    if ( cd "$APPDIR" && "$PHP_BIN" database/migrate.php migrate ); then
        log "migrations: up to date"
    else
        log "ERROR: migrations failed — /admin may be serving an older schema"
        DEPLOY_FAILED=1
    fi
fi

# ---------------------------------------------------------------- health check
# Probed against the origin, never through Cloudflare: the server's own request
# to its public hostname is answered by the CDN's bot rules, not by this site.
#
# 127.0.0.1 is not the answer either — cPanel's default vhost answers that
# address regardless of the Host header, so a dead site still looks healthy.
# Resolving the domain to this machine's own IP overrides DNS while keeping the
# real hostname (and therefore the correct SNI and vhost).
ORIGIN_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
[ -n "$ORIGIN_IP" ] || ORIGIN_IP="$(hostname -i 2>/dev/null | awk '{print $1}')"

probe() {
    curl -s -o /dev/null -w '%{http_code}' -m 15 \
        --resolve "wiz.arkeonethiopia.com:443:$ORIGIN_IP" \
        "https://wiz.arkeonethiopia.com$1?cb=$STAMP"
}

CODE="000"
for attempt in 1 2 3 4 5; do
    CODE="$(probe '/')"
    [ "$CODE" = "200" ] && break
    sleep 3
done

if [ "$CODE" = "200" ]; then
    log "origin health check: 200 OK (via $ORIGIN_IP)"
else
    log "WARNING: origin returned $CODE after deploy"
fi

# The admin sign-in screen proves the PHP app, its autoloader and its templates
# are all in place. It answers 200 without touching the database, so a broken
# deploy is what this catches — not a slow query.
APPCODE="000"
for attempt in 1 2 3 4 5; do
    APPCODE="$(probe '/admin/login')"
    [ "$APPCODE" = "200" ] && break
    sleep 3
done

if [ "$APPCODE" = "200" ]; then
    log "admin health check: 200 OK"
else
    log "WARNING: /admin/login returned $APPCODE after deploy"
    [ "$DEPLOY_FAILED" -eq 0 ] && DEPLOY_FAILED=2
fi

if [ "$DEPLOY_FAILED" -ne 0 ]; then
    exit 1
fi
