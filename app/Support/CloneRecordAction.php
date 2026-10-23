<?php

namespace App\Support;

use Closure;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A confirm-then-duplicate action, deliberately not Filament's own
 * ReplicateAction (an edit-form *modal*): it opens the resource's Create
 * *page* pre-filled with the cloned values. Nothing is saved until the user
 * presses Save there, so abandoning the form leaves no record behind. The
 * Create page reads the values back through pendingState().
 */
class CloneRecordAction
{
    protected const SESSION_KEY = 'epesi.clone';

    /**
     * @param  class-string<resource>  $resource
     * @param  array<string>  $resetAttributes  extra columns to leave blank on the
     *                                          clone beyond id/created_at/updated_at
     *                                          (already excluded by replicate()) —
     *                                          typically unique columns that would
     *                                          otherwise collide, e.g. email.
     * @param  (Closure(Model): void)|null  $prepareReplica  adjust cloned values before saving
     */
    public static function make(string $resource, array $resetAttributes = [], ?Closure $prepareReplica = null): Action
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
            ->modalDescription(__('This will open a new record pre-filled from this one. Nothing is saved until you press Save.'))
            ->modalSubmitActionLabel(__('Clone'))
            ->action(function (Model $record, Action $action) use ($resource, $resetAttributes, $prepareReplica): void {
                $replica = $record->replicate([...$resetAttributes, 'created_by', 'legacy_id']);
                $prepareReplica?->__invoke($replica);

                // Only the values are kept, in the session: nothing is saved,
                // so leaving the Create form without Save leaves no record.
                session()->put(static::SESSION_KEY, [
                    'resource' => $resource,
                    'source' => $record->getKey(),
                    'attributes' => $replica->attributesToArray(),
                ]);

                // Not return redirect(): that reloads the page, where the
                // action's own redirect follows the panel's SPA mode.
                $action->redirect($resource::getUrl('create', ['clone' => $record->getKey()]));
            });
    }

    /**
     * The form state a Create page opened by the Clone action starts from,
     * or null when it wasn't opened that way: the replica's own columns plus
     * the records a field links to, which live in other tables (a Relations
     * field's pivot rows, the record links of Customers and Related fields).
     *
     * @param  class-string<resource>  $resource
     * @return array<string, mixed>|null
     */
    public static function pendingState(string $resource, mixed $sourceId): ?array
    {
        $pending = session()->get(static::SESSION_KEY);

        if (! is_array($pending) || $pending['resource'] !== $resource || (string) $pending['source'] !== (string) $sourceId) {
            return null;
        }

        $state = $pending['attributes'];
        $record = $resource::getModel()::query()->find($pending['source']);

        if ($record === null || ! is_subclass_of($resource, RecordsetResource::class)) {
            return $state;
        }

        foreach ($resource::resolvedFields() as $field) {
            $name = $field->getStateName();

            switch ($field->type) {
                case FieldType::Related:
                case FieldType::Customers:
                    if (method_exists($record, 'recordLinks')) {
                        $state[$name] = $record->recordLinks()->where('field', $name)->orderBy('id')->get()
                            ->map(fn ($link): string => $link->token())->all();
                    }
                    break;
                case FieldType::Customer:
                    $type = $record->getAttribute("{$name}_type");
                    $id = $record->getAttribute("{$name}_id");
                    $state[$name] = $type !== null && $id !== null ? RecordLink::tokenFor((string) $type, (int) $id) : null;
                    break;
                case FieldType::Relations:
                    if (method_exists($record, $name) && $record->{$name}() instanceof BelongsToMany) {
                        $related = $record->{$name}();
                        $state[$name] = $related->pluck($related->getRelated()->getQualifiedKeyName())->all();
                    }
                    break;
                default:
            }
        }

        return $state;
    }

    public static function forget(): void
    {
        session()->forget(static::SESSION_KEY);
    }
}
