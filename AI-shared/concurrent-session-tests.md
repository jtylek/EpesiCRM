# Tests when several sessions share one checkout

Several sessions (AI agents, or an agent and a developer) often work in the same working tree at
the same time, and each may run `php artisan test`. This note covers what two test runs share,
what that can break, and how to run tests while someone else is working in the tree.

## What two runs don't share

- **The database.** `phpunit.xml` gives every run its own in-memory SQLite database. Cache and
  session are `array`, mail is `array` and the queue is `sync`, so they stay inside the process
  too.
- **The module list.** Tests boot modules from their manifests (`MODULES_FROM_MANIFESTS`), and
  in that mode `ModuleRegistry::refresh()` doesn't write `bootstrap/cache/epesi-modules.php`,
  which belongs to the real install.
- **Scratch folders.** Tests that need a folder of their own name it with `uniqid()` under
  `storage/framework/testing/` (`SetupTest`, `LegacyImportTest`, `SystemUpdateTest`,
  `DemoResetTest`, `RetryingFilesystemTest`). `RoundcubeTest` uses a random folder in the
  system temp directory.

## What they do share

- **Fake disks.** Before every test, `Tests\TestCase::setUp()` calls `Storage::fake()` for
  `FileStorage::DISK` and `CustomTranslations::DISK`. Several tests also fake `local`.
  `Storage::fake()` first empties the disk's folder under `storage/framework/testing/disks/`,
  and that folder is the same for every run. So one run can store a file and find it gone
  before it reads it back, because the other run's next test emptied the folder. The tests at
  risk are the ones that store files: Notes and Attachments files, mail attachments, custom
  translations, the legacy import of files.
- **Compiled views.** `TestCase::createApplication()` points `VIEW_COMPILED_PATH` at
  `storage/framework/testing/views`. That keeps tests away from the running app's compiled
  views, but every test run uses the same folder. A test that installs modules (the setup
  wizard in `SetupTest`) goes through `ModuleInstaller::afterChange()`, which runs
  `view:clear` and empties that folder while the other run renders from it.
  `App\Support\RetryingFilesystem` only covers the temporary file a compile is writing, not
  a compiled view deleted before it is included.
- **The app's caches.** The same `afterChange()` also calls `FrameworkCaches::forget()`
  (config, routes, events, Filament's components, Blade Icons), which acts on the checkout's
  real `bootstrap/cache`. A development checkout caches none of these (`optimize.enabled` is
  off in a git checkout), so it changes nothing there. With caches in place, a test run
  removes them. Never build them in this checkout: a cached config outlives `phpunit.xml`, and
  every test run would then use the MySQL database. `OptimizeTest` uses stand-in files in a
  scratch folder for that reason.
- **The working tree.** A run tests every file as it is on disk, including another session's
  half-finished edits. A failure may come from their change, not yours. This isn't a race
  between runs, and taking turns doesn't fix it.

## Announcing a test run

Before running tests, tell the other sessions working in the checkout, so they hold their own
test runs until yours is done. When it finishes, tell them again. This matters most for the
full suite or anything else long.

- In Claude Code, `ListAgents` lists the other Claude sessions on this machine. Send each one a
  message with `SendMessage`.
- The first message says what you are about to run (the full suite, or which test files), and
  asks them to wait for your results before running tests of their own.
- The second message gives the result: passed, or which tests failed. They can then tell
  whether a failure concerns their change.
- A session that has been told a run is starting doesn't start tests until the result
  arrives. If nothing arrives (the other session was closed or stopped), check with
  `ListAgents` or ask it before going ahead.

## Running tests meanwhile

- Run the tests for what you changed: the test file, or `php artisan test --filter=Name`.
  Keep the full suite for just before committing.
- A failure outside your change, or failures that differ from run to run, are probably not
  yours. Rerun only the failing files, and check `git status` for other sessions' uncommitted
  files in the code under test.
- To see whether your change causes a failure, turn off your own change only. Don't use
  `git stash`: it takes every session's uncommitted work out of the tree, not just yours.
- Don't fix or revert another session's files to make the suite pass.

## The lock

`tests/bootstrap.php` (PHPUnit's bootstrap in `phpunit.xml`) takes an exclusive OS file lock on
`storage/framework/testing/test-run.lock` and holds it until the process exits, even if it
crashes. A second run prints who holds it (read from `test-run.info`, a separate file because
Windows blocks reads of a locked one), waits, and gives up after 30 minutes. It works for any
agent or developer, with no messaging needed, so `SendMessage` announcements are now optional.
It doesn't fix the working-tree problem above: a run still tests everyone's edits.

A token per run (`php artisan test --parallel`'s `TEST_TOKEN`, added to the fake disks and
`VIEW_COMPILED_PATH`) would let runs overlap instead of queueing. Not built.
