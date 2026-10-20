<?php

namespace App\Support;

use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A plain confirm-then-duplicate action, deliberately not Filament's own
 * ReplicateAction — that opens a full edit-form *modal* pre-filled with the
 * record's data before saving the copy, whereas this clones immediately on
 * confirmation and takes the user to the new record's Edit *page* — not
 * View — so an exact duplicate never just sits there unreviewed; the whole
 * point of cloning is to change something before it's a distinct record.
 */
class CloneRecordAction
{
    /**
     * @param  class-string<resource>  $resource
     * @param  array<string>  $resetAttributes  extra columns to leave blank on the
     *                                          clone beyond id/created_at/updated_at
     *                                          (already excluded by replicate()) —
     *                                          typically unique columns that would
     *                                          otherwise collide, e.g. email.
     */
    public static function make(string $resource, array $resetAttributes = []): Action
    {
        // legacy_id is unique (see add_legacy_id_to_migrated_tables) and only
        // ever identifies the original legacy row a record was imported from
        // — a clone is a brand-new record, so it always resets regardless of
        // what the caller passes, the same way created_by always does below.
        return Action::make('clone')
            ->label('Clone')
            ->color('gray')
            ->icon(Heroicon::OutlinedSquare2Stack)
            ->requiresConfirmation()
            ->modalHeading(__('Clone this record?'))
            ->modalDescription(__('This will create a duplicate copy of this record. You\'ll be taken to the new copy to make changes before saving.'))
            ->modalSubmitActionLabel(__('Clone'))
            ->action(function (Model $record, Action $action) use ($resource, $resetAttributes): void {
                $replica = $record->replicate([...$resetAttributes, 'created_by', 'legacy_id']);
                // Suppress LogsActivity's own automatic "created" entry — a
                // clone gets a "cloned" History row instead, logged manually
                // below, so the two ways a record comes into existence read
                // as distinct events rather than both showing up as "created".
                $replica->disableLogging();
                $replica->save();
                $replica->enableLogging();

                static::copyLinks($record, $replica, $resource);

                activity($replica->getLogNameToUse())
                    ->performedOn($replica)
                    ->causedBy(auth()->user())
                    ->event('cloned')
                    ->log("cloned from #{$record->getKey()}");

                Notification::make()
                    ->title(__('Record cloned'))
                    ->success()
                    ->send();

                // Not return redirect(): that reloads the page, where the
                // action's own redirect follows the panel's SPA mode.
                $action->redirect($resource::getUrl('edit', ['record' => $replica]));
            });
    }

    /**
     * What replicate() leaves behind: the records a field links to live in
     * other tables (a Relations field's pivot rows, the record links of
     * Customers and Related fields), not in the row's own columns.
     *
     * @param  class-string<resource>  $resource
     */
    protected static function copyLinks(Model $record, Model $replica, string $resource): void
    {
        if (method_exists($record, 'recordLinks')) {
            foreach ($record->recordLinks()->get() as $link) {
                $replica->recordLinks()->create($link->only(['field', 'target_type', 'target_id']));
            }
        }

        if (! is_subclass_of($resource, RecordsetResource::class)) {
            return;
        }

        foreach ($resource::resolvedFields() as $field) {
            $name = $field->getStateName();

            if ($field->type === FieldType::Relations && method_exists($record, $name) && $record->{$name}() instanceof BelongsToMany) {
                $related = $record->{$name}();

                $replica->{$name}()->sync($related->pluck($related->getRelated()->getQualifiedKeyName())->all());
            }
        }
    }
}
