<?php

namespace Epesi\Modules\RecordBrowser\Filament\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Support\CloneRecordAction;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\IncomingLinks;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Shared base for every resource's Create page — see EditRecord in this
 * namespace for why this exists (the same header-action-consolidation
 * convention, just no redirect override: Filament's default post-create
 * redirect is left as-is here since no one's asked to change it yet).
 */
abstract class CreateRecord extends BaseCreateRecord
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    /** No "Create & create another" button (AI-shared/conventions.md). */
    protected static bool $canCreateAnother = false;

    /** Id of the record this form was cloned from, kept for the History entry on save. */
    public ?string $clonedFrom = null;

    /** Opened by the Clone action: start from the cloned values instead of the defaults. */
    protected function fillForm(): void
    {
        $state = CloneRecordAction::pendingState(static::getResource(), request()->query('clone'));

        if ($state === null) {
            parent::fillForm();

            return;
        }

        $this->clonedFrom = (string) request()->query('clone');
        $this->callHook('beforeFill');
        $this->form->fill($state);

        // Link-style fields (Customer, Related, …) re-read their value from the
        // record while filling, and a new record has none, so set them afterwards.
        foreach ($state as $name => $value) {
            $this->data[$name] = $value;
        }
        $this->callHook('afterFill');
    }

    protected function handleRecordCreation(array $data): Model
    {
        // A Customer picker holds a token, not columns (dehydrated(false)), and would
        // write `{name}_type`/`{name}_id` only after this insert, logging a second,
        // "updated" entry for a new record. Put the columns in the insert itself.
        $resource = static::getResource();

        foreach (is_subclass_of($resource, RecordsetResource::class) ? $resource::resolvedFields() : [] as $field) {
            if ($field->type === FieldType::Customer && ($pick = RecordLink::parseToken($this->data[$field->name] ?? null)) !== null) {
                $data["{$field->name}_type"] = $pick[0];
                $data["{$field->name}_id"] = $pick[1];
            }
        }

        $record = parent::handleRecordCreation($data);

        // Kept in the session until now so a page refresh keeps the cloned values.
        CloneRecordAction::forget();

        if ($this->clonedFrom !== null && method_exists($record, 'getLogNameToUse')) {
            activity($record->getLogNameToUse())
                ->performedOn($record)
                ->causedBy(auth()->user())
                ->event('cloned')
                ->log("cloned from #{$this->clonedFrom}");
        }

        return $record;
    }

    protected function afterFill(): void
    {
        $token = RecordLink::parseToken(request()->query('link'));

        if ($token === null || ! ($model = Relation::getMorphedModel($token[0]))) {
            return;
        }

        $target = $model::query()->find($token[1]);

        if (! $target) {
            return;
        }

        foreach (IncomingLinks::for($target)[static::getResource()] ?? [] as $field) {
            // A picker held to crits the record fails (Deal Owner: staff only)
            // would show a bare id, so it keeps its own default.
            if (in_array($field->type, [FieldType::Relation, FieldType::Relations], true) && ! $field->offers($target)) {
                continue;
            }

            $this->data[$field->getStateName()] = match ($field->type) {
                FieldType::Relation => $target->getKey(),
                FieldType::Relations => [$target->getKey()],
                FieldType::Related, FieldType::Customers => [$token[0].':'.$token[1]],
                FieldType::Customer => $token[0].':'.$token[1],
            };
        }
    }

    /**
     * Matches View/Edit's compact "label beside value" layout — see
     * EditRecord::hasInlineLabels() in this namespace for why this
     * overrides the instance method rather than calling the inherited
     * static `inlineLabels()` setter (it would flip the flag panel-wide
     * instead of scoping to just this page type).
     */
    public function hasInlineLabels(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...(is_subclass_of(static::getResource(), RecordsetResource::class) ? [
                Action::make('clickToFill')
                    ->label('Click 2 Fill')
                    ->icon(Heroicon::OutlinedClipboardDocument)
                    ->color('gray')
                    ->alpineClickHandler("\$dispatch('epesi-click-to-fill-toggle')"),
            ] : []),
            // getCreateFormAction() renders type="submit"; moved here
            // (outside the <form>), it needs formId('form') or the button
            // has no owning form and clicking it is a silent no-op.
            $this->getCreateFormAction()->label('Save')->icon(Heroicon::OutlinedCheck)->color('success')->formId('form'),
            $this->getCancelFormAction()->icon(Heroicon::OutlinedXMark),
        ];
    }

    /**
     * All actions live in the header (see above) — no duplicate action row
     * at the bottom of the form.
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
