<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The panel is mounted at the site root, so `/` is the dashboard: guests are
 * sent to the login page, signed-in users get the dashboard.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        // An installed system: an empty users table would mean the setup
        // wizard instead (see SetupTest).
        User::factory()->create();

        $this->get('/')->assertRedirect(route('filament.main.auth.login'));
    }

    public function test_signed_in_users_get_the_dashboard(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $this->get('/')->assertOk()->assertSee('Dashboard');
    }
}
