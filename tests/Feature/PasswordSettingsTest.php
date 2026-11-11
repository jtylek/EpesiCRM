<?php

namespace Tests\Feature;

use App\Filament\UserSettings\Pages\PasswordSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/** User Settings → Password: any user changes their own password. */
class PasswordSettingsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_an_employee_can_change_their_own_password(): void
    {
        Filament::setCurrentPanel('user-settings');
        $user = $this->userWithRole('employee', ['password' => Hash::make('old-password')]);
        $this->actingAs($user);

        Livewire::test(PasswordSettings::class)
            ->fillForm(['current_password' => 'old-password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_user_settings_opens_on_about_for_an_employee(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $this->followingRedirects()
            ->get('/user-settings')
            ->assertOk()
            ->assertSee('Business Information Manager')
            ->assertSee('MIT License')
            ->assertDontSee('Register your Epesi');
    }

    public function test_the_current_password_must_be_right(): void
    {
        Filament::setCurrentPanel('user-settings');
        $user = $this->userWithRole('employee', ['password' => Hash::make('old-password')]);
        $this->actingAs($user);

        Livewire::test(PasswordSettings::class)
            ->fillForm(['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
