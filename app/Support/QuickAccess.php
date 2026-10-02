<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Filament\Panel;

/**
 * "Quick Access": the icon links in the main panel's top bar, one per module
 * the user picked in User Settings → Quick Access (at most MAX).
 *
 * The choices are the main panel's own sidebar items, so a module installed
 * later is offered without anything being registered here. They're stored
 * per user (UiState) as the item's path inside the panel ("companies"), which
 * unlike a label survives a language change. Until the user chooses, the
 * CRM modules in DEFAULTS are shown.
 */
class QuickAccess
{
    public const MAX = 12;

    public const DASHBOARD = 'dashboard';

    /** @var list<string> */
    public const DEFAULTS = [
        self::DASHBOARD, 'calendar', 'companies', 'contacts', 'mails', 'mailbox',
        'meetings', 'attachments', 'phone-calls', 'tasks',
    ];

    private const STATE_KEY = 'quickaccess.items';

    /**
     * Every sidebar item of the main panel that has a page to go to, by key.
     *
     * @return array<string, NavigationItem>
     */
    public static function available(): array
    {
        $panel = Filament::getPanel('main');
        $current = Filament::getCurrentPanel();

        // The item URLs and the visibility checks are those of the current
        // panel, and this is also called from the User Settings panel.
        Filament::setCurrentPanel($panel);

        // The navigation manager is one object per request that keeps the menu
        // it built, whatever the panel: left holding the main panel's, the User
        // Settings sidebar would show it instead of its own.
        app()->forgetInstance(NavigationManager::class);

        try {
            $items = [];

            foreach ($panel->getNavigation() as $group) {
                foreach ($group->getItems() as $item) {
                    $key = self::keyOf($panel, $item);

                    if ($key !== null) {
                        $items[$key] = $item;
                    }
                }
            }

            return $items;
        } finally {
            app()->forgetInstance(NavigationManager::class);

            if ($current) {
                Filament::setCurrentPanel($current);
            }
        }
    }

    /** @return list<string> The keys the user chose, or the defaults. */
    public static function selected(): array
    {
        $saved = UiState::recall(self::STATE_KEY);

        return array_values(array_map('strval', is_array($saved) ? $saved : self::DEFAULTS));
    }

    /** @param  list<string>  $keys */
    public static function save(array $keys): void
    {
        UiState::remember(self::STATE_KEY, array_slice(array_values($keys), 0, self::MAX));
    }

    /**
     * The items to show, in the sidebar's order: the chosen ones that still
     * exist and that this user may open.
     *
     * @return list<NavigationItem>
     */
    public static function items(): array
    {
        $chosen = self::selected();

        return array_values(array_filter(
            self::available(),
            fn (string $key): bool => in_array($key, $chosen, true),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    private static function keyOf(Panel $panel, NavigationItem $item): ?string
    {
        $url = $item->getUrl();

        if (blank($url)) {
            return null;
        }

        $key = trim(substr($url, strlen(rtrim($panel->getUrl() ?? '', '/'))), '/');

        // The dashboard is the panel's root.
        return $key === '' ? self::DASHBOARD : $key;
    }
}
