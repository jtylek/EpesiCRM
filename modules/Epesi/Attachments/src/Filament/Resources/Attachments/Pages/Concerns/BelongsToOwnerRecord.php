<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns;

use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Models\Attachment;
use Filament\Facades\Filament;
use Filament\Support\Enums\IconSize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;

/**
 * A note page opened from a record's Notes tab knows that record: it reads
 * as part of it ("Phone Calls > Postponed: CCN… > New note"), keeps it in
 * every link to another note page, and goes back to its Notes tab wherever
 * Filament would go back to the Notes list. Opened from the list, there is
 * no owner and the page is an ordinary resource page.
 */
trait BelongsToOwnerRecord
{
    #[Locked]
    public ?Model $ownerRecord = null;

    /**
     * From the ?attachable_type=&attachable_id= query string, when there is
     * one. The record is found through its own query, so its ownership scope
     * applies: a record you can't see can't be written on either, and a note
     * can only be opened as part of a record it is actually on.
     */
    protected function mountOwnerRecord(?Attachment $note = null): void
    {
        if (! request()->has('attachable_type')) {
            return;
        }

        $owner = AttachmentResource::findRecord(request()->string('attachable_type')->toString(), request()->integer('attachable_id'));

        abort_if($owner === null, 404);

        abort_if($note && ! $note->links()
            ->where('attachable_type', $owner->getMorphClass())
            ->where('attachable_id', $owner->getKey())
            ->exists(), 404);

        $this->ownerRecord = $owner;
    }

    /**
     * Labels above the fields rather than beside them (RecordBrowser's
     * Create/Edit default): a label column would take a third of the note
     * editor's width, and the width is why this is a page.
     */
    public function hasInlineLabels(): bool
    {
        return false;
    }

    public function getResourceUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = true): string
    {
        if ($this->ownerRecord === null) {
            return parent::getResourceUrl($name, $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
        }

        if (blank($name) || $name === 'index') {
            return AttachmentResource::ownerUrl($this->ownerRecord);
        }

        return parent::getResourceUrl($name, [...AttachmentResource::ownerParameters($this->ownerRecord), ...$parameters], $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }

    public function getResourceBreadcrumbs(): array
    {
        if ($this->ownerRecord === null) {
            return parent::getResourceBreadcrumbs();
        }

        $resource = Filament::getModelResource($this->ownerRecord);

        return [
            $resource::getUrl() => new HtmlString(view('filament.components.heading-with-icon', [
                'icon' => $resource::getNavigationIcon(),
                'heading' => $resource::getBreadcrumb(),
                'size' => IconSize::Medium,
            ])->render()),
            $this->getResourceUrl() => $resource::getRecordTitle($this->ownerRecord),
        ];
    }
}
