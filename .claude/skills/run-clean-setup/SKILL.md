---
name: run-clean-setup
description: Use when the user explicitly asks to rerun or restart the Epesi browser installation from a clean database. Pulls the current branch from origin, wipes the configured application database, removes install-state files, and reports when setup is ready.
---

# Run Clean Setup

Use only for an explicitly requested clean installation reset. Wiping the database permanently deletes its application data.

1. Read `AI-shared/Reinstall-steps.md`. Identify the configured database connection and database name from `.env` without printing credentials. Inventory its tables; stop and ask if the database contains unrelated tables or its target is ambiguous.
2. Check the current branch and worktree. Preserve unrelated user changes. Pull with `git pull --ff-only origin <current-branch>`; do not stash, discard, or overwrite local changes. If the pull cannot complete safely, stop before resetting.
3. After confirming the user authorized the reset, run `php artisan db:wipe --database=<configured-connection> --force` from the repository root. This drops the application tables without dropping the database itself.
4. Remove the install marker at `SETUP_MARKER_PATH` if configured, otherwise `storage/app/epesi-installed.json`. Remove `bootstrap/cache/epesi-modules.php`.
5. Keep `.env`, `APP_KEY`, module source, `vendor/`, `public/build/`, `storage/`, and `storage/app/setup-code.txt`. Do not delete uploads, logs, or other storage contents. Only remove the setup code if the user separately asks for a new one.
6. If `storage/app/setup-code.txt` exists, read it and include its contents directly in the chat reply. Do not rely on terminal output to display the code. If the file does not exist, say so in the chat; the setup request can generate it when needed.
7. Report `Ready for clean installation` only after the pull, database wipe, and state-file cleanup all succeed. Report any failure clearly instead of claiming readiness.