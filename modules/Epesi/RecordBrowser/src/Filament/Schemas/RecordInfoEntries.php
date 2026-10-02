<?php

namespace Epesi\Modules\RecordBrowser\Filament\Schemas;

use Carbon\CarbonInterface;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Record ID / Updated / Created" entries shared by every resource's
 * infolist, factored out so they can be reused as the "Record Info" kebab
 * action's modal (see Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord) instead of duplicating this block per
 * resource. Most models here have Model::creator()/lastUpdater()/trashed()
 * (see modules/Epesi/RecordBrowser/src/Models/Concerns/HasOwnershipVisibility.php), but this tab is also
 * used for models without that concern (e.g. App\Models\User, which has no
 * ownership tracking or soft deletes) — each is guarded with method_exists(),
 * the same way Filament's own DeleteAction/ForceDeleteAction/RestoreAction
 * guard their own trashed() calls.
 *
 * Ordinary labelled entries, one per row — the same boxed label/value layout
 * (resources/css/filament/epesi/boxed-fields.css) as every other View page's
 * infolist, rather than the compact hiddenLabel()+prefix() "Label: value"
 * text this used when it was a Section squeezed into the addon tab strip
 * alongside other tabs. Now that it's the whole content of its own modal,
 * that read as under-formatted; the caller sets `->inlineLabel()` on the
 * wrapping schema to turn the boxing on.
 */
class RecordInfoEntries
{
    /**
     * @return array<TextEntry>
     */
    public static function components(): array
    {
        return [
            TextEntry::make('id')
                ->label(__('Record ID')),
            TextEntry::make('updated_at')
                ->label(__('Updated'))
                ->dateTime()
                ->formatStateUsing(fn (?CarbonInterface $state): ?string => RegionalSetting::display($state))
                ->suffix(function (Model $record): string {
                    $by = method_exists($record, 'lastUpdater') ? $record->lastUpdater()?->displayName() : null;

                    return $by ? ' '.__('by :name', ['name' => $by]) : '';
                }),
            TextEntry::make('created_at')
                ->label(__('Created'))
                ->dateTime()
                ->formatStateUsing(fn (?CarbonInterface $state): ?string => RegionalSetting::display($state))
                ->suffix(fn (Model $record): string => ($by = $record->creator?->displayName()) ? ' '.__('by :name', ['name' => $by]) : ''),
            TextEntry::make('deleted_at')
                ->label(__('Deleted'))
                ->dateTime()
                ->formatStateUsing(fn (?CarbonInterface $state): ?string => RegionalSetting::display($state))
                ->visible(fn (Model $record): bool => method_exists($record, 'trashed') && $record->trashed()),
        ];
    }
}
