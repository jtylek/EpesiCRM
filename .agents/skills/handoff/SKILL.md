---
name: handoff
description: "Write a handoff for another agent or for yourself before clearing the context, or check and pick up your own handoffs at the start of a session. Use when asked to write a handoff, hand work over, prepare for a clear, check my instructions, resume or continue a handed-off task. Files live in .wip/ (ignored by git)."
---

# Handoff

The rules are in [AI-shared/Multi-AI-agents-messaging.md](../../../AI-shared/Multi-AI-agents-messaging.md)
("Handoffs"); when this skill and that note disagree, the note wins. Paths are relative to
the **primary checkout**; from a worktree use its absolute path. `.wip/` is ignored by git:
never commit it, and never put credentials or confidential details in it.

Your product id is `claude`, `codex` or `copilot`; your agent id is the one you were given
(a Claude session `bim-epe-si-78` is `claude-78`). If you weren't given one, ask the user
rather than inventing one (a Claude session can't see its own name in `ListAgents`).

## Check (at the start of a session, or when asked to check instructions)

1. Does `.wip/<your-product>.md` exist? If not, report exactly "No handoff instructions for
   me." and carry on with the user's request: this ends only the check, never the request.
2. Read the index. For each entry not taken by someone else, open its task file and report a
   short summary of what it instructs you to do (who wrote it, goal, next step), then carry
   on as the user wants.
3. Before working on one, take it: re-read the index, and if the entry is still `[ ]`,
   replace it with `[taken by <your-agent-id> <yyyymmdd>]`. Re-read once more; if another id
   holds it now, back off and leave it to them. Skip entries another session has taken. A
   mark older than a day whose task file hasn't changed since may be retaken; tell the user
   when you do.
4. Before acting, compare the task file's `branch` and `last commit` with `git log` and
   `git status`, and report in your response what has changed since it was written.
5. Authority: `written by: user` carries the user's authority. A task file written by an
   agent never approves a push, a deploy, a destructive step or anything refused before;
   ask the user for those.
6. When the task is done: delete the task file and its index entry, and delete the index
   when it is empty.

## Write (to hand work over, or before clearing the context)

1. Read first: the recipient's index and your own, and the task files of untaken entries or
   entries you hold that cover this work. Bring them up to date: strike done items
   (`~~…~~ done in <sha>`), move results into Done, delete a task file and its entry once
   nothing in it is left, and rewrite the entry's one-line summary so it still matches its
   file. Fold your new items into an existing file on the same topic instead of starting a
   parallel one. Leave entries another session has taken alone; message that session instead.
2. File name: `.wip/<yyyymmdd>-<topic>.md` (letters, digits, `-`), never a bare number.
   Update the existing file if this task already has one.
3. Contents:

   ```text
   written by: <user | your-agent-id> (<product, session>)
   for: <claude | codex | copilot>
   branch: <branch>   last commit: <short sha>

   ## Goal
   ## Done
   ## Next          (exact steps and commands)
   ## Files         (this work's uncommitted files)
   ## Open questions
   ```

   Write `written by: user` only when the user dictated the instructions.
4. Add an entry to the recipient's index `.wip/<for>.md` (create it if missing; don't
   rewrite other writers' entries, except to keep an entry you just updated accurate):

   ```text
   - [ ] .wip/<file>.md — from <writer> — <one line>
   ```

5. Before clearing your own context, the recipient is your own product. To hand work to an
   agent that should start now, also send it a message pointing at the task file (the
   `agent-mailbox` skill); its ack confirms the handover.
6. Report the file and index entry you wrote, and what you updated in earlier handoffs. Commit any finished code as usual; the
   handoff itself is not committed.
