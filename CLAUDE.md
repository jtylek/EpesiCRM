# CLAUDE.md

The instructions for all agents live in [AGENTS.md](AGENTS.md), imported below. Put anything
that applies to every agent there; this file holds only what is specific to Claude Code.

@AGENTS.md

## Claude Code only

- Claude Code's own memory never replaces the committed docs: other agents can't see it.
- Before and after a test run, also tell the other Claude sessions on this machine
  (`ListAgents`, then `SendMessage`), as described in
  [AI-shared/concurrent-session-tests.md](AI-shared/concurrent-session-tests.md). This is
  the only messaging between sessions; there is no mailbox or other agent messaging.
- For long or risky work, `EnterWorktree` creates the separate worktree AGENTS.md recommends.

## Model routing in this repo

Work on Sonnet by default. The `quick` (Haiku) and `deep` (Opus) agents are defined in
`.claude/agents/` and shared with the repo. Do small or ordinary tasks yourself; never
delegate something that takes 1-3 tool calls. Escalate to `deep` for hard work (shared
infrastructure, more than about 5 files or several modules, legacy ports, security,
migrations or data-loss risk, a bug that survived a first fix, real design trade-offs),
and give it full context since it starts cold. Here, escalate to `deep` for:

- the RecordBrowser engine, migrations, module loading/registry, or `app/Support`: the same
  shared infrastructure that requires a full-suite run;
- legacy ports (read the Epesi source) and `App/Services/LegacyImport`;
- boot-order, morph-map or MySQL-vs-SQLite DDL questions;
- access rules (ownership scopes, policies, Shield, panel access).

Use `quick` for repo-wide searches. **Every test run goes to `quick`**, scoped or the full
suite: give it the exact command and ask for pass/fail counts plus each failing test with its
key error line. The calling session keeps the coordination: it sends the
`ListAgents`/`SendMessage` announcements before and after, and reads the timestamps
for a full-suite run. It also decides what a failure means; `quick` only reports it.

Subagents still follow the test-lock and git rules in AGENTS.md.
