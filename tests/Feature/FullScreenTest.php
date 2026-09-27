<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * epesi without the address bar: the top bar's full-screen button, which
 * lasts across clicks because the main panel runs in SPA mode (a browser
 * leaves full screen on every page load), and the web app manifest, which
 * lets the browser install epesi as an app in a window of its own.
 */
class FullScreenTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // With no users yet, every page goes to the setup wizard.
        $this->userWithRole('super_admin', ['email' => 'admin@example.com']);

        Filament::setCurrentPanel('main');
    }

    public function test_the_top_bar_has_a_full_screen_button(): void
    {
        $this->actingAs($this->userWithRole('employee'))
            ->get('/')
            ->assertOk()
            ->assertSee('document.documentElement.requestFullscreen()', false)
            ->assertSee('Exit full screen');
    }

    public function test_links_stay_in_the_page_except_into_another_panel(): void
    {
        $this->actingAs($this->userWithRole('employee'))->get('/')->assertOk();

        $this->assertTrue(FilamentView::hasSpaMode(url('/')));
        $this->assertTrue(FilamentView::hasSpaMode(url('calendar')));

        // Their styles differ from the main panel's, and SPA mode keeps
        // every stylesheet it has loaded.
        $this->assertFalse(FilamentView::hasSpaMode(url('administration')));
        $this->assertFalse(FilamentView::hasSpaMode(url('user-settings/regional-settings')));
        $this->assertFalse(FilamentView::hasSpaMode(url('setup/install')));
    }

    public function test_epesi_can_be_installed_as_an_app(): void
    {
        $this->get(route('filament.main.auth.login'))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="'.route('web-app.manifest').'">', false);

        $manifest = $this->get(route('web-app.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            // No session for a file the browser fetches on every page.
            ->assertCookieMissing(config('session.cookie'))
            ->json();

        $this->assertSame('standalone', $manifest['display']);

        // What Chrome asks of an installable app.
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], array_column($manifest['icons'], 'sizes'));

        foreach ($manifest['icons'] as $icon) {
            [$width, $height] = getimagesize(public_path(Str::after($icon['src'], asset(''))));

            $this->assertSame($icon['sizes'], "{$width}x{$height}", $icon['src']);
        }
    }
}
