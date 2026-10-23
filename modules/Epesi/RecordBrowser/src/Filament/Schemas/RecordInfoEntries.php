<?php

namespace Epesi\Modules\RecordBrowser\Filament\Schemas;

use App\Models\User;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Schemas\Components\View;
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
     * One three-column table (label | who | when) in a single view, since
     * inline-label entries can't line a third column up across rows.
     *
     * @param  string|null  $recordset  The resource slug, shown before the ID (companies/90).
     * @return array<View>
     */
    public static function components(?string $recordset = null): array
    {
        return [
            View::make('filament.components.record-info')->viewData(fn (Model $record): array => [
                'id' => ($recordset ? $recordset.'/' : '').$record->getKey(),
                'rows' => array_values(array_filter([
                    [__('Updated'), self::name(self::updater($record)), RegionalSetting::display($record->updated_at)],
                    [__('Created'), self::name(self::creator($record)), RegionalSetting::display($record->created_at)],
                    method_exists($record, 'trashed') && $record->trashed()
                        ? [__('Deleted'), null, RegionalSetting::display($record->deleted_at)]
                        : null,
                ])),
            ]),
        ];
    }

    private static function name(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $contact = $user->contact;
        $name = $contact ? trim("{$contact->last_name} {$contact->first_name}") : '';

        return $name !== '' ? $name : $user->name;
    }

    private static function creator(Model $record): ?User
    {
        return method_exists($record, 'creator') ? $record->creator : null;
    }

    private static function updater(Model $record): ?User
    {
        return method_exists($record, 'lastUpdater') ? $record->lastUpdater() : null;
    }
}
