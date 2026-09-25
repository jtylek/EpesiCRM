---
name: publish-epesicrm
description: Publish the committed epesi-laravel code to the public EpesiCRM repo (github.com/jtylek/EpesiCRM, branch `laravel`) as one snapshot commit, without the private history. Use only when the user asks to publish, update or push to EpesiCRM.
---

# Publishing to EpesiCRM

epesi-laravel's own repo is private. The code is published to the `laravel` branch of the
public [EpesiCRM](https://github.com/jtylek/EpesiCRM) repo. That repo's `main` is still the
legacy Epesi code, with no shared history with this one.

Each update is **one snapshot commit** of the code, built on the previous snapshot:

- the private history (every commit on `main`) is never published;
- every update is a plain fast-forward push, never a force-push;
- the snapshot's message lists the subjects of the private commits it adds.

```bash
.claude/skills/publish-epesicrm/scripts/publish.sh --dry-run   # build it and show it
.claude/skills/publish-epesicrm/scripts/publish.sh             # build it and push it
```

It publishes `main` as committed: uncommitted files and other branches are left out. Pass
a commit to publish something else. Show the user the dry run's message and summary before
pushing.

## Rules

- **Never push a branch of this repo to EpesiCRM directly** (`git push <EpesiCRM> main`).
  That publishes the private history. There is deliberately no remote for it; the script
  keeps the public tip in `refs/epesicrm/laravel`.
- **Never force-push.** If the push is rejected, someone changed `laravel` on GitHub. Fetch
  it and find out what changed before doing anything else.
- **`AI-private/` is never published.** It's gitignored, so no commit holds it, and the
  script refuses a tree that does.
- The script finds the private commit the last snapshot came from by its tree. If none
  matches (the branch was edited on GitHub), the snapshot is still built but its message
  lists no changes; write one with `git commit-tree` instead.
