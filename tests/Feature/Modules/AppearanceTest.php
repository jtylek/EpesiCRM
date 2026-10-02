<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Pages\CreateTheme;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\ThemeResource;
use Epesi\Modules\Appearance\Filament\Pages\Appearance;
use Epesi\Modules\Appearance\Models\Theme;
use Epesi\Modules\Appearance\Models\UserAppearance;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
