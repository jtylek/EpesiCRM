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
use Filament\Pages\Page;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The one Administration → Data → Recordsets entry. It only opens a recordset:
 * everything about one is managed on its ViewRecordset page.
 */
class Recordsets extends Page implements HasTable
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Recordsets';

    protected static ?string $title = 'Recordsets';

    protected static ?string $slug = 'recordsets';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected string $view = 'epesi-recordbrowser::recordsets';

    public static function canAccess(): bool
    {
        return auth()->user()?->active && auth()->user()?->hasRole('super_admin') && ! Demo::enabled();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect(static::recordsets())->map(fn (string $label, string $alias): array => [
                '__key' => $alias,
                'label' => $label,
                'alias' => $alias,
                'module' => static::moduleFor($alias),
                'modules' => collect(static::enabledFeatures($alias))
                    ->map(fn (string $feature): string => __(RecordsetFeatures::definitions()[$feature]))
                    ->implode(', '),
            ]))
            ->columns([
                TextColumn::make('label')->label(__('Recordset')),
                TextColumn::make('alias')->label(__('Alias')),
                TextColumn::make('module')->label(__('Module')),
                TextColumn::make('modules')->label(__('Enabled features'))->placeholder('-'),
            ])
            ->recordUrl(fn (array $record): string => ViewRecordset::getUrl(['alias' => $record['__key']]))
            ->recordActions([$this->viewAction()], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([])
            ->paginated(false);
    }

    protected function viewAction(): Action
    {
        return Action::make('view')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->url(fn (array $record): string => ViewRecordset::getUrl(['alias' => $record['__key']]));
    }

    /**
     * The way back to a recordset: this list, then its page.
     *
     * @return array<string, HtmlString|string>
     */
    public static function breadcrumbsFor(string $alias): array
    {
        $icon = static::getNavigationIcon();

        return [
            static::getUrl() => blank($icon) ? static::getNavigationLabel() : new HtmlString(
                view('filament.components.heading-with-icon', [
                    'icon' => $icon,
                    'heading' => static::getNavigationLabel(),
                    'size' => IconSize::Medium,
                ])->render()
            ),
            ViewRecordset::getUrl(['alias' => $alias]) => static::recordsets()[$alias] ?? $alias,
        ];
    }

    /**
     * @return array<string, string> morph alias => label, recordsets of the main panel only
     */
    public static function recordsets(): array
    {
        return collect(app(FieldOverrides::class)->recordsets())
            ->reject(fn (array $recordset): bool => is_subclass_of($recordset['model'], CollectionItem::class))
            ->map(fn (array $recordset): string => __($recordset['label']))
            ->all();
    }

    /**
     * @return array<int, string> the features turned on for a recordset
     */
    public static function enabledFeatures(string $alias): array
    {
        return array_values(Arr::where(
            array_keys(RecordsetFeatures::definitions()),
            fn (string $feature): bool => RecordsetFeatures::enabled($feature, $alias),
        ));
    }

    protected static function moduleFor(string $alias): string
    {
        $model = Relation::getMorphedModel($alias) ?? '';
        $module = Str::before((string) Str::after($model, 'Modules\\'), '\\Models\\');

        return str_replace('\\', '/', $module);
    }
}
