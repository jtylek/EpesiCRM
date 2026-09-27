<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Google Analytics on the demo (App\Filament\Support\DemoAnalytics): only on
 * a demo with an ID for it, and no analytics cookies (or tracking) before
 * the visitor accepts.
 */
class DemoAnalyticsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // With no users yet, every page goes to the setup wizard.
        $this->userWithRole('super_admin', ['email' => 'admin@example.com']);

        config(['demo.enabled' => true, 'demo.analytics_id' => 'G-TEST123']);
        Filament::setCurrentPanel('main');
    }

    public function test_the_demo_only_tracks_after_consent(): void
    {
        $this->get(route('filament.main.auth.login'))
            ->assertOk()
            ->assertSee("gtag('consent', 'default'", false)
            ->assertSee("analytics_storage: epesiAnalytics.choice() === 'granted' ? 'granted' : 'denied'", false)
            ->assertSee("gtag('config', 'G-TEST123')", false)
            ->assertSee("localStorage.getItem('epesi-analytics')", false)
            ->assertSee('Cookie settings')
            ->assertSee('No cookies are set and no visits are tracked unless you accept.')
            // Google's own gtag.js is only assembled client-side (document.createElement),
            // never written as a literal <script src> tag in the response.
            ->assertDontSee('src="https://www.googletagmanager.com', false);
    }

    public function test_the_demo_account_goes_with_the_visit(): void
    {
        $employee = $this->userWithRole('employee', ['email' => 'employee@example.com']);

        $this->actingAs($employee)
            ->get('/')
            ->assertOk()
            ->assertSee("demo_account: 'Employee'", false);
    }

    public function test_no_analytics_without_an_id(): void
    {
        config(['demo.analytics_id' => '']);

        $this->get(route('filament.main.auth.login'))
            ->assertOk()
            ->assertDontSee('epesiAnalytics', false)
            ->assertDontSee('Cookie settings');
    }

    public function test_no_analytics_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);

        $this->get(route('filament.main.auth.login'))
            ->assertOk()
            ->assertDontSee('G-TEST123', false)
            ->assertDontSee('Cookie settings');
    }
}
