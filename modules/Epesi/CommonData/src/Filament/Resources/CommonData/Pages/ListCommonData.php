<?php

namespace Epesi\Modules\CommonData\Filament\Resources\CommonData\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\CommonData\CommonDataRepository;
use Epesi\Modules\CommonData\Filament\Resources\CommonData\CommonDataResource;
use Epesi\Modules\CommonData\Models\CommonDataNode;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Url;

/**
 * One level of the tree at a time, selected by `?path=`.
 *
 * The two shared page concerns are used directly rather than by extending
 * RecordBrowser's base page: reference data is read by that engine's field
 * types, not the other way round, and a module dependency pointing back here
 * would become circular the moment `FieldType::CommonData` lands.
 */
class ListCommonData extends ListRecords
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = CommonDataResource::class;

    #[Url]
    public ?string $path = null;

    protected ?CommonDataNode $currentNode = null;

    protected bool $currentNodeResolved = false;

    /**
     * The node whose children are on screen, or null at the root.
     */
    public function currentNode(): ?CommonDataNode
    {
        if (! $this->currentNodeResolved) {
            $this->currentNode = filled($this->path)
                ? app(CommonDataRepository::class)->node($this->path)
                : null;

            $this->currentNodeResolved = true;
        }

        return $this->currentNode;
    }

    public function getTitle(): string
    {
        return filled($this->path) ? $this->path : 'Common Data';
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return parent::getTableQuery()?->where('parent_id', $this->currentNode()?->getKey());
    }

    /**
     * One crumb per path segment, each linking to its own level. Epesi offers a
     * Back button that only ever steps up one level.
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = $this->getResourceBreadcrumbs();

        $walked = '';

        foreach ($this->currentNode()?->segments() ?? [] as $segment) {
            $walked = $walked === '' ? $segment : $walked.'/'.$segment;

            $breadcrumbs[CommonDataResource::getUrl('index', ['path' => $walked])] = $segment;
        }

        return $breadcrumbs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New entry')
                ->modalHeading(fn (): string => filled($this->path) ? "New entry in {$this->path}" : 'New entry')
                ->mutateDataUsing(fn (array $data): array => [
                    ...$data,
                    'parent_id' => $this->currentNode()?->getKey(),
                ]),

            $this->resetOrderAction(),
        ];
    }

    /**
     * Epesi's "Reset Order By Key" action, kept because positions drift as
     * entries are added: a list dragged into a deliberate order stays that way,
     * but one that was never curated ends up in insertion order.
     */
    protected function resetOrderAction(): Action
    {
        return Action::make('resetOrder')
            ->label('Reset order by key')
            ->icon(Heroicon::OutlinedBars3BottomLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('Renumbers the entries at this level into key order. Any order you dragged them into is lost.'))
            ->action(function (): void {
                app(CommonDataRepository::class)->resetOrderByKey($this->path);

                Notification::make()->success()->title(__('Order reset'))->send();
            });
    }
}
