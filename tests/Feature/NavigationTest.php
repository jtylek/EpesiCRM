<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\UserSettings\Pages\CalendarSettings;
use Collator;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_calendar_settings_navigation_is_limited_to_the_user_settings_panel(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        Filament::setCurrentPanel('main');
        $mainItems = collect(Filament::getNavigation())->flatMap(fn (NavigationGroup $group) => $group->getItems());

        $this->assertFalse($mainItems->contains(fn (NavigationItem $item): bool => str_ends_with((string) parse_url($item->getUrl(), PHP_URL_PATH), '/calendar-settings')));

        Filament::setCurrentPanel('user-settings');
        app()->forgetScopedInstances();
        $settingsItems = collect(Filament::getNavigation())->flatMap(fn (NavigationGroup $group) => $group->getItems());

        $this->assertTrue($settingsItems->contains(fn (NavigationItem $item): bool => $item->getUrl() === CalendarSettings::getUrl(panel: 'user-settings')));
    }

    public function test_the_sidebar_is_alphabetical_after_the_dashboard_in_every_language(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        foreach (['en', 'pl'] as $locale) {
            app()->setLocale($locale);
            app()->forgetScopedInstances();
            Filament::setCurrentPanel('main');

            $groups = array_values(array_map(
                fn (NavigationGroup $group): array => collect($group->getItems())->map(fn (NavigationItem $item): string => $item->getLabel())->values()->all(),
                Filament::getNavigation(),
            ));

            $this->assertSame(Dashboard::getNavigationLabel(), array_shift($groups[0]), $locale);

            foreach ($groups as $labels) {
                $sorted = $labels;
                (new Collator($locale))->sort($sorted);

                $this->assertSame($sorted, $labels, $locale);
            }
        }
    }
}
