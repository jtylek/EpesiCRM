<?php

namespace Epesi\Modules\MigrationWizard\Background;

use Throwable;

/**
 * Runs the migration as a detached background process (`php artisan epesi:migrate-legacy --resume`), so that no
 * browser, web-server or proxy time limit can cut a long import short. The process is the very command-line
 * driver: same state, lock, ledger and gates. It never receives the administrator's password: it stops by itself at
 * the administrator step, and the page does that step and the rest in short browser steps.
 *
 * It is offered only when this server can really do it: `proc_open` or `exec` is usable (not listed in
 * `disable_functions`), and a PHP command-line binary was found that passes a real test (`--status` exits 0 with the
 * same application, database and extensions). Otherwise the page keeps stepping the migration itself and says why.
 */
final class BackgroundRunner
{
    /** Cached for ten minutes: the check runs a real process. */
    private const CACHE_SECONDS = 600;

    public static function logPath(): string
    {
        return storage_path('logs/migration-wizard-background.log');
    }

    /** Written when the page starts the process, removed when the page sees it has ended: lets a reopened page watch it. */
    private static function markerPath(): string
    {
        return storage_path('app/migration-wizard/background.json');
    }

    /** When the background process was started, or null when none was started (or it has been seen to end). */
    public static function startedAt(): ?int
    {
        $data = json_decode((string) @file_get_contents(self::markerPath()), true);

        return is_array($data) && is_int($data['started_at'] ?? null) ? $data['started_at'] : null;
    }

    public static function forget(): void
    {
        @unlink(self::markerPath());
    }

    private static function remember(): void
    {
        try {
            @file_put_contents(self::markerPath(), json_encode(['started_at' => time()]), LOCK_EX);
        } catch (Throwable) {
        }
    }

    /**
     * @return array{available: bool, reason: string, php: string|null, command: string}
     *                                                                                   `command` is what to run by hand either way.
     */
    public static function capability(bool $fresh = false): array
    {
        $cache = storage_path('app/migration-wizard/background-capability.json');
        if (! $fresh && is_file($cache) && filemtime($cache) > time() - self::CACHE_SECONDS) {
            $stored = json_decode((string) @file_get_contents($cache), true);
            if (is_array($stored) && isset($stored['available'], $stored['reason'], $stored['command'])) {
                return $stored;
            }
        }

        $result = self::check();
        try {
            @file_put_contents($cache, json_encode($result), LOCK_EX);
        } catch (Throwable) {
        }

        return $result;
    }

    /** @return array{available: bool, reason: string, php: string|null, command: string} */
    private static function check(): array
    {
        $manual = 'php artisan epesi:migrate-legacy --resume';
        $functions = self::usableFunctions(self::disabledFunctions((string) ini_get('disable_functions')));
        if ($functions === []) {
            return ['available' => false, 'reason' => 'this server does not allow PHP to start programs (proc_open and exec are disabled)', 'php' => null, 'command' => $manual];
        }

        foreach (self::phpCandidates() as $php) {
            $probe = self::run([$php, 'artisan', 'epesi:migrate-legacy', '--status'], 40);
            if ($probe['exit'] === 0) {
                return ['available' => true, 'reason' => '', 'php' => $php, 'command' => self::quote($php).' artisan epesi:migrate-legacy --resume'];
            }
        }

        return ['available' => false, 'reason' => 'no PHP command-line program that runs this application was found on the server', 'php' => null, 'command' => $manual];
    }

    /**
     * Start the background process now. Returns false when it could not be started (the page then steps itself).
     */
    public static function start(): bool
    {
        $capability = self::capability();
        if (! $capability['available'] || ! is_string($capability['php'])) {
            return false;
        }
        $windows = DIRECTORY_SEPARATOR === '\\';
        $command = self::buildCommand($capability['php'], base_path(), self::logPath(), $windows);
        try {
            if (in_array('proc_open', self::usableFunctions(self::disabledFunctions((string) ini_get('disable_functions'))), true)) {
                $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    return false;
                }
                foreach ($pipes as $pipe) {
                    @fclose($pipe);
                }
                // The detaching shell has already returned (it only launched the real process); not closing a
                // still-running child would leave it attached, so wait for the shell and nothing else.
                @proc_close($process);
                self::remember();

                return true;
            }
            @exec($command);
            self::remember();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<string> the functions of proc_open / exec that are usable on this server */
    public static function usableFunctions(array $disabled): array
    {
        return array_values(array_filter(['proc_open', 'exec'], static fn (string $name): bool => function_exists($name) && ! in_array($name, $disabled, true)));
    }

    /** @return list<string> */
    public static function disabledFunctions(string $ini): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $ini)), static fn (string $name): bool => $name !== ''));
    }

    /**
     * The shell line that starts the driver detached, appending its output to the log. Never contains a secret.
     */
    public static function buildCommand(string $php, string $base, string $log, bool $windows): string
    {
        if ($windows) {
            return 'cmd /c start "" /B '.self::quote($php, true).' artisan epesi:migrate-legacy --resume >> '.self::quote($log, true).' 2>&1';
        }
        $run = self::quote($php, false).' artisan epesi:migrate-legacy --resume';

        return 'cd '.escapeshellarg($base).' && nohup '.$run.' >> '.escapeshellarg($log).' 2>&1 &';
    }

    /**
     * PHP command-line programs to try, most likely first: an explicit setting, the one next to the running PHP,
     * then those on the PATH. (The web PHP is often php-cgi or php-fpm, which cannot run artisan.)
     *
     * @return list<string>
     */
    public static function phpCandidates(): array
    {
        $names = ['php', 'php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION, 'php'.PHP_MAJOR_VERSION];
        $candidates = [];
        $setting = getenv('MIGRATION_WIZARD_PHP');
        if (is_string($setting) && $setting !== '') {
            $candidates[] = $setting;
        }
        $suffix = DIRECTORY_SEPARATOR === '\\' ? '.exe' : '';
        foreach ($names as $name) {
            if (is_file(PHP_BINDIR.DIRECTORY_SEPARATOR.$name.$suffix)) {
                $candidates[] = PHP_BINDIR.DIRECTORY_SEPARATOR.$name.$suffix;
            }
        }
        if (in_array(strtolower(pathinfo(PHP_BINARY, PATHINFO_FILENAME)), ['php', 'php-cli'], true)) {
            $candidates[] = PHP_BINARY;
        }
        foreach ($names as $name) {
            $candidates[] = $name;
        }

        return array_values(array_unique($candidates));
    }

    private static function quote(string $argument, ?bool $windows = null): string
    {
        return ($windows ?? DIRECTORY_SEPARATOR === '\\') ? '"'.str_replace('"', '', $argument).'"' : escapeshellarg($argument);
    }

    /**
     * Run a short program and wait for it (at most $seconds). Used only for the capability test.
     *
     * @param  list<string>  $argv
     * @return array{exit: int|null, output: string}
     */
    private static function run(array $argv, int $seconds): array
    {
        if (! in_array('proc_open', self::usableFunctions(self::disabledFunctions((string) ini_get('disable_functions'))), true)) {
            $output = [];
            $code = 1;
            @exec(implode(' ', array_map(self::quote(...), $argv)).' 2>&1', $output, $code);

            return ['exit' => $code, 'output' => implode("\n", $output)];
        }
        try {
            $process = @proc_open($argv, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) {
                return ['exit' => null, 'output' => ''];
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $output = '';
            $deadline = time() + $seconds;
            while (true) {
                $output .= (string) stream_get_contents($pipes[1]).(string) stream_get_contents($pipes[2]);
                $status = proc_get_status($process);
                if (! $status['running']) {
                    $exit = $status['exitcode'];
                    break;
                }
                if (time() > $deadline) {
                    proc_terminate($process);
                    $exit = null;
                    break;
                }
                usleep(100_000);
            }
            $output .= (string) stream_get_contents($pipes[1]).(string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return ['exit' => $exit, 'output' => $output];
        } catch (Throwable) {
            return ['exit' => null, 'output' => ''];
        }
    }
}
