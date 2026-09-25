#!/usr/bin/env bash
# Publishes the committed epesi-laravel code to the public EpesiCRM repo's
# `laravel` branch as one snapshot commit on top of the last one. The private
# history never leaves this checkout, and every update is a plain
# fast-forward push.
#
#   .claude/skills/publish-epesicrm/scripts/publish.sh [--dry-run] [<commit>]
#
# <commit> defaults to main. --dry-run builds the snapshot and shows it
# without pushing.
set -euo pipefail

URL=https://github.com/jtylek/EpesiCRM.git
BRANCH=laravel
# The public tip, kept as a plain ref rather than a remote, so there's no
# `epesicrm` remote for a stray `git push epesicrm main` to publish history to.
TIP_REF=refs/epesicrm/$BRANCH

dry_run=false
if [[ ${1:-} == --dry-run ]]; then dry_run=true; shift; fi

cd "$(git rev-parse --show-toplevel)"
source=$(git rev-parse --verify "${1:-main}^{commit}")
tree=$(git rev-parse "$source^{tree}")

git fetch --quiet --no-tags "$URL" "+refs/heads/$BRANCH:$TIP_REF"
tip=$(git rev-parse "$TIP_REF")

if [[ $tree == $(git rev-parse "$tip^{tree}") ]]; then
    echo "EpesiCRM $BRANCH already has this code ($(git rev-parse --short "$tip"))."
    exit 0
fi

# AI-private/ is gitignored, so it can't be in a commit; this is the backstop.
if git ls-tree -r --name-only "$tree" | grep -q '^AI-private/'; then
    echo "Refusing: $source contains AI-private/." >&2
    exit 1
fi

# The private commit the last snapshot was taken from: the one with the same
# tree. Its descendants are what this snapshot adds.
last=$(git log --format='%H %T' "$source" | awk -v t="$(git rev-parse "$tip^{tree}")" '$2 == t { print $1; exit }')
changes=()
if [[ -n $last ]]; then
    mapfile -t changes < <(git log --reverse --no-merges --format='%s' "$last..$source")
fi

if (( ${#changes[@]} == 1 )); then
    subject=${changes[0]}
elif (( ${#changes[@]} > 1 )); then
    subject="Update: ${#changes[@]} changes"
else
    subject="Update"
fi

message=$subject
if (( ${#changes[@]} > 1 )); then
    message+=$'\n'
    for change in "${changes[@]}"; do message+=$'\n'"- $change"; done
fi
message+=$'\n\nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>'

new=$(printf '%s\n' "$message" | git commit-tree "$tree" -p "$tip" -F -)

echo "Snapshot $(git rev-parse --short "$new") of $(git rev-parse --short "$source"), on top of $(git rev-parse --short "$tip"):"
echo
git log -1 --format='%B' "$new" | sed 's/^/    /'
git diff --stat "$tip" "$new" | tail -n 1

if $dry_run; then
    echo
    echo "Dry run: nothing pushed."
    exit 0
fi

# Never --force. A rejected push means the branch moved on GitHub; look at
# what moved it before doing anything else.
git push --quiet "$URL" "$new:refs/heads/$BRANCH"
git update-ref "$TIP_REF" "$new"
echo
echo "Pushed to $URL ($BRANCH)."
