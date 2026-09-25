#!/usr/bin/env bash
# Signed-in screenshots of the running app: creates a session, shoots each
# page, and always removes the session and its login_audits row afterwards.
#
#   .claude/skills/run-epesi/scripts/shoot.sh [--light] [--user EMAIL] [--out DIR] PAGE...
#
# PAGE is a path under APP_URL, optionally "@Tab" to click a tab first:
#   shoot.sh meetings/13@History ''        ('' is the dashboard)
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../../../.." && pwd)"
theme=dark
user=
# temp/ is git-ignored, and not reachable over HTTP: the root .htaccess sends
# every request into public/.
out="${EPESI_SCREENSHOTS:-$root/temp/screenshots}"

while [ $# -gt 0 ]; do
    case "$1" in
        --light) theme=light; shift ;;
        --user) user="$2"; shift 2 ;;
        --out) out="$2"; shift 2 ;;
        *) break ;;
    esac
done
[ $# -gt 0 ] || { sed -n '2,9p' "$0"; exit 2; }

base="$(grep -E '^APP_URL=' "$root/.env" | cut -d= -f2- | tr -d '"'"'"'\r')"
session="$(mktemp -u "${TEMP:-${TMPDIR:-/tmp}}/epesi-session-XXXXXX").json"
native() { command -v cygpath >/dev/null && cygpath -m "$1" || printf '%s' "$1"; }

run_session() {
    ( cd "$root" && EPESI_SESSION_FILE="$(native "$session")" EPESI_SESSION_MODE="$1" EPESI_SESSION_USER="$user" \
        php artisan tinker --execute="require '$(native "$here/session.php")';" < /dev/null )
}

run_session create
trap 'run_session remove' EXIT

node "$(native "$here/shoot.cjs")" "$(native "$session")" "$base" "$(native "$out")" "$theme" "$@"
