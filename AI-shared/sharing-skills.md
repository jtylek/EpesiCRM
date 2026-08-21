# Sharing custom Claude Code skills across developers/computers

How to make a custom `/trigger-name` action (e.g. `/monitor-error-logs`, see
`log-monitoring.md`) work the same way for every developer, on every computer, every
time this repo is checked out — not just on the machine it was first written on.
Written 2026-08-21 after getting this wrong twice in a row before landing on the
setup below.

## Use `.claude/skills/<name>/SKILL.md`, not `.claude/commands/<name>.md`

The legacy `.claude/commands/<name>.md` format is **not reliably picked up in every
Claude Code surface** — confirmed broken (silent `Unknown command: /monitor-error-logs`)
inside a VSCode-extension/Agent-SDK session, even though the file existed, was
correctly placed, and had valid frontmatter. `.claude/skills/<name>/SKILL.md` is the
mechanism that actually registers there. Docs describe commands and skills as "the same
mechanism," but in practice only the skills layout is safe to rely on — use it for any
new trigger like this, don't reach for `commands/`.

Minimal working frontmatter (confirmed against a real bundled example skill,
`example-plugin/skills/example-command/SKILL.md`):

```markdown
---
name: monitor-error-logs
description: Short description shown in /help
---

Body: the actual instructions to follow when this skill is invoked.
```

`name`/`description` are enough to register; `argument-hint`, `allowed-tools`, and
`model` are optional extras (see that same bundled example for the full reference).

## `.claude/` needs to be git-tracked for this to actually travel with `git clone`/`pull`

This repo's `.gitignore` used to ignore `.claude/` wholesale (same bucket as `.vscode/`,
personal editor config). That's fine for genuinely personal state
(`.claude/settings.json`'s permission allowlist, `.claude/scheduled_tasks.lock`), but it
also meant a skill placed at `.claude/skills/<name>/SKILL.md` never left the machine it
was written on — defeating the entire point of a shared skill. `AI-shared/` is git-tracked
specifically so notes travel with `git clone`/`pull` to every developer and computer (see
`README.md`); the same needs to be true for shared skills.

**First fix attempt (didn't work):** keep `.claude/` ignored wholesale, add a negation
line for the subdirectory:

```gitignore
.claude/
!.claude/skills/
!.claude/skills/**
```

`git check-ignore -v` on a file under `.claude/skills/` still reported it as ignored by
the bare `.claude/` line, and `git status` never listed it. Root cause: per the `gitignore`
docs, a plain directory pattern like `.claude/` causes git to **prune traversal into that
directory entirely** for performance — any `!`-negation pattern for a path underneath it
is never even evaluated. This isn't specific to `.claude/`; the same failure would hit
any attempt to un-ignore a subdirectory of a directory that's ignored via a bare
`dir/`-style pattern.

**Second attempt, worked around the breakage instead of fixing it (unnecessary
complexity, since removed):** kept `.claude/` fully ignored and mirrored the skill's
content into a second copy under `AI-shared/commands/<name>.md`, with the (non-working,
per above) `.claude/commands/<name>.md` as a thin stub pointing at it. This achieved
"shared across computers" for the content, but at the cost of two copies that could
drift, and it was layered on top of the wrong local-registration mechanism (commands
instead of skills) in the first place.

**Actual fix:** stop ignoring `.claude/` as a whole, and exclude the personal files
inside it individually instead:

```gitignore
.claude/settings.json
.claude/*.lock
```

This works because git now walks into `.claude/` normally (nothing prunes the whole
directory), so only the two specifically-listed files are excluded — everything else,
including `.claude/skills/`, is tracked like any other file. Verify with
`git status --short --untracked-files=all .claude/` after any change here: it should
list exactly the skill file(s), not `settings.json` or `*.lock`.

## Net result

`.claude/skills/<name>/SKILL.md` is now the single git-tracked source of truth for a
shared skill — no separate `AI-shared/commands/` mirror, no stub file pointing elsewhere.
Author a new shared skill directly there.

**How to apply**: if you're adding a new shared `/trigger` action to this repo, create
`.claude/skills/<name>/SKILL.md` directly (skill format, not `commands/`), and don't add
anything to `.gitignore` for it — the existing `.claude/settings.json` / `.claude/*.lock`
exclusions already leave everything else in `.claude/` tracked by default. If `git status`
ever shows a new skill file as untracked-and-staying-that-way (`??` never turning into
something `git add` picks up, or `git check-ignore -v` reporting it matched), suspect a
reintroduced bare `.claude/` (or similarly-shaped) ignore pattern before assuming the file
itself is wrong.

**Known limitation**: a skill added to `.claude/skills/` mid-session is not recognized by
that same running session — the available-skills list appears to be fixed at session start.
Confirmed 2026-08-21: `/monitor-error-logs` was still "Unknown command" in the session that
created it, then worked immediately in a fresh session/window with no other changes needed.
Always test a newly-added skill from a new session, not the one that just created it.
