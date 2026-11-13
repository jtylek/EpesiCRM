<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\Recordsets;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;

class ListCustomFields extends ListRecords
{
    use LeadsBackToRecordset;
    use ReordersFields;

    protected static string $resource = CustomFieldResource::class;

    /**
     * Opened from a recordset (`?recordset=company`), the list holds that
     * recordset's fields only and offers no way to another's. Locked, and read
     * from the URL only here, so nothing later in the session can change it.
     */
    #[Locked]
    public ?string $recordset = null;

    public function mount(): void
    {
        parent::mount();

        $alias = request()->query('recordset');
        if (is_string($alias)) {
            abort_unless(array_key_exists($alias, Recordsets::recordsets()), 404);
            $this->recordset = $alias;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return filled($this->recordset)
            ? __(':recordset fields', ['recordset' => Recordsets::recordsets()[$this->recordset]])
            : parent::getTitle();
    }

    protected function breadcrumbRecordset(): ?string
    {
        return $this->recordset;
    }

    protected function getHeaderActions(): array
    {
        return [
            // New field starts with the recordset selected, as Add custom field does.
            CreateAction::make()->url(fn (): string => CustomFieldResource::getUrl('create', filled($this->recordset) ? ['recordset' => $this->recordset] : [])),
            $this->syncAction(),
        ];
    }

    /**
     * The definitions table is the source of truth and the schema is derived
     * from it; this is the button that makes that true — the repair path when a
     * column is missing, and how definitions copied from another install
     * materialise here.
     */
    protected function syncAction(): Action
    {
        return Action::make('sync')
            ->label('Repair columns')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('Creates any column a field definition needs but the database is missing. Existing columns and data are untouched.'))
            // The same work as `customfields:sync`, called directly: the command
            // is only registered for the console, so Artisan::call() from a web
            // request can't find it.
            ->action(function (): void {
                $created = app(CustomFieldSchema::class)->sync();

                CustomFieldRegistry::refresh();

                Notification::make()->success()->title(__('Columns checked'))
                    ->body($created === []
                        ? __('Every field already has its column.')
                        : __('Created:').' '.implode(', ', $created))
                    ->send();
            });
    }
}
