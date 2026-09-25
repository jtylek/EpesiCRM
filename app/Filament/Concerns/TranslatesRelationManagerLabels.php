<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * An addon's tab title and record label through __() — both static
 * properties Filament never translates.
 */
trait TranslatesRelationManagerLabels
{
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __(parent::getTitle($ownerRecord, $pageClass));
    }

    protected static function getModelLabel(): ?string
    {
        $label = parent::getModelLabel();

        return $label === null ? null : __($label);
    }

    protected static function getPluralModelLabel(): ?string
    {
        $label = parent::getPluralModelLabel();

        return $label === null ? null : __($label);
    }
}
