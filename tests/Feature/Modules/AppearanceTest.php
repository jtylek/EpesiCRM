<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Support\Appearance\AppName;
use Epesi\Modules\Appearance\Filament\Administration\Pages\LogoAndTitle;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Pages\CreateTheme;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\ThemeResource;
use Epesi\Modules\Appearance\Filament\Pages\Appearance;
use Epesi\Modules\Appearance\Models\AppearanceSetting;
use Epesi\Modules\Appearance\Models\Theme;
use Epesi\Modules\Appearance\Models\UserAppearance;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Admin-authored, named themes (Administration → Themes) a user picks from
 * on Appearance (user-settings) — not a free per-user preference. See
 * AI-shared/Epesi-custom-themes.md.
 */
class AppearanceTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_seeded_epesi_green_theme_uses_small_font_size(): void
    {
        $this->assertSame(
            Theme::FONT_SIZE_SMALL,
            Theme::query()->where('name', 'Epesi Green')->value('font_size'),
        );
    }

    public function test_the_three_titles_default_and_are_set_on_the_logo_and_title_page(): void
    {
        $this->assertSame('epesi', AppName::current());
        $this->assertSame('epesi', AppName::login());
        $this->assertSame('Customer Portal', AppName::portal());

        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(LogoAndTitle::class)
            ->assertSet('appName', 'epesi')
            ->assertSet('loginTitle', 'epesi')
            ->assertSet('portalTitle', 'Customer Portal')
            ->set('appName', 'Acme CRM')
            ->set('loginTitle', 'Acme sign in')
            ->set('portalTitle', 'Acme clients')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Acme CRM', AppName::current());
        $this->assertSame('Acme sign in', AppName::login());
        $this->assertSame('Acme clients', AppName::portal());
        $this->assertSame('Acme sign in', AppearanceSetting::loginTitle());
    }

    public function test_a_title_cannot_be_empty(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(LogoAndTitle::class)
            ->set('loginTitle', '')
            ->call('save')
            ->assertHasErrors(['loginTitle']);
    }

    public function test_login_pages_use_the_login_title_and_the_portal_its_own(): void
    {
        AppearanceSetting::current()->update(['login_title' => 'Acme sign in', 'portal_title' => 'Acme clients']);
        // An installed epesi has users; without one the login pages send to setup.
        User::factory()->create();

        $this->get('/login')->assertSee('Acme sign in');
        $this->get('/administration/login')->assertSee('Acme sign in');
        $this->get('/portal/login')->assertSee('Acme clients')->assertDontSee('Acme sign in');
    }

    public function test_the_login_and_portal_logos_are_uploaded_served_and_removed_separately(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('super_admin');

        // The login pages are for someone not signed in.
        $this->get('/login')->assertDontSee('branding/login');
        $this->get('/branding/login')->assertNotFound();

        $this->actingAs($admin);
        Filament::setCurrentPanel('administration');

        Livewire::test(LogoAndTitle::class)
            ->set('logos.login', UploadedFile::fake()->image('login.png', 300, 100))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(AppearanceSetting::logoUrl('login'));
        $this->assertNull(AppearanceSetting::logoUrl('portal'));

        auth()->logout();
        $this->get('/login')->assertSee('branding/login');
        $this->get('/portal/login')->assertDontSee('branding/login')->assertDontSee('branding/portal');
        $this->get('/branding/login')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // The requests above left another panel current, and nobody signed in.
        $this->actingAs($admin);
        Filament::setCurrentPanel('administration');

        Livewire::test(LogoAndTitle::class)
            ->set('logos.portal', UploadedFile::fake()->image('portal.png', 300, 100))
            ->call('save')
            ->call('removeLogo', 'login');

        $this->assertNull(AppearanceSetting::logoUrl('login'));
        auth()->logout();
        $this->get('/portal/login')->assertSee('branding/portal');
    }

    public function test_a_logo_must_be_an_image(): void
    {
        Storage::fake('local');
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(LogoAndTitle::class)
            ->set('logos.login', UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['logos.login']);
    }

    public function test_a_dark_mode_logo_is_separate_and_a_missing_one_falls_back_to_the_other_mode(): void
    {
        Storage::fake('local');
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(LogoAndTitle::class)
            ->set('logos.login', UploadedFile::fake()->image('light.png', 300, 100))
            ->call('save');

        $urls = AppearanceSetting::logoUrls('login');
        $this->assertNotNull($urls['light']);
        $this->assertSame($urls['light'], $urls['dark']);

        Livewire::test(LogoAndTitle::class)
            ->set('logos.login-dark', UploadedFile::fake()->image('dark.png', 300, 100))
            ->call('save');

        $urls = AppearanceSetting::logoUrls('login');
        $this->assertNotSame($urls['light'], $urls['dark']);
        $this->assertStringContainsString('branding/login-dark', $urls['dark']);

        auth()->logout();
        $this->get('/branding/login-dark')->assertOk();
        $this->get('/login')->assertSee('branding/login-dark')->assertSee('branding/login?');
        $this->get('/branding/login-sideways')->assertNotFound();
    }

    public function test_reset_to_default_puts_back_the_epesi_titles_and_logos(): void
    {
        Storage::fake('local');
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(LogoAndTitle::class)
            ->set('appName', 'Acme CRM')
            ->set('loginTitle', 'Acme sign in')
            ->set('portalTitle', 'Acme clients')
            ->set('logos.login', UploadedFile::fake()->image('a.png', 300, 100))
            ->set('logos.portal-dark', UploadedFile::fake()->image('b.png', 300, 100))
            ->call('save');

        $paths = array_filter(array_map(AppearanceSetting::logoPath(...), array_keys(AppearanceSetting::LOGO_COLUMNS)));
        $this->assertCount(2, $paths);

        Livewire::test(LogoAndTitle::class)
            ->call('resetToDefaults')
            ->assertSet('appName', 'epesi')
            ->assertSet('loginTitle', 'epesi')
            ->assertSet('portalTitle', 'Customer Portal');

        $this->assertSame('epesi', AppName::current());
        $this->assertSame('Customer Portal', AppName::portal());
        $this->assertNull(AppearanceSetting::logoUrl('login'));
        $this->assertNull(AppearanceSetting::logoUrl('portal-dark'));

        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_an_administrator_previews_the_login_pages_in_either_colour_mode(): void
    {
        $admin = $this->userWithRole('super_admin');

        // Nobody else gets the preview: a guest just sees the ordinary login page.
        $this->get('/login?preview=dark')->assertOk()->assertDontSee('Storage.prototype');

        // Signed in, the page opens (no redirect away) and answers "dark" for the colour mode.
        $this->actingAs($admin)->get('/login?preview=dark')->assertOk()->assertSee('Storage.prototype', false)->assertSee('"dark"', false);
        $this->get('/portal/login?preview=light')->assertOk()->assertSee('"light"', false);

        // Without the parameter a signed-in user is sent on, as ever.
        $this->get('/login')->assertRedirect();
    }

    public function test_the_logo_and_title_page_sits_with_themes_under_appearance(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        $this->get('/administration/logo-and-title')->assertOk()->assertSee('Logo');
        $this->assertSame('Appearance', LogoAndTitle::getNavigationGroup());
        $this->assertSame('Appearance', ThemeResource::getNavigationGroup());
    }

    public function test_resolves_the_users_own_choice_then_the_default_then_nothing(): void
    {
        // The seeded starter themes (2026_09_30_090100_seed_default_themes)
        // are the fresh-install baseline, not "no themes" — clear them to
        // exercise that edge case on its own.
        Theme::query()->delete();

        $user = $this->userWithRole('employee');
        $this->assertNull(Theme::resolveFor($user));

        $default = Theme::create(['name' => 'Default', 'is_default' => true]);
        $this->assertSame($default->id, Theme::resolveFor($user)->id);

        $chosen = Theme::create(['name' => 'Midnight', 'density' => Theme::DENSITY_COMFORTABLE]);
        UserAppearance::choose($user, $chosen->id);

        $this->assertSame($chosen->id, Theme::resolveFor($user)->id);
    }

    public function test_saving_a_theme_as_default_unsets_the_previous_one(): void
    {
        $a = Theme::create(['name' => 'A', 'is_default' => true]);
        $b = Theme::create(['name' => 'B', 'is_default' => true]);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
    }

    public function test_only_a_super_admin_reaches_administration_themes(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $this->get(ThemeResource::getUrl('index', panel: 'administration'))->assertForbidden();
    }

    public function test_a_super_admin_creates_a_theme(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(CreateTheme::class)
            ->fillForm([
                'name' => 'Midnight',
                'accent_color' => '#1d4ed8',
                'density' => Theme::DENSITY_COMFORTABLE,
                'font_size' => Theme::FONT_SIZE_LARGE,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            // Not just that create() didn't error: Save redirects straight to
            // View, and it isn't the same request/render — a follow-up GET
            // is what actually caught the missing morph alias below.
            ->assertRedirect();

        $this->assertDatabaseHas('epesi_appearance_themes', [
            'name' => 'Midnight',
            'accent_color' => '#1d4ed8',
            'density' => 'comfortable',
            'font_size' => 'large',
        ]);
    }

    /**
     * Every resource built on RecordBrowser's shared ViewRecord tries to
     * attach every registered RecordExtensions addon (here, PriorityList) to
     * whatever record it's showing, whether or not that resource actually
     * uses the addon — which touches a polymorphic relation keyed by the
     * model, throwing if it has no morph alias
     * (AppServiceProvider::registerMorphAliases()'s docblock; Theme's own is
     * in AppearanceServiceProvider::register()).
     */
    public function test_viewing_a_theme_works(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        $theme = Theme::create(['name' => 'Midnight', 'accent_color' => '#1d4ed8', 'is_default' => true]);

        $this->get(ThemeResource::getUrl('view', ['record' => $theme], panel: 'administration'))
            ->assertOk()
            ->assertSee('Midnight');
    }

    public function test_a_new_theme_starts_as_a_copy_of_the_default_theme(): void
    {
        Theme::create(['name' => 'Epesi Default', 'accent_color' => '#f59e0b', 'density' => Theme::DENSITY_COMPACT, 'font_size' => Theme::FONT_SIZE_LARGE, 'is_default' => true]);

        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(CreateTheme::class)
            ->assertFormSet(['accent_color' => '#f59e0b', 'density' => Theme::DENSITY_COMPACT, 'font_size' => Theme::FONT_SIZE_LARGE]);
    }

    public function test_a_theme_needs_a_name(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(CreateTheme::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_the_appearance_page_shows_a_message_until_a_theme_exists(): void
    {
        Theme::query()->delete();

        $this->actingAs($this->userWithRole('employee'));

        $this->get(Appearance::getUrl(panel: 'user-settings'))
            ->assertOk()
            ->assertSee("hasn't set up any themes yet");
    }

    public function test_a_user_picks_a_theme_on_the_appearance_page(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        Filament::setCurrentPanel('user-settings');

        $theme = Theme::create(['name' => 'Midnight']);

        Livewire::test(Appearance::class)
            ->fillForm(['theme_id' => $theme->id])
            ->call('save');

        $this->assertSame($theme->id, UserAppearance::themeIdFor($user));
    }

    public function test_compact_is_the_default_when_no_theme_is_in_effect(): void
    {
        Theme::query()->delete();

        $this->actingAs($this->userWithRole('employee'));

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="epesi-density" content="compact">', false);
    }

    public function test_a_comfortable_default_theme_leaves_off_the_compact_class(): void
    {
        Theme::create(['name' => 'Comfy', 'density' => Theme::DENSITY_COMFORTABLE, 'is_default' => true]);
        $this->actingAs($this->userWithRole('employee'));

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="epesi-density" content="comfortable">', false);
    }

    public function test_the_administration_panel_is_always_compact(): void
    {
        Theme::create(['name' => 'Comfy', 'density' => Theme::DENSITY_COMFORTABLE, 'is_default' => true]);
        $this->actingAs($this->userWithRole('super_admin'));

        $this->get('/administration/about')
            ->assertOk()
            ->assertSee('<meta name="epesi-density" content="compact">', false);
    }

    public function test_default_font_size_is_the_default_when_no_theme_is_in_effect(): void
    {
        Theme::query()->delete();

        $this->actingAs($this->userWithRole('employee'));

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="epesi-font-size" content="default">', false);
    }

    public function test_a_large_font_size_theme_emits_the_large_meta_tag(): void
    {
        Theme::create(['name' => 'Big Text', 'font_size' => Theme::FONT_SIZE_LARGE, 'is_default' => true]);
        $this->actingAs($this->userWithRole('employee'));

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="epesi-font-size" content="large">', false);
    }
}
