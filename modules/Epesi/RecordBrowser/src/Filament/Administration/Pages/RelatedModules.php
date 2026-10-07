<?php

namespace Epesi\Modules\RecordBrowser\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\Demo;
use BackedEnum;
use Epesi\Modules\RecordBrowser\Models\CollectionItem;
use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Administration → Data → Related modules: for each recordset, which addon
 * modules attach themselves to it — whether e-mails can be linked to
 * Projects, whether Tickets get a Notes tab. The choice is stored per
 * recordset (RecordsetFeatures); a module only says what it starts as.
 */
class RelatedModules extends Page implements HasTable
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $navigationLabel = 'Related modules';

    protected static ?string $title = 'Related modules';

    protected static ?string $slug = 'related-modules';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected string $view = 'epesi-recordbrowser::related-modules';

    public static function canAccess(): bool
    {
        return auth()->user()?->active && auth()->user()?->hasRole('super_admin') && ! Demo::enabled()
            && RecordsetFeatures::definitions() !== [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect(static::recordsets())->map(fn (string $label, string $alias): array => [
                '__key' => $alias,
                'label' => $label,
                'modules' => collect(static::enabledFeatures($alias))
                    ->map(fn (string $feature): string => __(RecordsetFeatures::definitions()[$feature]))
                    ->implode(', '),
            ]))
            ->columns([
                TextColumn::make('label')->label(__('Recordset')),
                TextColumn::make('modules')->label(__('Related modules'))->placeholder('-'),
            ])
            ->recordActions([$this->editAction()], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([])
            ->paginated(false);
    }

    protected function editAction(): Action
    {
        return Demo::guard(Action::make('edit')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencil)
            ->color('gray')
            ->modalHeading(fn (array $record): string => __('Related modules').': '.$record['label'])
            ->modalDescription(__('Choose which addon modules attach to this recordset. A change applies from the next page load.'))
            ->modalWidth('lg')
            ->fillForm(fn (array $record): array => ['features' => static::enabledFeatures($record['__key'])])
            ->schema([
                CheckboxList::make('features')
                    ->hiddenLabel()
                    ->options(fn (): array => array_map(fn (string $label): string => __($label), RecordsetFeatures::definitions()))
                    ->helperText(fn (): string => __('Links already made stay in place when a module is turned off; they show again when it is turned back on.')),
            ])
            ->action(function (array $data, array $record): void {
                $alias = (string) $record['__key'];
                abort_unless(array_key_exists($alias, static::recordsets()), 404);

                foreach (array_keys(RecordsetFeatures::definitions()) as $feature) {
                    RecordsetFeatures::set($feature, $alias, in_array($feature, $data['features'] ?? [], true));
                }

                Notification::make()->success()->title(__('Related modules saved'))->send();
            }));
    }

    /**
     * @return array<string, string> morph alias => label, recordsets of the main panel only
     */
    protected static function recordsets(): array
    {
        return collect(app(FieldOverrides::class)->recordsets())
            ->reject(fn (array $recordset): bool => is_subclass_of($recordset['model'], CollectionItem::class))
            ->map(fn (array $recordset): string => __($recordset['label']))
            ->all();
    }

    /**
     * @return array<int, string> the features turned on for a recordset
     */
    protected static function enabledFeatures(string $alias): array
    {
        return array_values(Arr::where(
            array_keys(RecordsetFeatures::definitions()),
            fn (string $feature): bool => RecordsetFeatures::enabled($feature, $alias),
        ));
    }
}
