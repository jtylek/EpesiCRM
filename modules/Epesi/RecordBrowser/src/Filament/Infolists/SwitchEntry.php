<?php

namespace Epesi\Modules\RecordBrowser\Filament\Infolists;

use Filament\Infolists\Components\Entry;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\View\Components\ToggleComponent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

use function Filament\Support\get_component_color_classes;

/**
 * A yes/no value shown as the on/off switch the form sets it with, instead of
 * IconEntry's check or cross — Filament has no switch entry of its own. It is
 * Filament's own `fi-toggle` markup (see Forms\Components\Toggle), drawn
 * disabled: a View page only shows the value, it is changed on Edit.
 *
 * The switch is taller than a line of text, so it is laid out as a block and
 * reaches 2px into its box's padding at top and bottom: the row stays the
 * height of the text row beside it (Permission next to Sticky) instead of
 * standing out 7px taller.
 */
class SwitchEntry extends Entry implements HasEmbeddedView
{
    public function toEmbeddedHtml(): string
    {
        $state = (bool) $this->getState();
        $label = $this->getLabel();

        $classes = Arr::toCssClasses([
            'fi-toggle',
            ...($state
                ? ['fi-toggle-on', ...get_component_color_classes(ToggleComponent::class, 'primary')]
                : ['fi-toggle-off', ...get_component_color_classes(ToggleComponent::class, 'gray')]),
        ]);

        ob_start(); ?>

        <div style="display: flex">
            <div
                role="switch"
                aria-checked="<?= $state ? 'true' : 'false' ?>"
                aria-disabled="true"
                aria-label="<?= e(trim(strip_tags($label instanceof Htmlable ? $label->toHtml() : (string) $label)), doubleEncode: false) ?>"
                disabled
                class="<?= $classes ?>"
                style="margin-block: -2px"
            >
                <div>
                    <div aria-hidden="true"></div>
                    <div aria-hidden="true"></div>
                </div>
            </div>
        </div>

        <?php return $this->wrapEmbeddedHtml(ob_get_clean());
    }
}
