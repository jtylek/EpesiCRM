<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments;

use App\Enums\RecordPermission;
use App\Models\StoredFile;
use App\Services\FileStorage;
use BackedEnum;
use Epesi\Modules\Attachments\AttachmentsServiceProvider;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\CreateAttachment;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\EditAttachment;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\ListAttachments;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\ViewAttachment;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\Attachments\Models\AttachmentLink;
use Epesi\Modules\RecordBrowser\Filament\Infolists\SwitchEntry;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Notes — the port of Epesi's Utils/Attachment records, as full pages: the
 * addon's "add"/"edit" buttons opened the note's RecordBrowser form in place
 * of the whole page rather than in a popup, and the rich-text editor needs
 * the room.
 *
 * Every page can be opened from a record's Notes tab, carrying that record
 * along (?attachable_type=company&attachable_id=5) so it reads as part of it
 * and returns to its Notes tab — see Pages\Concerns\BelongsToOwnerRecord. The
 * list is the sidebar's Notes: every note you can see, whatever it is on.
 */
class AttachmentResource extends Resource
{
    protected static ?string $model = Attachment::class;

    protected static ?string $modelLabel = 'note';

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = 50;

    /**
     * A note is reached through a record it is on, so it is listed only while
     * one of those records is visible to you (each model's own ownership
     * scope applies inside whereHasMorph) — a public note on someone's
     * private company stays with the company. A note on nothing yet goes by
     * its own permission alone.
     */
    public static function getEloquentQuery(): Builder
    {
        $types = array_values(array_filter(array_map(
            fn (string $alias): ?string => Relation::getMorphedModel($alias),
            AttachmentsServiceProvider::$recordTypes,
        )));

        return parent::getEloquentQuery()->where(fn (Builder $query): Builder => $query
            ->whereDoesntHave('links')
            ->orWhereHas('links', fn (Builder $links): Builder => $links->whereHasMorph('attachable', $types)));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            // Only on a note started from the Notes list: from a record's
            // tab, that record is what it is attached to.
            Select::make('attach_to_type')
                ->label('Attach to')
                ->placeholder('Nothing')
                ->options(fn (): array => collect(AttachmentsServiceProvider::$recordTypes)
                    ->mapWithKeys(fn (string $type): array => [$type => Str::headline($type)])
                    ->all())
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('attach_to_id', null))
                ->visible(fn ($livewire): bool => $livewire instanceof CreateAttachment && $livewire->ownerRecord === null),
            Select::make('attach_to_id')
                ->label('Record')
                ->searchable()
                ->getSearchResultsUsing(fn (Get $get, string $search): array => static::searchRecords((string) $get('attach_to_type'), $search))
                ->getOptionLabelUsing(fn (Get $get, $value): ?string => ($record = static::findRecord((string) $get('attach_to_type'), $value))
                    ? strip_tags((string) static::recordTitle($record))
                    : null)
                ->requiredWith('attach_to_type')
                ->disabled(fn (Get $get): bool => blank($get('attach_to_type')))
                ->visible(fn ($livewire): bool => $livewire instanceof CreateAttachment && $livewire->ownerRecord === null),
            TextInput::make('title')
                ->maxLength(255)
                ->columnSpanFull(),
            RichEditor::make('note')
                ->extraInputAttributes(['class' => 'epesi-note-editor'])
                ->columnSpanFull(),
            // Straight into the file storage, which keeps a content once however
            // many notes and e-mails carry it (App\Services\FileStorage): the
            // field's state is the note's StoredFile ids, not paths on a disk.
            FileUpload::make('files')
                ->multiple()
                ->maxSize(50 * 1024)
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => (string) app(FileStorage::class)->putUpload($file)->getKey())
                ->fetchFileInformation(false)
                ->getUploadedFileUsing(fn (?Model $record, string $file): ?array => static::uploadedFile($record, $file))
                // Only the note's own files and new uploads: an id typed into
                // the request would otherwise attach someone else's file.
                ->preventFilePathTampering()
                ->columnSpanFull(),
            Select::make('permission')
                ->options(RecordPermission::class)
                ->default(RecordPermission::Public)
                ->required()
                ->selectablePlaceholder(false),
            Toggle::make('sticky')
                ->inline(false),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->inlineLabel()->components([
            TextEntry::make('title')
                ->placeholder('-')
                ->columnSpanFull(),
            // Right under the title, in a card of its own: the body is what
            // the page is for, the rows below only describe it.
            Section::make(__('Note'))
                ->compact()
                ->columnSpanFull()
                ->components([
                    TextEntry::make('note')
                        ->hiddenLabel()
                        ->inlineLabel(false)
                        ->html()
                        ->prose()
                        ->placeholder('-'),
                ]),
            // Each record a badge linking to it, as the engine renders a
            // relation field.
            LinkedRecords::style(
                TextEntry::make('attached_to')
                    ->state(fn (Attachment $record): array => array_keys(static::attachedTo($record))),
                fn (Attachment $record, string $state): ?string => static::attachedTo($record)[$state] ?? null,
            )
                ->label('Attached to')
                ->placeholder('-')
                ->columnSpanFull(),
            TextEntry::make('files')
                ->state(fn (Attachment $record): HtmlString => static::fileLinks($record))
                ->columnSpanFull(),
            TextEntry::make('permission')
                ->badge(),
            SwitchEntry::make('sticky'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::notesTable($table, attachedTo: true)
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('View'),
                EditAction::make()->iconButton()->tooltip('Edit'),
                DeleteAction::make()->iconButton()->tooltip('Delete'),
            ]);
    }

    /**
     * What the Notes list and a record's Notes tab share: sticky notes on
     * top, then newest first, as in Epesi. The tab leaves out "Attached to",
     * which there is the record itself.
     */
    public static function notesTable(Table $table, bool $attachedTo): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['creator', ...($attachedTo ? ['links.attachable'] : [])]))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByDesc('epesi_attachments.sticky')
                ->orderByDesc('epesi_attachments.updated_at'))
            ->columns([
                // Flipped right here by whoever may edit the note (the
                // column saves without asking the policy itself), which moves
                // the note to or from the top of the list.
                ToggleColumn::make('sticky')
                    ->disabled(fn (Attachment $record): bool => ! (auth()->user()?->can('update', $record) ?? false))
                    ->width('1%'),
                TextColumn::make('note')
                    ->label('Note')
                    ->state(fn (Attachment $record): HtmlString => static::preview($record))
                    ->wrap()
                    ->searchable(['title', 'note']),
                ...($attachedTo ? [
                    LinkedRecords::style(
                        TextColumn::make('attached_to')
                            ->state(fn (Attachment $record): array => array_keys(static::attachedTo($record))),
                        fn (Attachment $record, string $state): ?string => static::attachedTo($record)[$state] ?? null,
                    )
                        ->label('Attached to')
                        ->placeholder('-'),
                ] : []),
                TextColumn::make('files')
                    ->label('Files')
                    ->state(fn (Attachment $record): HtmlString => static::fileLinks($record))
                    ->placeholder('-'),
                TextColumn::make('permission')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Edited on')
                    ->description(fn (Attachment $record): string => $record->creator?->name ?? '')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('sticky')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('epesi_attachments.sticky', true)),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->toolbarActions([]);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Attachment ? $record->label() : static::getTitleCaseModelLabel();
    }

    public static function getRelations(): array
    {
        return [HistoryRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttachments::route('/'),
            'create' => CreateAttachment::route('/create'),
            'view' => ViewAttachment::route('/{record}'),
            'edit' => EditAttachment::route('/{record}/edit'),
        ];
    }

    /**
     * The query string that tells a page which record the note was opened from.
     *
     * @return array{attachable_type: string, attachable_id: int|string}
     */
    public static function ownerParameters(Model $owner): array
    {
        return [
            'attachable_type' => $owner->getMorphClass(),
            'attachable_id' => $owner->getKey(),
        ];
    }

    /**
     * The owner's View page with its Notes tab open. Filament keys a tab by
     * its slugged label, so the addon titled "Notes" is `notes::tab`.
     */
    public static function ownerUrl(Model $owner): string
    {
        return Filament::getModelResource($owner)::getUrl('view', ['record' => $owner, 'tab' => 'notes::tab']);
    }

    /**
     * A record of one of the types notes go on, through its own query so its
     * ownership scope applies: you can only attach to a record you can see.
     */
    public static function findRecord(string $type, mixed $id): ?Model
    {
        $class = in_array($type, AttachmentsServiceProvider::$recordTypes, true) ? Relation::getMorphedModel($type) : null;

        return $class && is_scalar($id) ? $class::query()->find($id) : null;
    }

    /**
     * Searched on whatever the type's own resource searches globally.
     *
     * @return array<int|string, string>
     */
    protected static function searchRecords(string $type, string $search): array
    {
        $class = in_array($type, AttachmentsServiceProvider::$recordTypes, true) ? Relation::getMorphedModel($type) : null;
        $resource = $class ? Filament::getModelResource($class) : null;

        if ($resource === null) {
            return [];
        }

        return $class::query()
            ->where(function (Builder $query) use ($resource, $search): void {
                foreach ($resource::getGloballySearchableAttributes() as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            })
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => strip_tags((string) static::recordTitle($record))])
            ->all();
    }

    protected static function recordTitle(Model $record): string|Htmlable|null
    {
        return Filament::getModelResource($record)::getRecordTitle($record);
    }

    /**
     * Title on the first line, the start of the body under it — Epesi's
     * display_note() "tall preview".
     */
    protected static function preview(Attachment $record): HtmlString
    {
        $body = Str::limit(trim(html_entity_decode(strip_tags((string) $record->note))), 200);
        $title = filled($record->title) ? '<strong>'.e($record->title).'</strong>' : '';

        return new HtmlString(implode('<br>', array_filter([$title, e($body)])));
    }

    protected static function fileLinks(Attachment $record): HtmlString
    {
        $links = $record->storedFiles()->map(fn (StoredFile $file): string => sprintf(
            '<a href="%s" class="text-primary-600 underline" target="_blank">%s</a>',
            e(route('epesi.attachments.download', ['attachment' => $record->getKey(), 'file' => $file->getKey()])),
            e($file->name),
        ));

        return new HtmlString($links->implode('<br>') ?: '-');
    }

    /**
     * What the upload field shows for a file the note already has.
     *
     * @return array{name: string, size: int, type: ?string, url: ?string}|null
     */
    protected static function uploadedFile(?Model $record, string $id): ?array
    {
        $file = StoredFile::query()->with('content')->find($id);

        if ($file === null) {
            return null;
        }

        return [
            'name' => $file->name,
            'size' => $file->size(),
            'type' => $file->mimeType(),
            'url' => $record ? route('epesi.attachments.download', ['attachment' => $record->getKey(), 'file' => $file->getKey()]) : null,
        ];
    }

    /**
     * "Phone Call: Postponed: CCN Sliver Bullet" for each record the note is
     * on that you can see, keyed to that record's View page — the morphTo load
     * applies each model's ownership scope, so a hidden one comes back null
     * and is left out.
     *
     * @return array<string, string|null>
     */
    protected static function attachedTo(Attachment $record): array
    {
        return $record->links
            ->filter(fn (AttachmentLink $link): bool => $link->attachable !== null)
            ->mapWithKeys(function (AttachmentLink $link): array {
                $related = $link->attachable;
                $resource = Filament::getModelResource($related::class);
                $label = Str::headline($link->attachable_type).': '.($resource
                    ? strip_tags((string) $resource::getRecordTitle($related))
                    : '#'.$related->getKey());

                return [$label => $resource && $resource::hasPage('view') ? $resource::getUrl('view', ['record' => $related]) : null];
            })
            ->all();
    }
}
