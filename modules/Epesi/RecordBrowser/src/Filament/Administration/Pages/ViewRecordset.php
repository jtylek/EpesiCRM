<?php

namespace Epesi\Modules\RecordBrowser\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Filament\Concerns\UsesEpesiFormLayout;
use BackedEnum;
use Closure;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Epesi\Modules\RecordBrowser\Recordset\ClipboardPattern;
use Epesi\Modules\RecordBrowser\Recordset\ExportGrants;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Spatie\Permission\Models\Role;

/**
 * One recordset's working screen: its features, Clipboard pattern and Export
 * roles, kept with Save, plus the way to its fields. There is no separate Edit
 * step. The field editor is a resource behind this page; only the Recordsets
 * list registers in navigation.
 */
class ViewRecordset extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;
    use UsesEpesiFormLayout;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $slug = 'recordsets/{alias}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'epesi-recordbrowser::view-recordset';

    #[Locked]
    public string $alias;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Recordsets::canAccess();
    }

    public function mount(string $alias): void
    {
        abort_unless(array_key_exists($alias, Recordsets::recordsets()), 404);

        $this->alias = $alias;

        $this->form->fill([
            'features' => Recordsets::enabledFeatures($alias),
            'clipboard_pattern' => ClipboardPattern::patternFor($alias) ?? '',
            'export_roles' => ExportGrants::rolesFor($alias),
        ]);
    }

    public function getTitle(): string
    {
        return isset($this->alias)
            ? (Recordsets::recordsets()[$this->alias] ?? __('Recordset'))
            : __('Recordset');
    }

    public function getBreadcrumbs(): array
    {
        return Recordsets::breadcrumbsFor($this->alias);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                CheckboxList::make('features')
                    ->label(__('Features'))
                    ->live()
                    ->options(fn (): array => array_map(fn (string $label): string => __($label), RecordsetFeatures::definitions()))
                    ->helperText(fn (): string => __('Links already made stay in place when a module is turned off; they show again when it is turned back on.')),
                Textarea::make('clipboard_pattern')
                    ->label(__('Clipboard pattern'))
                    ->rows(8)
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => in_array('clipboard', $get('features') ?? [], true))
                    ->hintAction($this->keywordsAction())
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (trim((string) $value) === '') {
                                // An empty override is allowed: it falls back
                                // to the module's default (ClipboardPattern::patternFor()).
                                return;
                            }

                            if ($error = ClipboardPattern::validate($this->alias, (string) $value)) {
                                $fail($error);
                            }
                        },
                    ])
                    ->helperText(__('{key} substitutes a field; a %{…} block is dropped whole when none of its keys has a value (blocks can\'t nest). Leave blank to use the recordset\'s default pattern.')),
                Placeholder::make('clipboard_preview')
                    ->label(__('Preview'))
                    ->visible(fn (Get $get): bool => in_array('clipboard', $get('features') ?? [], true))
                    ->content(function (Get $get): HtmlString {
                        $pattern = trim((string) ($get('clipboard_pattern') ?? ''));
                        $sample = ClipboardPattern::sampleRecordFor($this->alias);

                        if ($sample === null) {
                            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">'.e(__('No record of this type exists yet to preview against.')).'</span>');
                        }

                        if ($pattern === '') {
                            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">'.e(__('Nothing to preview.')).'</span>');
                        }

                        return new HtmlString('<pre class="whitespace-pre-wrap text-sm text-gray-950 dark:text-white">'.e(ClipboardPattern::renderPattern($pattern, $sample, $this->alias)).'</pre>');
                    }),
                Select::make('export_roles')
                    ->label(__('Export roles'))
                    ->multiple()
                    ->options(fn (): array => Role::query()->where('name', '!=', 'super_admin')->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (Get $get): bool => in_array('export', $get('features') ?? [], true))
                    ->helperText(__('Super admins can export by default. These roles may export this recordset when they can view it.')),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fields')
                ->label(__('Manage fields'))
                ->icon(Heroicon::OutlinedTableCells)
                ->url(fn (): string => CustomFieldResource::getUrl('index', ['recordset' => $this->alias])),
            Action::make('addField')
                ->label(__('Add custom field'))
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => CustomFieldResource::getUrl('create', ['recordset' => $this->alias])),
        ];
    }

    protected function keywordsAction(): Action
    {
        return Action::make('keywords')
            ->label(__('Keywords'))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->modalHeading(__('Keywords'))
            ->modalDescription(__('{key} substitutes a field; a %{…} block is dropped whole when none of its keys has a value.'))
            ->modalContent(fn (): HtmlString => new HtmlString(
                '<pre class="whitespace-pre-wrap text-sm text-gray-950 dark:text-white">'.e(implode("\n", array_map(
                    fn (string $keyword): string => "{{$keyword}}",
                    array_keys(ClipboardPattern::keywords($this->alias)),
                ))).'</pre>'
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $features = $data['features'] ?? [];

        DB::transaction(function () use ($features, $data): void {
            if (in_array('export', $features, true)) {
                ExportGrants::save($this->alias, $data['export_roles'] ?? []);
            }

            foreach (array_keys(RecordsetFeatures::definitions()) as $feature) {
                RecordsetFeatures::set($feature, $this->alias, in_array($feature, $features, true));
            }

            // Off keeps its old pattern for when it is enabled again.
            if (in_array('clipboard', $features, true)) {
                ClipboardPattern::setPattern($this->alias, $data['clipboard_pattern'] ?? null);
            }
        });

        Notification::make()->success()->title(__('Recordset settings saved'))->send();
    }
}
