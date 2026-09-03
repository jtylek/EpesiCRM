---
name: ci-local
description: Run tools/ci-local.bat (the local equivalent of the disabled GitHub CI workflow — lint, PHPStan, Rector, console.php list) and analyze the output
allowed-tools: [Bash, Read]
---

# Run the local CI script and analyze it

Background: `.github/workflows/ci.yml` is `disabled_manually` on GitHub, so
`tools\ci-local.bat` is the only way these checks actually run. This skill runs it and
reads the result so the user doesn't have to.

## 1. Run the script, capturing output to a file

Don't let PowerShell's own `2>&1` do the redirect — it wraps a native command's stderr in
`ErrorRecord` objects and can garble the batch script's interleaved output. Let `cmd.exe`
do its own redirection instead. From the Bash tool, `/c` needs escaping as `//c`, and the
script is run from this checkout's own `tools\` directory:

```
cmd //c "tools\ci-local.bat > <scratchpad>\ci-output.txt 2>&1"
```

Write `<scratchpad>\ci-output.txt` into the current session's scratchpad directory (see
the system prompt's "Scratchpad Directory" section for the exact path this session — it
changes per session, never hardcode one from a prior run) — never a bare filename, which
would land in the repo root.

The script itself can take a minute or two (PHPStan alone analyses ~800 files) — don't
poll or re-run early.

## 2. Read the output file and analyze each section

Read the captured file (`Read`, not `Bash`/`cat` — it can contain raw ANSI color escape
codes from PHPStan/Rector, which `Read` handles fine). Check exit code from the command
result too: the script exits non-zero only if lint or PHPStan failed (mirrors the two
blocking CI jobs); Rector and the console list are informational only, same as the
`continue-on-error: true` jobs in `ci.yml`.

Walk the four sections:

1. **lint** — should say `OK`. Any `FAIL:` line names a real syntax error — treat as a
   blocking problem.
2. **PHPStan** — look for `[OK] No errors` vs. a findings table. Any reported finding is
   *new* (the baseline in `phpstan-baseline.neon` absorbs everything pre-existing) —
   treat as a real, actionable problem, not noise.
3. **Rector** — should report 0 files as of 2026-09-01 (the ~10 files of whitespace-only
   noise CLAUDE.md describes — "it currently applies zero actual rules" — were fixed for
   real that day: `rector process --config rector-php82.php` with no `--dry-run`, applied
   once). If it reports files again, don't assume it's the same harmless noise — skim each
   diff, or check `git diff --ignore-all-space` on the affected files, before calling it
   advisory-only. A genuine content change here is worth surfacing even though the job is
   advisory.
4. **console.php list** — cross-check against every `console.php <command>` named in
   CLAUDE.md's Commands section; every one of them should appear in the printed command
   list. A missing one is what the (currently un-runnable) `docs` CI job would have
   caught. If the whole section is empty/missing rather than just incomplete, suspect
   `console.php` blanking all output on an undefined-variable warning under
   `REPORT_ALL_ERRORS` — a known failure shape — rather than assuming the command list
   itself is wrong.

## 3. Report concisely

Summarize pass/fail per section in a few lines — don't paste the raw captured file back
into the conversation. Call out anything that's a genuine new finding (a lint failure, a
new PHPStan error, a non-whitespace Rector diff, a missing console command) prominently;
say "all clean" plainly when there's nothing to act on, the way the 2026-09-01 baseline
run came back.
