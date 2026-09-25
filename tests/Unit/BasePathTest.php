<?php

namespace Tests\Unit;

use App\Support\Setup\BasePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How each way of serving epesi reaches public/index.php, and what Laravel
 * must be told: SCRIPT_NAME is what it works its base path out from.
 */
class BasePathTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function servers(): array
    {
        return [
            // Unpacked into public_html/crm; the top-level .htaccess rewrote
            // /crm/login into public/.
            'folder, through the root .htaccess' => [['SCRIPT_NAME' => '/crm/public/index.php', 'REQUEST_URI' => '/crm/login'], '/crm/index.php', '/crm/login'],
            'folder, its bare address' => [['SCRIPT_NAME' => '/crm/public/index.php', 'REQUEST_URI' => '/crm/'], '/crm/index.php', '/crm/'],
            'folder, with a query' => [['SCRIPT_NAME' => '/crm/public/index.php', 'REQUEST_URI' => '/crm/setup/install?x=1'], '/crm/index.php', '/crm/setup/install?x=1'],
            'folder, a route starting with "public"' => [['SCRIPT_NAME' => '/crm/public/index.php', 'REQUEST_URI' => '/crm/public-holidays'], '/crm/index.php', '/crm/public-holidays'],
            'nested folders' => [['SCRIPT_NAME' => '/apps/crm/public/index.php', 'REQUEST_URI' => '/apps/crm/contacts/5'], '/apps/crm/index.php', '/apps/crm/contacts/5'],
            // Unpacked straight into public_html.
            'site root, through the root .htaccess' => [['SCRIPT_NAME' => '/public/index.php', 'REQUEST_URI' => '/login'], '/index.php', '/login'],
            // The old way in, or a bookmark from before: still works as is.
            'an address with /public in it' => [['SCRIPT_NAME' => '/crm/public/index.php', 'REQUEST_URI' => '/crm/public/login'], '/crm/public/index.php', '/crm/public/login'],
            // Document root is public/ itself, php artisan serve, nginx.
            'document root is public/' => [['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/login'], '/index.php', '/login'],
            // XAMPP development vhost rewriting /epesi-laravel to public/.
            'LARAVEL_BASE_PATH' => [['SCRIPT_NAME' => '/epesi-laravel/public/index.php', 'REQUEST_URI' => '/epesi-laravel/login', 'LARAVEL_BASE_PATH' => '/epesi-laravel'], '/epesi-laravel/index.php', '/epesi-laravel/login'],
            'LARAVEL_BASE_PATH, its bare address' => [['SCRIPT_NAME' => '/epesi-laravel/public/index.php', 'REQUEST_URI' => '/epesi-laravel', 'LARAVEL_BASE_PATH' => '/epesi-laravel'], '/epesi-laravel/index.php', '/epesi-laravel/'],
            // Set server-wide, it must not reach another copy under htdocs.
            'LARAVEL_BASE_PATH for another copy' => [['SCRIPT_NAME' => '/other/public/index.php', 'REQUEST_URI' => '/other/public/login', 'LARAVEL_BASE_PATH' => '/epesi-laravel'], '/other/public/index.php', '/other/public/login'],
        ];
    }

    /**
     * @param  array<string, string>  $server
     */
    #[DataProvider('servers')]
    public function test_it_lines_the_script_up_with_the_address(array $server, string $script, string $uri): void
    {
        $fixed = BasePath::fix($server);

        $this->assertSame($script, $fixed['SCRIPT_NAME']);
        $this->assertSame($uri, $fixed['REQUEST_URI']);
    }
}
