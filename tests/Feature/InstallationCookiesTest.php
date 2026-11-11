<?php

namespace Tests\Feature;

use App\Auth\InstallationSessionGuard;
use Tests\TestCase;

class InstallationCookiesTest extends TestCase
{
    public function test_the_web_guard_names_its_remember_cookie_after_this_installation(): void
    {
        $guard = auth()->guard('web');

        $this->assertInstanceOf(InstallationSessionGuard::class, $guard);

        config(['session.cookie' => 'epesi-session-aaaaaaaa']);
        $first = $guard->getRecallerName();

        config(['session.cookie' => 'epesi-session-bbbbbbbb']);

        $this->assertNotSame($first, $guard->getRecallerName());
    }

    public function test_the_session_cookie_name_carries_a_suffix_from_the_installation_url(): void
    {
        $this->assertMatchesRegularExpression('/-session-[0-9a-f]{8}$/', (string) config('session.cookie'));
    }
}
