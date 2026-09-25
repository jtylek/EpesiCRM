<?php

namespace App\Support\Setup;

/**
 * What a freshly unpacked copy needs before Laravel can serve its first page —
 * the part of Epesi's setup.php that ran before any database existed.
 *
 * Laravel can't encrypt a cookie without APP_KEY, and .env.example keeps
 * sessions and the cache in the database, which isn't configured yet. So on
 * the first web request, while .env is missing or has no key, this writes one
 * from .env.example with a new key and file-based sessions and cache. The
 * setup wizard then takes over and asks for the database in the browser.
 *
 * Runs from public/index.php, before the application boots, so it uses plain
 * PHP only. Anything that stops it is shown as a plain page of its own:
 * Laravel's error page couldn't render either.
 */
class FirstBoot
{
    public function __construct(protected string $basePath) {}

    public static function prepare(string $basePath): void
    {
        (new static($basePath))->run();
    }

    public function run(): void
    {
        $env = $this->basePath.'/.env';

        if (is_file($env) && preg_match('/^APP_KEY=\S/m', (string) file_get_contents($env))) {
            return;
        }

        $problems = $this->problems($env);

        if ($problems !== []) {
            $this->fail($problems);
        }

        $values = [
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
        ];

        // .env.example is set up for development. A copy started here is an
        // installation, often on a public server, where APP_DEBUG=true would
        // show configuration values on every error page. A developer's own
        // .env that only lacks a key keeps its settings.
        if (! is_file($env)) {
            copy($this->basePath.'/.env.example', $env);

            $values += ['APP_ENV' => 'production', 'APP_DEBUG' => 'false'];
        }

        (new EnvFile($env))->set($values);
    }

    /**
     * @return list<string>
     */
    public function problems(string $env): array
    {
        $problems = [];

        if (! is_file($env) && ! is_file($this->basePath.'/.env.example')) {
            $problems[] = 'The file .env.example is missing, so there is nothing to start .env from. Unpack the complete epesi package again.';
        }

        if (is_file($env) ? ! is_writable($env) : ! is_writable($this->basePath)) {
            $problems[] = 'epesi cannot write its settings file (.env) in '.$this->basePath.'. Let the web server write to that folder, or create .env there yourself.';
        }

        foreach (['storage', 'bootstrap/cache'] as $directory) {
            if (! is_writable($this->basePath.'/'.$directory)) {
                $problems[] = 'The folder '.$directory.'/ must be writable by the web server.';
            }
        }

        return $problems;
    }

    /**
     * @param  list<string>  $problems
     */
    protected function fail(array $problems): never
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        $items = implode('', array_map(fn (string $p): string => '<li>'.htmlspecialchars($p).'</li>', $problems));

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>epesi setup</title></head>'
            .'<body style="font-family:system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem;line-height:1.5">'
            .'<h1>epesi can\'t start its setup yet</h1><ul>'.$items.'</ul><p>Fix this and reload the page.</p></body></html>';

        exit(1);
    }
}
