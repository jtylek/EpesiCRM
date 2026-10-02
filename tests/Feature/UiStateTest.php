<?php

namespace Tests\Feature;

use App\Support\UiState;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Lists' filters and the last page outlive the session (App\Support\UiState).
 */
class UiStateTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userWithRole('super_admin', ['email' => 'admin@example.com']);

        Filament::setCurrentPanel('main');
    }

    public function test_list_state_is_saved_and_restored_into_a_new_session(): void
    {
        $user = $this->userWithRole('employee');

        session()->put('tables', ['abc_filters' => ['status' => ['value' => 'open']], 'abc_sort' => 'name:asc']);
        UiState::save($user);

        session()->flush();
        UiState::restore($user);

        $this->assertSame('open', session('tables.abc_filters.status.value'));
        $this->assertSame('name:asc', session('tables.abc_sort'));
        $this->assertTrue(session()->has('my_records_default:tables.abc_filters'));
    }

    public function test_screen_choices_are_saved_and_restored_into_a_new_session(): void
    {
        $user = $this->userWithRole('employee');

        UiState::remember('dashboard.tab', 7);
        UiState::remember('mailbox.folder.3', 'INBOX.Sent');
        UiState::save($user);

        session()->flush();
        $this->assertNull(UiState::recall('dashboard.tab'));

        UiState::restore($user);

        $this->assertSame(7, UiState::recall('dashboard.tab'));
        $this->assertSame('INBOX.Sent', UiState::recall('mailbox.folder.3'));
    }

    public function test_a_page_load_records_the_last_url_and_login_returns_there(): void
    {
        $user = $this->userWithRole('employee');

        $this->actingAs($user)->get('/')->assertOk();

        $this->assertSame(url('/'), UiState::lastUrl($user));

        UiState::saveLastUrl($user, url('/contacts?x=1'));
        auth()->login($user);

        $this->assertSame(
            url('/contacts?x=1'),
            app(LoginResponse::class)->toResponse(request())->getTargetUrl(),
        );
    }
}
