<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments;

use App\Enums\NoteFormat;
use App\Enums\RecordPermission;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\FileStorage;
use App\Support\Files\FileChip;
use BackedEnum;
use Closure;
use Epesi\Modules\Attachments\AttachmentsServiceProvider;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\CreateAttachment;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\EditAttachment;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\ListAttachments;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\ViewAttachment;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\Attachments\Models\AttachmentLink;
use Epesi\Modules\Attachments\Services\LegacyNoteCipher;
use Epesi\Modules\RecordBrowser\Filament\Infolists\SwitchEntry;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInput\Password;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ClosureValidationRule;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\Activitylog\Models\Activity;
use Throwable;
use UnitEnum;

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

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

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
            AttachmentsServiceProvider::recordTypes(),
        )));

        return parent::getEloquentQuery()->where(fn (Builder $query): Builder => $query
            ->whereDoesntHave('links')
            ->orWhereHas('links', fn (Builder $links): Builder => $links->whereHasMorph('attachable', $types)));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')
                ->maxLength(255)
                ->columnSpanFull(),
            // HTML (rich text) or Markdown, chosen here per note: the format is
            // stored with the note so it renders the way it was written. The
            // switch has a third button, Preview, which shows the note as it
            // will read without changing the format. The two editors keep
            // their own state and the switch converts one into the other
            // (NoteFormat::convert()); the pages save the visible one as
            // `note` (foldNoteState()).
            Hidden::make('format')
                ->default(fn (): NoteFormat => NoteFormat::default()),
            ToggleButtons::make('editor')
                ->hiddenLabel()
                ->options([
                    NoteFormat::Html->value => __('HTML'),
                    NoteFormat::Markdown->value => __('Markdown'),
                    'preview' => __('Preview'),
                ])
                ->default(fn (): string => NoteFormat::default()->value)
                ->inline()
                ->grouped()
                ->required()
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                    $to = NoteFormat::tryFrom((string) $state);
                    $from = NoteFormat::fromState($get('format'));

                    if ($to === null || $from === null || $from === $to) {
                        return;
                    }

                    $text = $from === NoteFormat::Markdown ? (string) $get('note_markdown') : static::editorHtml($get('note'));

                    $set('format', $to->value);
                    $set($to === NoteFormat::Markdown ? 'note_markdown' : 'note', $from->convert($text, $to));
                })
                ->columnSpanFull(),
            RichEditor::make('note')
                ->hiddenLabel()
                ->extraInputAttributes(['class' => 'epesi-note-editor'])
                ->visible(fn (Get $get): bool => $get('editor') !== 'preview' && NoteFormat::fromState($get('format')) !== NoteFormat::Markdown)
                ->dehydratedWhenHidden()
                ->columnSpanFull(),
            MarkdownEditor::make('note_markdown')
                ->hiddenLabel()
                ->visible(fn (Get $get): bool => $get('editor') !== 'preview' && NoteFormat::fromState($get('format')) === NoteFormat::Markdown)
                ->dehydrated(false)
                ->columnSpanFull(),
            Section::make(__('Encryption'))
                ->compact()
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    Toggle::make('encrypt_note')
                        ->label('Encrypt this note with a password')
                        ->default(fn (?Attachment $record): bool => (bool) $record?->legacy_encrypted)
                        ->live()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    TextInput::make('encryption_password')
                        ->label('Password')
                        ->password()
                        ->revealable(false)
                        ->autocomplete('new-password')
                        ->helperText('The password is not stored in Epesi. Enter it again when saving an encrypted note.')
                        ->visible(fn (Get $get): bool => (bool) $get('encrypt_note')),
                    TextInput::make('encryption_password_confirmation')
                        ->label('Confirm Password')
                        ->password()
                        ->revealable(false)
                        ->autocomplete('new-password')
                        ->visible(fn (Get $get): bool => (bool) $get('encrypt_note')),
                    TextInput::make('legacy_password_hint')
                        ->label('Password Hint')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('encrypt_note'))
                        ->columnSpanFull(),
                ]),
            Section::make(__('Preview'))
                ->compact()
                ->visible(fn (Get $get): bool => $get('editor') === 'preview')
                ->columnSpanFull()
                ->components([
                    TextEntry::make('note_preview')
                        ->hiddenLabel()
                        ->state(fn (Get $get): HtmlString => new HtmlString(
                            NoteFormat::fromState($get('format')) === NoteFormat::Markdown
                                ? NoteFormat::Markdown->toHtml((string) $get('note_markdown'))
                                : static::editorHtml($get('note')),
                        ))
                        ->html()
                        ->prose(),
                ]),
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
                // The label beside the drop zone, laid out like "Attached to".
                ->inlineLabel()
                ->extraFieldWrapperAttributes(['class' => 'epesi-attach-to'])
                ->columnSpanFull(),
            // The records the note is on, as Epesi's "Attached to" multiselect
            // (a note can be on several) — not virtual columns of the note:
            // the pages read it out of the form's data (attachedToState()) and
            // put it back with syncAttachedTo(). A note is on at least one
            // record. A table, so each record is one line with its delete icon
            // at the end and the labels once above the columns; the button to
            // add one is on the label's line (a hint action, in place of the
            // repeater's own below the table) together with the warning when
            // there is none (attachedToLabel()). Left out only on a note
            // started from a record's tab, where that record is what it is
            // attached to; editing one from the tab still shows it, so it can
            // be moved. Each Select's own validation refuses a record you
            // can't see: its option label comes back blank.
            Repeater::make('attach_to')
                ->label(fn (Repeater $component, $livewire): Htmlable => static::attachedToLabel(
                    (string) $livewire->getErrorBag()->first($component->getStatePath()),
                ))
                ->markAsRequired(false)
                ->inlineLabel()
                // Filament names a field in its messages after its label,
                // which is markup here.
                ->validationAttribute(mb_strtolower(__('Attached to')))
                ->extraFieldWrapperAttributes(['class' => 'epesi-attach-to'])
                ->required()
                ->minItems(1)
                // A note started from the Notes list begins on the signed-in
                // user's own contact, the usual thing to attach it to.
                ->default(fn (): array => [static::ownContactRow()])
                ->addable(false)
                ->hintAction(
                    Action::make('attachToRecord')
                        ->label('Attach to a record')
                        ->color('gray')
                        ->size(Size::ExtraSmall)
                        ->button()
                        ->action(function (Get $get, Set $set): void {
                            $rows = $get('attach_to') ?? [];
                            $rows[(string) Str::uuid()] = ['type' => null, 'id' => null];
                            $set('attach_to', $rows);
                        }),
                )
                ->reorderable(false)
                ->compact()
                ->columnSpanFull()
                ->table([
                    TableColumn::make(__('Type'))->markAsRequired()->width('30%'),
                    TableColumn::make(__('Record'))->markAsRequired(),
                ])
                ->schema([
                    Select::make('type')
                        ->options(fn (): array => collect(AttachmentsServiceProvider::recordTypes())
                            ->mapWithKeys(fn (string $type): array => [$type => Str::headline($type)])
                            ->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('id', null)),
                    Select::make('id')
                        ->searchable()
                        ->required()
                        ->getSearchResultsUsing(fn (Get $get, string $search): array => static::searchRecords((string) $get('type'), $search))
                        ->getOptionLabelUsing(fn (Get $get, $value): ?string => ($record = static::findRecord((string) $get('type'), $value))
                            ? strip_tags((string) static::recordTitle($record))
                            : null)
                        ->disabled(fn (Get $get): bool => blank($get('type'))),
                ])
                ->visible(fn ($livewire): bool => ! ($livewire instanceof CreateAttachment && $livewire->ownerRecord !== null)),
            Select::make('permission')
                ->options(RecordPermission::class)
                ->default(RecordPermission::Public)
                ->required()
                ->selectablePlaceholder(false)
                ->inlineLabel()
                ->extraFieldWrapperAttributes(['class' => 'epesi-inline-centered']),
            Toggle::make('sticky')
                ->inlineLabel()
                ->extraFieldWrapperAttributes(['class' => 'epesi-inline-centered']),
        ]);
    }

    /**
     * The form holds the note in two editors; this puts the Markdown one's
     * text into `note`, which is what is saved, when Markdown is the format.
     * `note_markdown` is not dehydrated, so the pages hand it over from the
     * raw form state.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function foldNoteState(array $data, mixed $markdown): array
    {
        if (NoteFormat::fromState($data['format'] ?? null) === NoteFormat::Markdown) {
            $data['note'] = (string) $markdown;
        }

        return $data;
    }

    /** Apply the separate password controls to note data before it reaches the model. */
    public static function applyEncryptionState(array $data, array $state): array
    {
        $encrypted = (bool) ($state['encrypt_note'] ?? false);
        $password = (string) ($state['encryption_password'] ?? '');
        $confirmation = (string) ($state['encryption_password_confirmation'] ?? '');
        unset($data['encrypt_note'], $data['encryption_password'], $data['encryption_password_confirmation']);

        if (! $encrypted) {
            $data['legacy_encrypted'] = false;
            $data['legacy_password_hint'] = null;

            return $data;
        }

        if ($password !== '' && ! hash_equals($password, $confirmation)) {
            throw ValidationException::withMessages([
                'data.encryption_password_confirmation' => __('Password confirmation does not match.'),
            ]);
        }

        if ($password === '') {
            throw ValidationException::withMessages([
                'data.encryption_password' => __('Please provide a password to encrypt this note.'),
            ]);
        }

        $data['note'] = app(LegacyNoteCipher::class)->encrypt(
            (string) ($data['note'] ?? ''),
            $password,
            (string) ($state['legacy_password_hint'] ?? ''),
        );
        $data['legacy_encrypted'] = true;
        $data['legacy_password_hint'] = filled($state['legacy_password_hint'] ?? null)
            ? (string) $state['legacy_password_hint']
            : null;

        return $data;
    }

    /**
     * The rich-text editor's state as HTML: a string, or the editor's own
     * JSON document once something has been typed.
     */
    public static function editorHtml(mixed $state): string
    {
        return is_array($state) ? RichContentRenderer::make($state)->toHtml() : (string) $state;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->inlineLabel()->components([
            TextEntry::make('title')
                ->placeholder('-')
                ->weight(FontWeight::Bold)
                ->columnSpanFull(),
            // The Decrypt note button sits on this row's label; once the
            // password worked, the row confirms it in green.
            TextEntry::make('legacy_encrypted')
                ->hidden(fn (Attachment $record): bool => ! $record->legacy_encrypted)
                ->label('Encryption')
                ->state(fn (Attachment $record, $livewire): string => match (true) {
                    $livewire instanceof ViewAttachment && $livewire->getDecryptedLegacyNoteHtml() !== null => __('Password is correct'),
                    filled($record->legacy_password_hint) => __('Encrypted. Password hint: :hint', ['hint' => $record->legacy_password_hint]),
                    default => __('Password protected'),
                })
                ->color(fn ($livewire): ?string => $livewire instanceof ViewAttachment && $livewire->getDecryptedLegacyNoteHtml() !== null ? 'success' : null)
                ->icon(fn ($livewire): ?Heroicon => $livewire instanceof ViewAttachment && $livewire->getDecryptedLegacyNoteHtml() !== null ? Heroicon::OutlinedCheckCircle : null)
                ->hintAction(
                    Action::make('decryptLegacyNote')
                        ->label('Decrypt note')
                        ->icon(Heroicon::OutlinedLockOpen)
                        ->button()
                        ->visible(fn ($livewire): bool => $livewire instanceof ViewAttachment && $livewire->getDecryptedLegacyNoteHtml() === null)
                        ->modalDescription(fn (Attachment $record): ?string => filled($record->legacy_password_hint)
                            ? __('Password hint: :hint', ['hint' => $record->legacy_password_hint])
                            : null)
                        ->modalSubmitActionLabel(__('Decrypt'))
                        ->schema(fn (Attachment $record): array => [static::notePasswordInput($record)])
                        ->action(function (array $data, Attachment $record, ViewAttachment $livewire): void {
                            $livewire->showDecryptedLegacyNote($record, (string) $data['password']);
                        }),
                )
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
                        ->visible(fn (Attachment $record): bool => ! $record->legacy_encrypted)
                        ->state(fn (Attachment $record): string => $record->bodyHtml())
                        ->html()
                        ->prose()
                        ->extraAttributes(['style' => 'overflow-wrap: anywhere'])
                        ->placeholder('-'),
                    ViewEntry::make('legacy_note_body')
                        ->hiddenLabel()
                        ->visible(fn (Attachment $record): bool => $record->legacy_encrypted)
                        ->view('epesi-attachments::encrypted-note-body')
                        ->state(fn (Attachment $record): string => filled($record->legacy_password_hint)
                            ? __('This note is password protected. Hint: :hint', ['hint' => $record->legacy_password_hint])
                            : __('This note is password protected. Enter its password using Decrypt note.')),
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

    /**
     * The Decrypt note dialog's password field. A wrong password is a
     * validation error under the field, so the dialog stays open to retry
     * or cancel.
     */
    public static function notePasswordInput(Attachment $record): TextInput
    {
        return TextInput::make('password')
            ->password()
            ->revealable(false)
            ->required()
            ->label('Note password')
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                if (app(LegacyNoteCipher::class)->decrypt((string) $record->note, (string) $value) === null) {
                    $fail(__('Invalid password'));
                }
            });
    }

    public static function table(Table $table): Table
    {
        return static::notesTable($table, attachedTo: true)
            // Filament otherwise defaults a resource list's row link to the
            // View action's URL (ListRecords::makeTable()); an explicit null
            // opts out, since clicking the preview expands it in place
            // instead (see preview()) — the eye icon is the only way in.
            ->recordUrl(null)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    /**
     * What the Notes list and a record's Notes tab share: sticky notes on
     * top, then newest first, as in Epesi. "Attached to" (the tab leaves it
     * out, which there is the record itself), "Files" and "Edited on" render
     * as lines under the note itself (preview()) rather than their own
     * columns, so Note is nearly the whole row — none of the three is a real
     * column any more, so none has a sortable header; the default sort above
     * still applies. Only a record's Notes tab shows the live Sticky toggle;
     * the standalone Notes list leaves that column out. No column selector:
     * both remaining columns opt out of
     * `AppServiceProvider::giveEveryAddonAColumnSelector()`, since there's
     * nothing worth hiding on a table that's essentially one column.
     */
    public static function notesTable(Table $table, bool $attachedTo): Table
    {
        $dateInput = static function (string $name, string $label): DatePicker|TextInput {
            if (RegionalSetting::calendarSystem() === 'gregorian') {
                $input = DatePicker::make($name)->label($label);

                return $name === 'until' ? $input->afterOrEqual('from') : $input;
            }

            $input = TextInput::make($name)
                ->label($label)
                ->placeholder('YYYY-MM-DD')
                ->inputMode('numeric')
                ->formatStateUsing(fn (mixed $state): ?string => RegionalSetting::formatDateInput($state))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? RegionalSetting::parseDateInput($state) : null)
                ->rules([new ClosureValidationRule(function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        RegionalSetting::parseDateInput((string) $value);
                    } catch (Throwable) {
                        $fail(__('Enter a valid date in YYYY-MM-DD format.'));
                    }
                })]);

            return $name === 'until' ? $input->afterOrEqual('from') : $input;
        };

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['creator', 'latestEdit.causer', ...($attachedTo ? ['links.attachable'] : [])]))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByDesc('epesi_attachments.sticky')
                ->orderByDesc('epesi_attachments.updated_at'))
            ->columns([
                // Flipped right here by whoever may edit the note (the
                // column saves without asking the policy itself), which moves
                // the note to or from the top of the list.
                ToggleColumn::make('sticky')
                    ->visible(! $attachedTo)
                    ->disabled(fn (Attachment $record): bool => ! (auth()->user()?->can('update', $record) ?? false))
                    ->toggleable(false)
                    ->width('1%'),
                TextColumn::make('note')
                    ->label('Note')
                    ->state(fn (Attachment $record): HtmlString => static::preview($record, $attachedTo))
                    ->wrap()
                    ->searchable(['title', 'note'])
                    ->toggleable(false),
            ])
            ->filters($attachedTo ? [
                Filter::make('has_attachments')
                    ->label('Has Attachments')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereJsonLength('epesi_attachments.files', '>', 0)),
                static::editedByFilter(),
                Filter::make('encrypted')
                    ->label('Encrypted notes')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('epesi_attachments.legacy_encrypted', true)),
                Filter::make('updated_at')
                    ->label('Edited on')
                    ->schema([
                        $dateInput('from', 'Edited on from'),
                        $dateInput('until', 'Edited on until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('epesi_attachments.updated_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('epesi_attachments.updated_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make(__('Edited on from').': '.$data['from'])->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make(__('Edited on until').': '.$data['until'])->removeField('until');
                        }

                        return $indicators;
                    }),
            ] : [
                static::editedByFilter(),
                Filter::make('sticky')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('epesi_attachments.sticky', true)),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->toolbarActions([]);
    }

    protected static function editedByFilter(): SelectFilter
    {
        return SelectFilter::make('edited_by')
            ->label('Edited by')
            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['value'] ?? null, fn (Builder $query, $userId): Builder => $query
                    ->where(fn (Builder $query): Builder => $query
                        ->whereHas('latestEdit', fn (Builder $query): Builder => $query
                            ->where('causer_type', (new User)->getMorphClass())
                            ->where('causer_id', $userId))
                        ->orWhere(fn (Builder $query): Builder => $query
                            ->whereDoesntHave('latestEdit')
                            ->where('epesi_attachments.created_by', $userId)))));
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Attachment ? $record->label() : static::getTitleCaseModelLabel();
    }

    public static function getRelations(): array
    {
        return [HistoryRelationManager::class];
    }

    /**
     * Not built on the RecordBrowser engine (its RichEditor and file upload
     * need a bespoke form), so History has nothing to read a field's label or
     * value from unless this hands them over — same Field objects a recordset
     * declares, used here only for their labels/formatLoggedValue(), never a
     * form or column.
     *
     * @return array<int, Field>
     */
    public static function historyFields(): array
    {
        return [
            Field::text('title'),
            Field::longText('note')->richText(),
            Field::text('format'),
            Field::select('permission', RecordPermission::class),
            Field::boolean('sticky'),
            Field::make('files', FieldType::Multiselect)
                ->historyUsing(fn (mixed $old, mixed $new, Activity $entry): ?HtmlString => static::historyFileChanges(
                    (array) $old,
                    (array) $new,
                    (array) $entry->properties->get('file_names', []),
                )),
        ];
    }

    /**
     * A change of a note's files for its History tab: the files taken off,
     * struck through, and the ones added, by name. The names are the ones
     * logged with the change (Attachment::tapActivity()). An entry logged
     * before that holds ids alone, looked up in the file storage, where a file
     * taken off the note is gone.
     *
     * @param  array<int|string, int|string>  $old
     * @param  array<int|string, int|string>  $new
     * @param  array<int|string, string>  $names  id => name
     */
    public static function historyFileChanges(array $old, array $new, array $names): ?HtmlString
    {
        $old = array_map('strval', $old);
        $new = array_map('strval', $new);
        $removed = array_diff($old, $new);
        $added = array_diff($new, $old);

        if ($removed === [] && $added === []) {
            return null;
        }

        $unnamed = array_diff([...$removed, ...$added], array_map('strval', array_keys($names)));

        if ($unnamed !== []) {
            $names += StoredFile::query()->whereKey($unnamed)->pluck('name', 'id')->all();
        }

        $name = fn (string $id): string => e($names[$id] ?? __('deleted file'));

        return new HtmlString(implode(' ', [
            ...array_map(fn (string $id): string => '<del class="epesi-history-old">− '.$name($id).'</del>', $removed),
            ...array_map(fn (string $id): string => '<ins class="epesi-history-new">+ '.$name($id).'</ins>', $added),
        ]));
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
     * The "Attached to" row for the signed-in user's contact; a blank one when
     * they have none, or can't attach notes to contacts.
     *
     * @return array{type: ?string, id: ?int}
     */
    protected static function ownContactRow(): array
    {
        $contact = auth()->user()?->contact;

        if ($contact && static::findRecord('contact', $contact->getKey())) {
            return ['type' => 'contact', 'id' => $contact->getKey()];
        }

        return ['type' => null, 'id' => null];
    }

    /**
     * A record of one of the types notes go on, through its own query so its
     * ownership scope applies: you can only attach to a record you can see.
     */
    public static function findRecord(string $type, mixed $id): ?Model
    {
        $class = in_array($type, AttachmentsServiceProvider::recordTypes(), true) ? Relation::getMorphedModel($type) : null;

        return $class && is_scalar($id) ? $class::query()->find($id) : null;
    }

    /**
     * "Attached to" with its warning after it on the same line, when the field
     * has one. The label is where it goes because the field's own message
     * comes under the table, out of sight on a long note; that one is hidden
     * (AttachmentsPlugin). The required mark is drawn here too, since Filament
     * would put it after the warning.
     */
    public static function attachedToLabel(string $error): Htmlable
    {
        return new HtmlString(e(__('Attached to')).'<sup class="fi-fo-field-label-required-mark">*</sup>'
            .($error !== '' ? '<span class="epesi-attach-to-error">'.e($error).'</span>' : ''));
    }

    /**
     * The form's "Attached to" rows for a note: the records it is on that a
     * note can be put on and you can see.
     *
     * @return array<int, array{type: string, id: int|string}>
     */
    public static function attachedToState(Attachment $note): array
    {
        return static::editableLinks($note)
            ->map(fn (AttachmentLink $link): array => ['type' => $link->attachable_type, 'id' => $link->attachable_id])
            ->all();
    }

    /**
     * Puts the note on the records the form's "Attached to" rows name, and
     * takes it off the ones it showed that are no longer among them. A record
     * the form couldn't show (one you can't see, or of a type notes aren't put
     * on) keeps its link: leaving it out of the rows was never a choice.
     *
     * @param  array<int|string, array{type?: string, id?: int|string|null}>  $rows
     */
    public static function syncAttachedTo(Attachment $note, array $rows): void
    {
        $wanted = collect($rows)
            ->map(fn (array $row): ?Model => static::findRecord((string) ($row['type'] ?? ''), $row['id'] ?? null))
            ->filter();

        foreach (static::editableLinks($note) as $link) {
            $stays = $wanted->contains(fn (Model $record): bool => $record->getMorphClass() === $link->attachable_type
                && (string) $record->getKey() === (string) $link->attachable_id);

            if (! $stays) {
                $link->delete();
            }
        }

        $wanted->each(fn (Model $record) => $note->attachTo($record));
    }

    /**
     * The links the "Attached to" field can show and change. The morphTo load
     * applies each model's ownership scope, so a record you can't see comes
     * back null and is left out.
     *
     * @return Collection<int, AttachmentLink>
     */
    protected static function editableLinks(Attachment $note): Collection
    {
        $types = array_filter(AttachmentsServiceProvider::recordTypes(), fn (string $type): bool => Relation::getMorphedModel($type) !== null);

        return $note->links()
            ->whereIn('attachable_type', $types)
            ->with('attachable')
            ->get()
            ->filter(fn (AttachmentLink $link): bool => $link->attachable !== null)
            ->values();
    }

    /**
     * Searched on whatever the type's own resource searches globally, its own
     * columns only: a dot path ("addresses.city") names a relation's.
     *
     * @return array<int|string, string>
     */
    protected static function searchRecords(string $type, string $search): array
    {
        $class = in_array($type, AttachmentsServiceProvider::recordTypes(), true) ? Relation::getMorphedModel($type) : null;
        $resource = $class ? Filament::getModelResource($class) : null;

        if ($resource === null) {
            return [];
        }

        return $class::query()
            ->where(function (Builder $query) use ($resource, $search): void {
                foreach ($resource::getGloballySearchableAttributes() as $column) {
                    if (! str_contains($column, '.')) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
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
     * display_note() "tall preview". Clicking it swaps in the full note
     * (title + rich text, same markup the view page renders) right there in
     * the table, and clicking that collapses it back — an expand in place,
     * not a navigation, so View (the eye icon) stays the one way to reach the
     * note's own page. Files, "Attached to" and "Edited on" sit underneath,
     * outside the toggle, so they show either way (metaLines()).
     */
    protected static function preview(Attachment $record, bool $attachedTo): HtmlString
    {
        $title = filled($record->title) ? e($record->title) : '';
        $body = Str::limit($record->plainText(), 200);
        $collapsed = implode('<br>', array_filter([$title !== '' ? "<strong>{$title}</strong>" : '', e($body)]));

        $full = implode('', array_filter([
            $title !== '' ? "<div class=\"epesi-note-full-title\">{$title}</div>" : '',
            '<div class="fi-prose epesi-note-full-body">'.$record->bodyHtml().'</div>',
        ]));

        return new HtmlString(
            '<div class="epesi-note-preview" x-data="{ expanded: false }">'
            .'<div class="epesi-note-toggle" x-show="!expanded" x-on:click="expanded = true">'.$collapsed.'</div>'
            .'<div class="epesi-note-toggle" x-show="expanded" x-cloak x-on:click="expanded = false">'.$full.'</div>'
            .static::metaLines($record, $attachedTo)
            .'</div>'
        );
    }

    /**
     * Under the note, one plain line each — instead of columns of their own,
     * so Note keeps nearly the whole row: the files (a count badge, then each
     * file's chip exactly as the view page shows it; no label, the badge says
     * what they are, and no line at all for a note without files), "Attached
     * to" (list only — the tab's record is what it's attached to) and
     * Edited on.
     */
    protected static function metaLines(Attachment $record, bool $attachedTo): string
    {
        $files = $record->storedFiles();
        $lines = [];

        if ($files->isNotEmpty()) {
            $count = $files->count();
            $lines[] = '<div class="epesi-note-meta-line">'
                .static::metaChip(trans_choice('{1} :count file|[2,*] :count files', $count, ['count' => $count]), null)
                .$files->map(fn (StoredFile $file): string => static::fileChip($record, $file))->implode('')
                .'</div>';
        }

        if ($attachedTo) {
            $chips = collect(static::attachedTo($record))
                ->map(fn (?string $url, string $label): string => static::metaChip($label, $url))
                ->implode('');

            if ($chips !== '') {
                $lines[] = static::metaLine(__('Attached to'), $chips);
            }
        }

        $editedOn = e($record->updated_at ? RegionalSetting::toUser($record->updated_at)->translatedFormat(RegionalSetting::dateTimeFormat(true)) : '');
        $editedBy = $record->latestEdit ? $record->latestEdit->causer : $record->creator;
        $editor = $editedBy instanceof User ? ' <span class="epesi-note-meta-by">'.e($editedBy->name).'</span>' : '';
        $lines[] = static::metaLine(__('Edited on'), $editedOn.$editor);

        return '<div class="epesi-note-meta">'.implode('', $lines).'</div>';
    }

    protected static function metaLine(string $label, string $value): string
    {
        return '<div class="epesi-note-meta-line">'.static::metaGroup($label, $value).'</div>';
    }

    protected static function metaGroup(string $label, string $value): string
    {
        return '<span class="epesi-note-meta-group"><span class="epesi-note-meta-label">'.e($label).'</span>'.$value.'</span>';
    }

    protected static function metaChip(string $label, ?string $url): string
    {
        if ($url === null) {
            return '<span class="epesi-note-meta-chip">'.e($label).'</span>';
        }

        return sprintf(
            '<a href="%s" class="epesi-note-meta-chip">%s %s</a>',
            e($url),
            e($label),
            static::icon('heroicon-o-link'),
        );
    }

    protected static function fileLinks(Attachment $record): HtmlString
    {
        $chips = $record->storedFiles()->map(fn (StoredFile $file): string => static::fileChip($record, $file));

        return new HtmlString($chips->implode('') ?: '-');
    }

    /**
     * One file: a type icon and its name, clicking either opens it (a
     * preview for a type isPreviewable() can render, a download otherwise —
     * same fallback the plain filename link always was), plus explicit
     * View/Download/Get link actions for anyone who wants a specific one.
     * The chip itself is FileChip::render() — shared with the Mail archive,
     * which serves the same StoredFile model behind its own download route.
     */
    protected static function fileChip(Attachment $record, StoredFile $file): string
    {
        $downloadUrl = route('epesi.attachments.download', ['attachment' => $record->getKey(), 'file' => $file->getKey()]);
        $viewUrl = match (true) {
            static::isMarkdown($file) => route('epesi.attachments.markdown', ['attachment' => $record->getKey(), 'file' => $file->getKey()]),
            static::isHtml($file) => route('epesi.attachments.html', ['attachment' => $record->getKey(), 'file' => $file->getKey()]),
            $file->isPreviewable() => route('epesi.attachments.download', ['attachment' => $record->getKey(), 'file' => $file->getKey(), 'preview' => 1]),
            default => null,
        };

        return FileChip::render($file, $downloadUrl, $viewUrl, static::shareLinkButton($record, $file));
    }

    /**
     * "Get link": a signed URL good for whoever holds it, login or not, for
     * a week — for pasting into an e-mail or handing to someone outside the
     * CRM (SharedFileController, the `signed` route middleware is the only
     * gate). The button itself is FileChip::shareButton() — shared with the
     * Mail archive's own "Get link", which signs the same download route
     * instead of a route of its own (MailAttachment::shareUrl()).
     */
    protected static function shareLinkButton(Attachment $record, StoredFile $file): string
    {
        return FileChip::shareButton(URL::temporarySignedRoute('epesi.attachments.shared', now()->addWeek(), [
            'attachment' => $record->getKey(),
            'file' => $file->getKey(),
        ]));
    }

    protected static function icon(string $name): string
    {
        return FileChip::icon($name);
    }

    protected static function isMarkdown(StoredFile $file): bool
    {
        return in_array(strtolower(pathinfo($file->name, PATHINFO_EXTENSION)), ['md', 'markdown'], true);
    }

    protected static function isHtml(StoredFile $file): bool
    {
        return in_array(strtolower(pathinfo($file->name, PATHINFO_EXTENSION)), ['html', 'htm'], true);
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
