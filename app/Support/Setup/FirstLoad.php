<?php

namespace App\Support\Setup;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\View\Compilers\BladeCompiler;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * The first page after a release zip is unpacked takes many seconds and looks
 * like a dead site. So the first page load gets a plain notice at once, sent
 * before Laravel boots, whose script has the templates compiled in short
 * batches and then reloads the address.
 *
 * Runs once per release (bootstrap/release-id against a marker in storage), so
 * a git checkout, which has no release id, never sees it. Only page loads get
 * the notice: Livewire, cron and API requests pass through. Nothing here may
 * lock anyone out: when compiling fails the script asks to give up, which needs
 * no Laravel, and the application then starts (and reports) as usual.
 */
class FirstLoad
{
    public const PASS = 'pass';

    public const NOTICE = 'notice';

    public const COMPILE = 'compile';

    public const GIVE_UP = 'give-up';

    /** Seconds of compiling per request: well inside any host's PHP time limit. */
    private const BUDGET = 5.0;

    /** @param  array<string, mixed>  $server  $_SERVER */
    public function __construct(private string $basePath, private array $server) {}

    public function decide(): string
    {
        if (! $this->pending()) {
            return self::PASS;
        }
        $method = (string) ($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST') {
            $action = $this->server['HTTP_X_EPESI_PREPARE'] ?? null;

            return in_array($action, [self::COMPILE, self::GIVE_UP], true) ? $action : self::PASS;
        }

        return $method === 'GET' && $this->isPageLoad() ? self::NOTICE : self::PASS;
    }

    /** Before Laravel: answer the requests that don't need it. */
    public function gate(): void
    {
        $decision = $this->decide();
        if ($decision === self::NOTICE) {
            http_response_code(503);
            header('Retry-After: 5');
            header('Cache-Control: no-store');
            header('Content-Type: text/html; charset=utf-8');
            echo self::noticeHtml();
            exit;
        }
        if ($decision === self::GIVE_UP) {
            $this->markPrepared();
            $this->json(['done' => true]);
        }
    }

    public function wantsCompile(): bool
    {
        return $this->decide() === self::COMPILE;
    }

    /** One batch of compiling, in a booted application; answers the notice's script. */
    public function compile(Application $app): never
    {
        @set_time_limit(60);
        $lock = fopen($this->basePath.'/storage/framework/epesi-first-load.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->json(['done' => false, 'busy' => true]);
        }

        $app->instance('request', Request::capture());
        $app->make(Kernel::class)->bootstrap();

        $view = $app->make('view');
        $done = self::compileBatch(
            $view->getEngineResolver()->resolve('blade')->getCompiler(),
            self::templates($view),
            self::BUDGET,
        );
        if ($done) {
            $this->markPrepared();
        }
        $this->json(['done' => $done, 'busy' => false]);
    }

    /**
     * Compile what isn't compiled yet, until the time is used up. Stateless: the next batch skips what this one
     * wrote. A template that can't compile is left for the request that needs it to report.
     *
     * @param  iterable<string>  $paths
     * @return bool whether every template was reached
     */
    public static function compileBatch(BladeCompiler $compiler, iterable $paths, float $budget): bool
    {
        $deadline = microtime(true) + $budget;
        foreach ($paths as $path) {
            if (! self::needsCompiling($compiler, $path)) {
                continue;
            }
            if (microtime(true) >= $deadline) {
                return false;
            }
            try {
                $compiler->compile($path);
                // compile() leaves an identical file as it is, which would stay older than a re-unpacked source.
                touch($compiler->getCompiledPath($path));
            } catch (Throwable) {
            }
        }

        return true;
    }

    /**
     * Not BladeCompiler::isExpired(): a source dated in the future (clock or time zone differences) would stay
     * expired after every compile, and the batches might never end.
     */
    private static function needsCompiling(BladeCompiler $compiler, string $path): bool
    {
        $compiled = $compiler->getCompiledPath($path);
        clearstatcache(true, $compiled);
        if (! is_file($compiled)) {
            return true;
        }
        $source = is_file($path) ? filemtime($path) : 0;

        return $source > filemtime($compiled) && $source <= time();
    }

    /**
     * Every template, spelled the way FileViewFinder spells it at runtime (folder, a slash, the relative name).
     * The compiled file is named by a hash of that path, so view:cache's real paths would not always be found.
     *
     * @return iterable<string>
     */
    public static function templates(object $view): iterable
    {
        $finder = $view->getFinder();
        $folders = array_unique([...$finder->getPaths(), ...array_merge([], ...array_values($finder->getHints()))]);
        $names = array_map(
            static fn (string $extension): string => '*.'.$extension,
            array_keys(array_filter($view->getExtensions(), static fn (string $engine): bool => $engine === 'blade')),
        );
        foreach ($folders as $folder) {
            if (! is_dir($folder)) {
                continue;
            }
            foreach (Finder::create()->in($folder)->exclude('vendor')->name($names)->files() as $file) {
                yield $folder.'/'.str_replace('\\', '/', $file->getRelativePathname());
            }
        }
    }

    public static function noticeHtml(): string
    {
        return <<<'HTML'
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex">
            <title>Preparing Epesi</title>
            <style>
            :root{color-scheme:light dark}
            body{margin:0;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;background:#f4f7fb;color:#142033;font:18px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;text-align:center;padding:16px;box-sizing:border-box}
            .spinner{width:44px;height:44px;border:4px solid #dce4ee;border-top-color:#0b9f5a;border-radius:50%;animation:spin 1s linear infinite}
            @keyframes spin{to{transform:rotate(360deg)}}
            @media (prefers-color-scheme:dark){body{background:#111827;color:#f3f4f6}.spinner{border-color:#374151;border-top-color:#0b9f5a}}
            </style>
            </head>
            <body>
            <div class="spinner" role="status" aria-hidden="true"></div>
            <div>Preparing Epesi - please wait...</div>
            <script>
            (function () {
                var failures = 0;
                function post(action) {
                    return fetch(location.href, {method: 'POST', credentials: 'same-origin', headers: {'X-Epesi-Prepare': action, 'Accept': 'application/json'}})
                        .then(function (response) { if (!response.ok) { throw new Error(); } return response.json(); });
                }
                function open() { location.reload(); }
                function step() {
                    post('compile').then(function (result) {
                        failures = 0;
                        if (result.done) { open(); } else { setTimeout(step, result.busy ? 1000 : 0); }
                    }).catch(function () {
                        if (++failures < 3) { setTimeout(step, 1000); } else { post('give-up').then(open, open); }
                    });
                }
                step();
            })();
            </script>
            </body>
            </html>
            HTML;
    }

    private function pending(): bool
    {
        $releaseId = $this->releaseId();
        if ($releaseId === '' || ! is_writable($this->basePath.'/storage/framework')) {
            return false;
        }

        return $this->read($this->marker()) !== $releaseId;
    }

    private function isPageLoad(): bool
    {
        foreach (['HTTP_X_LIVEWIRE', 'HTTP_X_LIVEWIRE_NAVIGATE', 'HTTP_X_REQUESTED_WITH'] as $header) {
            if (isset($this->server[$header])) {
                return false;
            }
        }
        $destination = $this->server['HTTP_SEC_FETCH_DEST'] ?? 'document';

        return $destination === 'document' && str_contains((string) ($this->server['HTTP_ACCEPT'] ?? ''), 'text/html');
    }

    private function releaseId(): string
    {
        return $this->read($this->basePath.'/bootstrap/release-id');
    }

    private function read(string $path): string
    {
        return is_file($path) ? trim((string) file_get_contents($path)) : '';
    }

    private function marker(): string
    {
        return $this->basePath.'/storage/framework/epesi-first-load';
    }

    private function markPrepared(): void
    {
        @file_put_contents($this->marker(), $this->releaseId());
    }

    /** @param  array<string, bool>  $body */
    private function json(array $body): never
    {
        header('Cache-Control: no-store');
        header('Content-Type: application/json');
        echo json_encode($body);
        exit;
    }
}
