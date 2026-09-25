<?php

namespace Epesi\Modules\RecordBrowser\Filament\Schemas;

use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Record ID / Updated / Created" entries shared by every resource's
 * infolist, factored out so they can be reused as the "Record Info" tab
 * (see Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord) instead of duplicating this block per
 * resource. Most models here have Model::creator()/lastUpdater()/trashed()
 * (see modules/Epesi/RecordBrowser/src/Models/Concerns/HasOwnershipVisibility.php), but this tab is also
 * used for models without that concern (e.g. App\Models\User, which has no
 * ownership tracking or soft deletes) — each is guarded with method_exists(),
 * the same way Filament's own DeleteAction/ForceDeleteAction/RestoreAction
 * guard their own trashed() calls.
 *
 * Rendered as 3 compact "Label: value" lines rather than Filament's default
 * stacked label-then-value pairs, via hiddenLabel()+prefix()/suffix() on a
 * single dateTime() entry per line instead of separate label/by-whom entries.
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
                ->hiddenLabel()
                ->prefix(__('Record ID:').' ')
                ->columnSpanFull(),
            TextEntry::make('updated_at')
                ->hiddenLabel()
                ->dateTime()
                ->prefix(__('Updated:').' ')
                ->suffix(function (Model $record): string {
                    $by = method_exists($record, 'lastUpdater') ? $record->lastUpdater()?->displayName() : null;

                    return $by ? " by {$by}" : '';
                })
                ->columnSpanFull(),
            TextEntry::make('created_at')
                ->hiddenLabel()
                ->dateTime()
                ->prefix(__('Created:').' ')
                ->suffix(fn (Model $record): string => ($by = $record->creator?->displayName()) ? " by {$by}" : '')
                ->columnSpanFull(),
            TextEntry::make('deleted_at')
                ->hiddenLabel()
                ->dateTime()
                ->prefix(__('Deleted:').' ')
                ->columnSpanFull()
                ->visible(fn (Model $record): bool => method_exists($record, 'trashed') && $record->trashed()),
        ];
    }
}
