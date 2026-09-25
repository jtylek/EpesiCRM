<?php

namespace App\Filament\Navigation;

use Collator;
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

        foreach ($navigation as $group) {
            $items = collect($group->getItems())->all();

            usort($items, fn (NavigationItem $a, NavigationItem $b): int => min($a->getSort(), -1) <=> min($b->getSort(), -1)
                ?: $collator->compare($a->getLabel(), $b->getLabel()));

            $group->items($items);
        }

        return $navigation;
    }
}
