#!/usr/bin/env bash
# Deploys epesi-laravel to a public demo (DEMO_MODE) as the release zip
# `epesi:package` builds, the same one people download. Run from Git Bash on
# the Windows development machine: the build uses its PHP, Composer and Node,
# and every ssh/scp goes through WSL.
#
#   deploy.sh [--dry-run] [--no-reset] [<commit>]   build, upload, swap, reset, check
#   deploy.sh first-deploy [--no-backup] [<commit>] replace a legacy Epesi demo, once
#   deploy.sh rollback                              the previous release back
#   deploy.sh audit [<days>]                        who used the demo (demo:audit)
#   deploy.sh visitors [<days>]                     fetch + merge + render the visitor report (../demo-visitors/)
#   deploy.sh status                                what is deployed, and its state
#
# <commit> defaults to main. --dry-run builds and uploads the zip but changes
# nothing live. The target (host, paths, PHP binary) comes from
# AI-private/epesicrm-demo.env, never from this published file. AI-private
# lives once, globally, as a sibling of every project checkout, not nested
# inside any one of them.
set -euo pipefail

root="$(git rev-parse --show-toplevel)"
target="$(dirname "$root")/AI-private/epesicrm-demo.env"
[ -f "$target" ] || { echo "No $target: the demo's host and paths are kept there (core developers' AI-private, a sibling of this checkout)." >&2; exit 1; }
# shellcheck disable=SC1090
source <(tr -d '\r' < "$target")
: "${DEMO_SSH:?}" "${DEMO_SSH_KEY:?}" "${DEMO_LIVE:?}" "${DEMO_DEPLOY:?}" "${DEMO_PHP:?}" "${DEMO_URL:?}" "${DEMO_DB:?}"

mode=deploy
case "${1:-}" in first-deploy|rollback|audit|visitors|status) mode=$1; shift ;; esac
dry_run=false
reset=true
backup=true
while [ $# -gt 0 ]; do
    case "$1" in
        --dry-run) dry_run=true; shift ;;
        --no-reset) reset=false; shift ;;
        --no-backup) backup=false; shift ;;
        *) break ;;
    esac
done

ssh_opts="-i $DEMO_SSH_KEY -F /dev/null -o BatchMode=yes -o ConnectTimeout=20"

# A command line for WSL's bash. Git Bash would otherwise turn /dev/null and
# other absolute paths in it into Windows paths first. Only here: git,
# Composer and PHP need that conversion for their own arguments.
in_wsl() {
    MSYS_NO_PATHCONV=1 wsl -e bash -c "$1"
}

# Runs the script on stdin on the server, with the target's settings in front.
# One output stream: with stdout and stderr on separate handles, wsl.exe has
# lost the first lines of stdout.
remote() {
    { printf 'set -euo pipefail\nLIVE=~/%s\nDEPLOY=~/%s\nPHP=%s\nDB=%s\n' "$DEMO_LIVE" "$DEMO_DEPLOY" "$DEMO_PHP" "$DEMO_DB"; cat; } \
        | in_wsl "ssh $ssh_opts $DEMO_SSH 'bash -s' 2>&1"
}

build() {
    local commit build out
    commit="$(git -C "$root" rev-parse --verify "${1:-main}^{commit}")"
    build="$(cygpath -u "${LOCALAPPDATA:?}")/epesi-demo-build"
    out="$build-out"

    if [ -n "$(git -C "$root" status --porcelain --untracked-files=no)" ]; then
        echo "Note: the checkout has uncommitted changes. They are not deployed: the build uses commit ${commit:0:7} only." >&2
    fi

    echo "Building ${commit:0:7} in $build"
    [ -d "$build/.git" ] || git clone --quiet --no-checkout "$root" "$build"
    # Fetch from the checkout the script is called from, not from wherever the
    # clone was first made (a renamed or second checkout left it stale).
    git -C "$build" remote set-url origin "$root"
    git -C "$build" fetch --quiet origin
    git -C "$build" checkout --quiet --force --detach "$commit"
    git -C "$build" clean -fdxq -e vendor/ -e node_modules/ -e .env

    (
        cd "$build"
        # Windows sometimes can't delete a file Composer is replacing (a virus
        # scanner or the indexer holds it); a second run finishes the job.
        for try in 1 2 3; do
            composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --quiet && break
            [ "$try" = 3 ] && { echo "composer install failed three times" >&2; exit 1; }
            echo "composer install failed, trying again"
        done
        npm ci --no-audit --no-fund --loglevel=error
        npm run build --silent > /dev/null
        # Only so artisan can boot here; .env is never packed.
        [ -f .env ] || { cp .env.example .env && php artisan key:generate --force --quiet; }
        rm -rf "$out" && mkdir -p "$out"
        php artisan epesi:package --out="$(cygpath -w "$out")"
    )

    zip="$(ls -t "$out"/epesi-*.zip | head -n 1)"
    name="$(basename "$zip")"
}

upload() {
    local wzip
    wzip="$(in_wsl "wslpath -u '$(cygpath -m "$zip")'")"
    echo "Uploading $name"
    in_wsl "ssh $ssh_opts $DEMO_SSH 'mkdir -p ~/$DEMO_DEPLOY/incoming' && scp -q $ssh_opts '$wzip' '$wzip.sha256' $DEMO_SSH:$DEMO_DEPLOY/incoming/"
    # Without "\r": a checksum file written on Windows by an older epesi:package.
    remote <<EOF
cd "\$DEPLOY/incoming"
tr -d '\r' < "$name.sha256" | sha256sum -c --quiet -
echo "checksum OK"
ls -t epesi-*.zip | tail -n +4 | while read -r old; do rm -f "\$old" "\$old.sha256"; done
EOF
}

# Unpacked outside the docroot: a second copy inside it would be a second
# working app on the same database. A zip written on Windows carries no Unix
# modes, so they are set: directories 755, files 644, as the web server
# needs to read public/.
unpack_staging() {
    cat <<EOF
rm -rf "\$DEPLOY/staging"
unzip -q "\$DEPLOY/incoming/$name" -d "\$DEPLOY/staging"
chmod -R u=rwX,go=rX "\$DEPLOY/staging"
EOF
}

check() {
    echo
    echo "Checks against $DEMO_URL"
    local code failed=0
    code="$(curl -s -o /tmp/demo-login.html -w '%{http_code}' "$DEMO_URL/login")"
    if [ "$code" = 200 ] && grep -q 'Log in as' /tmp/demo-login.html; then echo "  login page, demo mode        200 OK"
    else echo "  login page, demo mode        $code  FAILED"; failed=1; fi
    for path in .env storage/logs/laravel.log vendor/composer/installed.json composer.json administration setup/install; do
        code="$(curl -s -o /dev/null -w '%{http_code}' "$DEMO_URL/$path")"
        case "$code" in 404|403) printf '  %-28s %s OK\n' "/$path" "$code" ;; *) printf '  %-28s %s  FAILED (must be 404)\n' "/$path" "$code"; failed=1 ;; esac
    done
    remote <<'EOF'
cd "$LIVE"
log="storage/logs/laravel-$(date +%F).log"
if [ -f "$log" ] && grep -q '\.ERROR' "$log"; then echo "  errors in $log:"; grep '\.ERROR' "$log" | tail -n 5 | cut -c1-300; else echo "  no errors logged today"; fi
EOF
    return $failed
}

case "$mode" in
deploy)
    build "${1:-}"
    upload
    if $dry_run; then echo "Dry run: $name is in $DEMO_DEPLOY/incoming; nothing live changed."; exit 0; fi
    remote <<EOF
test -f "\$LIVE/.env" && test -f "\$LIVE/artisan" || { echo "No epesi-laravel demo at \$LIVE yet: use first-deploy." >&2; exit 1; }
$(unpack_staging)
cp -p "\$LIVE/.env" "\$DEPLOY/staging/.env"
cd "\$LIVE"
\$PHP artisan down --render=epesi.demo-resetting --retry=60 || \$PHP artisan down --retry=60
# The live storage/ (installed marker, logs, the down file) replaces the skeleton.
rm -rf "\$DEPLOY/staging/storage"
cp -a "\$LIVE/storage" "\$DEPLOY/staging/storage"
rm -rf "\$DEPLOY/previous"
mv "\$LIVE" "\$DEPLOY/previous"
mv "\$DEPLOY/staging" "\$LIVE"
cd "\$LIVE"
\$PHP artisan epesi:update
$($reset && echo '$PHP artisan demo:reset --force')
\$PHP artisan up
echo "$name \$(date '+%F %T')" > "\$DEPLOY/deployed.txt"
echo "Deployed $name"
EOF
    check
    ;;

first-deploy)
    build "${1:-}"
    upload
    remote <<EOF
config="\$LIVE/data/config.php"
test -f "\$config" || { echo "No legacy Epesi at \$LIVE (no data/config.php): nothing to replace, use deploy." >&2; exit 1; }
field() { sed -n "s/^[[:space:]]*define([[:space:]]*'\$1'[[:space:]]*,[[:space:]]*'\\(.*\\)'[[:space:]]*);.*/\\1/p" "\$config" | head -n 1; }
db_user="\$(field DATABASE_USER)"; db_name="\$(field DATABASE_NAME)"; db_pass="\$(field DATABASE_PASSWORD)"
[ "\$db_name" = "\$DB" ] || { echo "The legacy config names database '\$db_name', not \$DB." >&2; exit 1; }
[ -n "\$db_pass" ] || { echo "No DATABASE_PASSWORD in the legacy config." >&2; exit 1; }
case "\$db_pass" in *"'"*) echo "The database password has a single quote; write .env by hand." >&2; exit 1 ;; esac

if [ "$backup" = true ]; then
    day="\$(date +%F)"
    mkdir -p ~/backups
    MYSQL_PWD="\$db_pass" mysqldump --single-transaction -u "\$db_user" "\$DB" | gzip > ~/backups/"\$DB-legacy-\$day.sql.gz"
    tar czf ~/backups/"demo-legacy-epesi-\$day.tar.gz" -C "\$(dirname "\$LIVE")" "\$(basename "\$LIVE")"
    echo "Backed up: ~/backups/\$DB-legacy-\$day.sql.gz, ~/backups/demo-legacy-epesi-\$day.tar.gz"
else
    echo "No backup (--no-backup): the legacy tables are dropped for good."
fi

$(unpack_staging)
cd "\$DEPLOY/staging"
grep -vE '^(#[[:space:]]*)?(APP_NAME|APP_ENV|APP_DEBUG|APP_URL|LOG_CHANNEL|LOG_STACK|LOG_LEVEL|DB_[A-Z_]+|QUEUE_CONNECTION|MAIL_MAILER|MODULES_INSTALL_ENABLED|DEMO_MODE)=' .env.example > .env
cat >> .env <<ENV

APP_NAME=epesi
APP_ENV=production
APP_DEBUG=false
APP_URL=$DEMO_URL
LOG_CHANNEL=daily
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=\$DB
DB_USERNAME=\$db_user
DB_PASSWORD='\$db_pass'
QUEUE_CONNECTION=sync
MAIL_MAILER=log
MODULES_INSTALL_ENABLED=false
DEMO_MODE=true
ENV
\$PHP artisan key:generate --force --no-interaction
\$PHP artisan down --render=epesi.demo-resetting --retry=60

# The switch: the legacy files go aside (kept until the new demo has run a while).
rm -rf "\$DEPLOY/legacy-epesi"
mv "\$LIVE" "\$DEPLOY/legacy-epesi"
mv "\$DEPLOY/staging" "\$LIVE"
cd "\$LIVE"
# Its tables go too (they are in the dump above): legacy's \`modules\` table
# collides with the port's.
\$PHP artisan db:wipe --force
\$PHP artisan demo:reset --force
\$PHP artisan up
echo "$name \$(date '+%F %T')" > "\$DEPLOY/deployed.txt"
echo "Deployed $name over the legacy demo (moved to \$DEPLOY/legacy-epesi)."
echo
echo "Still to do: the scheduler's cron line (crontab -e), then check a reset runs:"
echo "* * * * * cd \$HOME/$DEMO_LIVE && $DEMO_PHP artisan schedule:run > /dev/null 2>&1"
EOF
    check
    ;;

rollback)
    remote <<'EOF'
test -d "$DEPLOY/previous" || { echo "No previous release in $DEPLOY/previous." >&2; exit 1; }
cd "$LIVE"
$PHP artisan down --retry=60 || true
failed="$DEPLOY/failed-$(date +%Y%m%d-%H%M%S)"
mv "$LIVE" "$failed"
mv "$DEPLOY/previous" "$LIVE"
cd "$LIVE"
$PHP artisan demo:reset --force
$PHP artisan up
echo "Rolled back; the release that was live is in $failed."
EOF
    check
    ;;

audit)
    remote <<EOF
cd "\$LIVE" && \$PHP artisan demo:audit --days=${1:-30}
EOF
    ;;

visitors)
    days="${1:-3650}"
    visitors_dir="$(dirname "$root")/demo-visitors"
    mkdir -p "$visitors_dir/raw"
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    raw="$visitors_dir/raw/login_audits-$stamp.csv"

    echo "Fetching demo:audit --raw --days=$days"
    remote <<EOF > "$raw"
cd "\$LIVE" && \$PHP artisan demo:audit --raw --days=$days
EOF

    if ! head -n 1 "$raw" | grep -q '^id,user_id,login,impersonated_by,started_at,ended_at,ip_address,host_name,device$'; then
        echo "Unexpected output from demo:audit --raw (has the live demo been deployed since it shipped?):" >&2
        cat "$raw" >&2
        rm -f "$raw"
        exit 1
    fi

    echo "Saved $raw ($(($(wc -l < "$raw") - 1)) sessions)"
    php "$root/.claude/skills/update-epesi-demo/scripts/build-visitor-report.php" "$visitors_dir"
    ;;

status)
    remote <<'EOF'
cd "$LIVE"
echo "Deployed:  $(cat "$DEPLOY/deployed.txt" 2>/dev/null || echo unknown)"
echo "Demo mode: $(grep -E '^DEMO_MODE=' .env 2>/dev/null || echo 'not set')"
echo "Down:      $([ -f storage/framework/down ] && echo yes || echo no)"
echo "Reset:     $([ -f storage/app/private/demo/pending ] && echo 'INTERRUPTED (demo/pending exists)' || echo 'not pending')"
echo "Last kept: $(ls -t storage/app/private/demo/keep-*.json 2>/dev/null | head -n 1 || echo none)"
echo "Cron:      $(crontab -l 2>/dev/null | grep -F "$(basename "$LIVE") && " | grep -F 'schedule:run' || echo 'no schedule:run line')"
EOF
    ;;
esac
