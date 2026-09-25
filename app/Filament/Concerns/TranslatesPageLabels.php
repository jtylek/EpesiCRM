<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;

/**
 * A standalone page's title and navigation label through __() — both come
 * from static properties Filament never translates.
 */
trait TranslatesPageLabels
{
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    public function getTitle(): string|Htmlable
    {
        $title = parent::getTitle();

        return is_string($title) ? __($title) : $title;
    }
}
