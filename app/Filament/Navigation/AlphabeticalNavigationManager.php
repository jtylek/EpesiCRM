<?php

namespace App\Filament\Navigation;

use Collator;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;

/**
 * Orders every sidebar group alphabetically by its translated label, so the
 * menu stays in order whichever modules are installed and whichever language
 * the user reads it in — hand-picked `$navigationSort` numbers can do neither.
 *
 * A sort below Filament's default of -1 still pins an item above the list:
 * that is how the Dashboard (-2) stays first.
 */
class AlphabeticalNavigationManager extends NavigationManager
{
    public function get(): array
    {
        $collator = new Collator(app()->getLocale());

        $navigation = parent::get();

        // A panel that declares its own group order (Administration) keeps it.
        if (blank(Filament::getCurrentPanel()?->getNavigationGroups())) {
            // The ungrouped items stay on top; the groups follow by label.
            usort($navigation, fn (NavigationGroup $a, NavigationGroup $b): int => filled($a->getLabel()) <=> filled($b->getLabel())
                ?: $collator->compare((string) $a->getLabel(), (string) $b->getLabel()));
        }

        foreach ($navigation as $group) {
            $items = collect($group->getItems())->all();

            usort($items, fn (NavigationItem $a, NavigationItem $b): int => min($a->getSort(), -1) <=> min($b->getSort(), -1)
                ?: $collator->compare($a->getLabel(), $b->getLabel()));

            foreach ($items as $item) {
                if (filled($item->getChildItems())) {
                    $children = collect($item->getChildItems())->all();

                    usort($children, fn (NavigationItem $a, NavigationItem $b): int => $collator->compare($a->getLabel(), $b->getLabel()));

                    $item->childItems($children);
                }
            }

            $group->items($items);
        }

        return $navigation;
    }
}
