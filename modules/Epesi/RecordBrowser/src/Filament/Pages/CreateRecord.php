<?php

namespace Epesi\Modules\RecordBrowser\Filament\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\IncomingLinks;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;
use Filament\Support\Icons\Heroicon;
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
