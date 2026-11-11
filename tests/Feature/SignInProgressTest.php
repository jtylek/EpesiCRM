<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class SignInProgressTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_the_login_page_carries_the_notice_hidden_until_the_form_is_sent(): void
    {
        $this->userWithRole(); // else it is off to the setup wizard

        $this->get(route('filament.main.auth.login'))
            ->assertOk()
            ->assertSee('<div id="epesi-sign-in-progress" role="status" hidden>', false)
            ->assertSee('Preparing Epesi - please wait...');
    }
}
