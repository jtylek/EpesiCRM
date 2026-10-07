<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages;

use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;

class ListAttachments extends ListRecords
{
    protected static string $resource = AttachmentResource::class;

    /**
     * The records select offers three modes here instead of two: all notes,
     * my notes, and my encrypted notes (the latter is "my notes" plus the
     * Encrypted notes filter).
     */
    public function getMyRecordsButton(): ?array
    {
        $button = parent::getMyRecordsButton();

        if ($button === null) {
            return null;
        }

        $encrypted = $button['active'] && $this->encryptedNotesAreShown();

        return $button + [
            'value' => $encrypted ? 'encrypted' : ($button['active'] ? 'mine' : 'all'),
            'options' => [
                'all' => __('All notes'),
                'mine' => __('My notes'),
                'encrypted' => __('My encrypted notes'),
            ],
        ];
    }

    public function setMyRecordsMode(string $mode): void
    {
        if ($mode === 'encrypted') {
            $this->removeTableFilters();
            parent::toggleMyRecords();
            $this->tableFilters = [...($this->tableFilters ?? []), 'encrypted' => ['isActive' => true]];
            $this->handleTableFilterUpdates();

            return;
        }

        if ($this->encryptedNotesAreShown()) {
            $this->removeTableFilters();

            if ($mode === 'mine') {
                parent::toggleMyRecords();
            }

            return;
        }

        parent::setMyRecordsMode($mode);
    }

    protected function encryptedNotesAreShown(): bool
    {
        return (bool) ($this->tableFilters['encrypted']['isActive'] ?? false);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New note')->icon(Heroicon::OutlinedPlus),
        ];
    }
}
