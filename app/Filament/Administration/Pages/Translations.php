<?php

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\Locale\Locales;
use App\Support\Translations\CustomTranslations;
use App\Support\Translations\TranslationCatalog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Translations tab of Epesi's Base/Lang/Administrator: every string of
 * the interface next to its translation in one language, to fill in a
 * missing translation or replace one with this installation's own.
 *
 * Whatever is saved here is a custom translation (CustomTranslations), kept
 * apart from the shipped files and loaded over them. The shipped translations
 * are the developers' to complete; Epesi's "send your translations to our
 * server" became links to GitHub and the forum, and a download of the custom
 * translations to send there.
 */
class Translations extends Page implements HasTable
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    public const GITHUB_URL = 'https://github.com/jtylek/epesiCRM';

    public const FORUM_URL = 'https://forum.epe.si/';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $navigationLabel = 'Translations';

    protected static ?string $title = 'Translations';

    protected static ?string $slug = 'translations';

    /** The language being translated, one tab each. */
    #[Url]
    public ?string $language = null;

    /** @var array<string, array<string, array<string, mixed>>> by language */
    protected array $catalogs = [];

    public function mount(): void
    {
        $this->language = $this->validLanguage($this->language);
    }

    public function updatedLanguage(): void
    {
        $this->language = $this->validLanguage($this->language);
        $this->resetPage();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(__('Custom translations'))
                ->description(__('What you translate here is kept on this installation as a custom translation. It is used instead of the translation shipped with epesi, and an update leaves it as it is. To improve a shipped translation for everyone, send it to the developers as a pull request on GitHub or post it on the forum.'))
                ->info()
                ->actions([
                    Action::make('github')
                        ->label('GitHub')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->link()
                        ->url(self::GITHUB_URL, shouldOpenInNewTab: true),
                    Action::make('forum')
                        ->label('Forum')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->link()
                        ->url(self::FORUM_URL, shouldOpenInNewTab: true),
                ]),
            Tabs::make()
                ->key('languages')
                ->livewireProperty('language')
                ->contained(false)
                ->tabs(collect(Locales::available())
                    ->map(fn (string $name, string $code): Tab => Tab::make($name)
                        // Each language by its own name, as everywhere else.
                        ->translateLabel(false)
                        ->badge(fn (): ?int => $this->missingCount($code) ?: null)
                        ->badgeColor('warning')
                        ->badgeTooltip(__('Not translated')))
                    ->all()),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?array $filters, ?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage): LengthAwarePaginator => $this->records($search, $filters, $sortColumn, $sortDirection, $page, $recordsPerPage))
            ->columns([
                TextColumn::make('english')
                    ->label('English')
                    // A group line is looked up by its key, not its text.
                    ->description(fn (array $record): ?string => $record['key'] !== $record['english'] ? $record['key'] : null)
                    ->wrap()
                    ->sortable(),
                TextColumn::make('translation')
                    ->label('Translation')
                    ->state(fn (array $record): ?string => $record['custom'] ?? $record['shipped'])
                    ->description(fn (array $record): ?string => $record['custom'] !== null && $record['shipped'] !== null
                        ? __('Shipped: :translation', ['translation' => $record['shipped']])
                        : null)
                    ->placeholder(__('Not translated'))
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statuses()[$state])
                    ->color(fn (string $state): string => match ($state) {
                        TranslationCatalog::MISSING => 'warning',
                        TranslationCatalog::CUSTOM => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statuses()),
                SelectFilter::make('source')
                    ->label('Source')
                    ->options(fn (): array => collect($this->catalog())->pluck('source', 'source')->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)->all()),
            ])
            ->searchable()
            ->recordAction('translate')
            ->recordActions([
                $this->translateAction(),
                $this->revertAction(),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(__('No strings found'));
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addAction(),
            $this->downloadAction(),
        ];
    }

    protected function translateAction(): Action
    {
        return Action::make('translate')
            ->label('Translate')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()
            ->tooltip(__('Translate'))
            ->modalHeading(fn (): string => __('Translate into :language', ['language' => $this->languageName()]))
            ->modalSubmitActionLabel('Save')
            ->modalSubmitAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedCheck)->color('success'))
            ->modalCancelAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedXMark))
            ->fillForm(fn (array $record): array => ['translation' => $record['custom'] ?? $record['shipped']])
            ->schema(fn (array $record): array => [
                TextEntry::make('english')
                    ->label('English')
                    ->state($record['english'])
                    ->helperText($record['key'] !== $record['english'] ? $record['key'] : null),
                TextEntry::make('shipped')
                    ->label('Shipped translation')
                    ->state($record['shipped'])
                    ->placeholder(__('Not translated'))
                    ->visible($this->language !== TranslationCatalog::SOURCE_LOCALE),
                Textarea::make('translation')
                    ->label('Translation')
                    ->autosize()
                    ->helperText($this->translationHint($record['english'])),
                TextEntry::make('forms')
                    ->label('Other forms of this word')
                    ->state(implode(', ', $forms = $this->otherForms($record['key'])))
                    ->helperText(__('Each is translated on its own. Translate them too, so the new word is used everywhere.'))
                    ->visible($forms !== []),
            ])
            ->action(function (array $record, array $data): void {
                $translation = $data['translation'] ?? null;

                // The shipped translation typed back in isn't a custom one.
                CustomTranslations::put($this->language, $record['key'], $translation === $record['shipped'] ? null : $translation);
                $this->flushCachedTableRecords();
                $this->catalogs = [];

                Notification::make()->success()->title(__('Translation saved'))->send();
            });
    }

    protected function revertAction(): Action
    {
        return Action::make('revert')
            ->label('Remove the custom translation')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->iconButton()
            ->tooltip(__('Remove the custom translation'))
            ->color('danger')
            ->visible(fn (array $record): bool => $record['custom'] !== null)
            ->requiresConfirmation()
            ->modalHeading(__('Remove the custom translation?'))
            ->modalDescription(fn (array $record): string => $record['shipped'] !== null
                ? __('The shipped translation is used again: ":translation".', ['translation' => $record['shipped']])
                : __('The text is left untranslated.'))
            ->modalSubmitActionLabel(__('Remove'))
            ->action(function (array $record): void {
                CustomTranslations::put($this->language, $record['key'], null);
                $this->flushCachedTableRecords();
                $this->catalogs = [];

                Notification::make()->success()->title(__('Custom translation removed'))->send();
            });
    }

    /**
     * For a text the list doesn't have, such as a word in a module's own
     * screen that a developer hasn't given a translation file yet.
     */
    protected function addAction(): Action
    {
        return Action::make('add')
            ->label('Add a translation')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(fn (): string => __('Add a translation into :language', ['language' => $this->languageName()]))
            ->modalSubmitActionLabel('Save')
            ->modalSubmitAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedCheck)->color('success'))
            ->modalCancelAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedXMark))
            ->schema([
                TextInput::make('english')
                    ->label('English text')
                    ->helperText(__('Exactly as it appears in English, capital letters and punctuation included.'))
                    ->required(),
                Textarea::make('translation')
                    ->label('Translation')
                    ->autosize()
                    ->required(),
            ])
            ->action(function (array $data): void {
                CustomTranslations::put($this->language, $data['english'], $data['translation']);
                $this->flushCachedTableRecords();
                $this->catalogs = [];

                Notification::make()->success()->title(__('Translation saved'))->send();
            });
    }

    /**
     * The custom translations of this language, as a file in the shape of
     * lang/<code>.json: what to attach to a pull request or a forum post.
     */
    protected function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Download custom translations')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (): bool => CustomTranslations::for($this->language) !== [])
            ->action(fn (): StreamedResponse => response()->streamDownload(
                function (): void {
                    echo CustomTranslations::encode(CustomTranslations::for($this->language));
                },
                "{$this->language}.json",
                ['Content-Type' => 'application/json'],
            ));
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    protected function records(?string $search, ?array $filters, ?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage): LengthAwarePaginator
    {
        $order = array_flip(array_keys(self::statuses()));
        $sortColumn = in_array($sortColumn, ['english', 'source'], true) ? $sortColumn : null;

        $rows = collect($this->catalog())
            ->when(filled($status = $filters['status']['value'] ?? null), fn ($rows) => $rows->where('status', $status))
            ->when(filled($source = $filters['source']['value'] ?? null), fn ($rows) => $rows->where('source', $source))
            ->when(filled($search), fn ($rows) => $rows->filter(fn (array $row): bool => collect([$row['key'], $row['english'], $row['shipped'], $row['custom']])
                ->contains(fn (?string $text): bool => $text !== null && mb_stripos($text, (string) $search) !== false)))
            // Missing translations first, as Epesi's list had them.
            ->sort(fn (array $a, array $b): int => $sortColumn !== null
                ? strnatcasecmp($a[$sortColumn], $b[$sortColumn]) * ($sortDirection === 'desc' ? -1 : 1)
                : [$order[$a['status']], mb_strtolower($a['english'])] <=> [$order[$b['status']], mb_strtolower($b['english'])])
            ->map(fn (array $row): array => ['__key' => md5($row['key']), ...$row]);

        return new LengthAwarePaginator(
            $rows->forPage($page, $recordsPerPage)->all(),
            total: $rows->count(),
            perPage: $recordsPerPage,
            currentPage: $page,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function catalog(?string $language = null): array
    {
        $language ??= $this->language;

        return $this->catalogs[$language] ??= TranslationCatalog::for($language);
    }

    protected function missingCount(string $language): int
    {
        return collect($this->catalog($language))->where('status', TranslationCatalog::MISSING)->count();
    }

    /**
     * @return array<string, string> in the order the list is sorted by
     */
    protected static function statuses(): array
    {
        return [
            TranslationCatalog::MISSING => __('Not translated'),
            TranslationCatalog::CUSTOM => __('Custom'),
            TranslationCatalog::TRANSLATED => __('Translated'),
        ];
    }

    /**
     * What a translation has to keep for the text to come out right.
     */
    protected function translationHint(string $english): ?string
    {
        preg_match_all('/:[a-zA-Z_]+/', $english, $placeholders);

        $hints = array_filter([
            $placeholders[0] !== [] ? __('Keep :placeholders as they are: they are filled in when the text is shown.', ['placeholders' => implode(', ', array_unique($placeholders[0]))]) : null,
            str_contains($english, '|') ? __('Keep the | between the forms for different numbers.') : null,
            __('Clear it to use the shipped translation.'),
        ]);

        return implode(' ', $hints);
    }

    /**
     * "Companies" in the sidebar, "company" in "New company", "Company" on a
     * View page: one word, three strings. Renaming only one of them leaves
     * the old word in the others.
     *
     * @return list<string>
     */
    protected function otherForms(string $key): array
    {
        $words = collect([$key, Str::singular($key), Str::plural($key)])->map(fn (string $word): string => mb_strtolower($word));

        return collect(array_keys($this->catalog()))
            ->map(fn (int|string $other): string => (string) $other)
            ->filter(fn (string $other): bool => $other !== $key && $words->contains(mb_strtolower($other)))
            ->sortBy(fn (string $other): array => [mb_strtolower($other), $other])
            ->values()
            ->all();
    }

    protected function languageName(): string
    {
        return Locales::available()[$this->language] ?? $this->language;
    }

    protected function validLanguage(?string $code): string
    {
        foreach ([$code, app()->getLocale()] as $candidate) {
            if (Locales::isAvailable($candidate)) {
                return $candidate;
            }
        }

        return (string) array_key_first(Locales::available());
    }
}
