<?php

/*
 * PHPUnit bootstrap: autoload, then take a lock so only one test run goes at a
 * time in this checkout. Runs share the fake disks and compiled views under
 * storage/framework/testing/ (see AI-shared/concurrent-session-tests.md), and
 * several agents (Claude, Codex, a developer) may start a run at any moment.
 *
 * The lock is an OS file lock held for the life of the process, so it is
 * released when the run ends, however it ends. A second run waits for it
 * instead of failing. Who holds it is written to test-run.info, a separate file
 * because Windows won't let other processes read a locked one.
 */

require dirname(__DIR__).'/vendor/autoload.php';

// Parallel workers belong to a run that already holds the lock.
if (getenv('LARAVEL_PARALLEL_TESTING') || isset($_SERVER['LARAVEL_PARALLEL_TESTING'])) {
    return;
}

$dir = dirname(__DIR__).'/storage/framework/testing';
is_dir($dir) || @mkdir($dir, 0777, true);

$lock = fopen($dir.'/test-run.lock', 'c');
$info = $dir.'/test-run.info';

if ($lock === false) {
    return;
}

if (! flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another test run holds the lock; waiting for it to finish.\n");
    if (is_file($info)) {
        fwrite(STDERR, '  '.trim((string) @file_get_contents($info))."\n");
    }

    $waited = 0;
    while (! flock($lock, LOCK_EX | LOCK_NB)) {
        sleep(2);
        $waited += 2;

        if ($waited >= 1800) {
            fwrite(STDERR, "Gave up after 30 minutes. If no run is active, delete $dir/test-run.lock and retry.\n");
            exit(1);
        }
    }
}

file_put_contents($info, sprintf("pid %d, started %s, args: %s\n", getmypid(), date('Y-m-d H:i:s'), implode(' ', array_slice($_SERVER['argv'] ?? [], 1))));

$GLOBALS['epesi_test_run_lock'] = $lock; // keep the handle open until the process exits

register_shutdown_function(static function () use ($info): void {
    @unlink($info);
});
