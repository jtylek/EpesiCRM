# Claude Code Remote Control and cross-session messaging

Written 2026-08-26. Covers two related but *separate* Claude Code mechanisms that came up
while disabling one of them on a dev machine: **Remote Control** (human control of a
session from another device) and **cross-session messaging** (agent-to-agent, via the
`SendMessage`/`ListAgents` tools). Easy to conflate — disabling one does not disable the
other.

## Remote Control

Lets another device/session on the same claude.ai account connect to and take control of
a running Claude Code session. Requires a claude.ai subscription + org enablement.

Disable with, in `settings.json`:

```json
{
  "disableRemoteControl": true
}
```

Scope depends on which `settings.json` gets the key:

- `~/.claude/settings.json` — this device/user only, applies across all projects. This is
  where it was actually set on 2026-08-26 (a personal machine setting, outside this repo —
  not something to replicate via `manage/.claude/settings.json`).
- A project's `.claude/settings.json` — that project only.
- Org-managed settings — enforced for everyone in the org; blocked users see "Remote
  Control is disabled by your organization's policy".

To kill an already-connected Remote Control session without disabling the feature: stop
the session from the connected device, or stop the server.

## Cross-session messaging (`SendMessage` / `ListAgents`) is a different mechanism

`ListAgents` discovers other running Claude sessions (local peer sessions on the same
machine, teammates, sessions on other machines, cloud sessions); `SendMessage` lets one
session message another to coordinate work.

**`disableRemoteControl: true` does *not* turn this off.** What it actually does to
cross-session reach:

- **Same-machine peer sessions**: unaffected — two Claude Code sessions running on one
  machine can still discover and message each other with Remote Control disabled.
- **Other machines / cloud sessions**: effectively cut off, because the Remote Control
  connection is the transport `ListAgents`/`SendMessage` use to reach sessions that aren't
  local.

To also block cross-session messaging itself (e.g. same-machine peers too), add:

```json
{
  "permissions": {
    "deny": ["SendMessage", "ListAgents"]
  }
}
```

**How to apply**: if a task specifically wants "no other Claude session can reach or
control this one," `disableRemoteControl: true` alone is not sufficient — it only removes
the remote/cloud leg. Pair it with the `permissions.deny` block above if same-machine
peer messaging needs to be blocked too.
