<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Setup\BasePath;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * epesi unpacked into a web folder (public_html/crm) and opened at
 * example.com/crm/: the top-level .htaccess rewrites into public/, and Apache
 * hands public/index.php SCRIPT_NAME=/crm/public/index.php with the address
 * /crm/login (checked against a real Apache). These requests are made with
 * exactly those values, as public/index.php passes them through BasePath.
 */
class ServedFromFolderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A request exactly as Apache hands it to public/index.php, passed
     * through BasePath as index.php does. Built by hand: the test client's
     * call() trims a trailing slash and rebuilds REQUEST_URI from the URL.
     *
     * @param  bool  $fix  false: as index.php would see it without BasePath
     */
    protected function serve(string $uri, bool $fix = true): TestResponse
    {
        $server = [
            'SCRIPT_NAME' => '/crm/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF' => '/crm/public/index.php',
            'REQUEST_URI' => $uri,
        ];
        $server = $fix ? BasePath::fix($server) : $server;

        $request = Request::create('http://example.com'.$uri, 'GET', server: $server);
        $request->server->set('REQUEST_URI', $server['REQUEST_URI']);

        return TestResponse::fromBaseResponse($this->app->make(Kernel::class)->handle($request));
    }

    public function test_epesi_answers_at_the_folders_own_address(): void
    {
        User::factory()->create();

        $this->serve('/crm/login')
            ->assertOk()
            ->assertSee('http://example.com/crm/css/filament/', false)
            ->assertDontSee('/crm/public/', false);
    }

    public function test_the_folders_bare_address_opens_epesi(): void
    {
        User::factory()->create();

        $this->serve('/crm/')->assertRedirect('http://example.com/crm/login');
    }

    public function test_without_lining_up_the_base_path_every_page_would_404(): void
    {
        User::factory()->create();

        $this->serve('/crm/login', fix: false)->assertNotFound();
    }

    public function test_files_outside_public_are_not_served(): void
    {
        // /crm/.env reaches public/index.php (there is no public/.env), and
        // epesi has no such page.
        User::factory()->create();

        $this->serve('/crm/.env')->assertNotFound();
        $this->serve('/crm/vendor/autoload.php')->assertNotFound();
    }
}
