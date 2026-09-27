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
- **The app's caches.** The same `afterChange()` also runs `config:clear`, `route:clear` and
  `filament:optimize-clear`, which act on the checkout's real `bootstrap/cache`. A development
  checkout caches none of these, so it changes nothing there. With `config:cache` or
  `route:cache` in place, a test run removes them.
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

## Making runs independent

Not built yet. There are two ways to do it:

- **A token per run.** Laravel's parallel testing (`php artisan test --parallel`) gives each
  process a token, and `Storage::fake()` then uses `<disk>_test_<token>` as its folder.
  `ParallelTesting::token()` reads `$_SERVER['TEST_TOKEN']`. Setting that per process in
  `TestCase::createApplication()` (the process id, say) and adding it to
  `VIEW_COMPILED_PATH` would give each run its own fake disks and compiled views. As long as
  `LARAVEL_PARALLEL_TESTING` stays unset, nothing else switches to parallel behaviour. The
  per-run folders need removing when the run ends, or they pile up.
- **A lock.** Take a file lock in the test bootstrap, so a second run waits for the first one
  to finish. It's simpler, and it also covers shared state nobody has found yet. The cost is
  that a quick filtered run waits for another session's whole suite.

The token is the better choice: runs stay independent, and a session never waits on another.
