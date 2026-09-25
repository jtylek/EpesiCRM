<?php

namespace Epesi\Modules\Reminders\Filament\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use App\Models\User;
use Carbon\CarbonInterface;
use Epesi\Modules\Reminders\Models\Reminder;
use Epesi\Modules\Reminders\Reminders;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The "Reminders" addon on tasks, meetings and phone calls — the port of
 * Utils_Messenger::body() and edit(): a list of the record's alerts and a
 * form to add one, "N minutes/hours/days before" the record's time (the
 * quick "messenger_before" choice Epesi offered on a new meeting) or at a
 * fixed date and time (the addon's own form).
 *
 * Staff see the reminders they set or receive; managers see all of them.
 */
class RemindersRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'reminders';

    protected static ?string $title = 'Reminders';

    protected static ?string $modelLabel = 'reminder';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedBellAlert;

    /** @var array<int, int>|null */
    protected ?array $eligibleRecipientIds = null;

    public function form(Schema $schema): Schema
    {
        $start = $this->startTime();

        return $schema->columns(2)->components([
            ToggleButtons::make('timing')
                ->label('When')
                ->options([
                    'before' => $start ? 'Before '.$start->format('Y-m-d H:i') : 'Before (this record has no time set)',
                    'at' => __('At a fixed date and time'),
                ])
                ->disableOptionWhen(fn (string $value): bool => $value === 'before' && $start === null)
                ->default($start ? 'before' : 'at')
                ->inline()
                ->live()
                ->required()
                ->columnSpanFull(),
            TextInput::make('before_amount')
                ->label('How long before')
                ->integer()
                ->minValue(0)
                ->maxValue(100000)
                ->default(15)
                ->required()
                ->visible(fn (Get $get): bool => $get('timing') === 'before'),
            Select::make('before_unit')
                ->label('Unit')
                ->options(['minutes' => __('minutes'), 'hours' => __('hours'), 'days' => 'days'])
                ->default('minutes')
                ->selectablePlaceholder(false)
                ->required()
                ->visible(fn (Get $get): bool => $get('timing') === 'before'),
            DateTimePicker::make('remind_at')
                ->label('Remind at')
                ->seconds(false)
                ->default(fn (): string => ($start ?? now()->addHour())->copy()->startOfMinute()->toDateTimeString())
                ->required()
                ->visible(fn (Get $get): bool => $get('timing') === 'at')
                ->columnSpanFull(),
            Select::make('recipients')
                ->label('Remind')
                ->relationship(
                    name: 'recipients',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->whereKey($this->eligibleRecipientIds())
                        ->with('contact'),
                )
                ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->displayName())
                ->multiple()
                ->preload()
                ->default(fn (): array => [Auth::id()])
                ->required()
                ->helperText(__('Only colleagues who can see this record are listed.'))
                ->columnSpanFull(),
            Textarea::make('message')
                ->rows(3)
                ->maxLength(2000)
                ->columnSpanFull(),
            Toggle::make('send_email')
                ->label('Also send by e-mail')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Reminder $record): string => 'reminder at '.$record->remind_at->format('Y-m-d H:i'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->visibleReminders($query)
                ->with(['recipients.contact', 'creator.contact']))
            ->defaultSort('remind_at')
            ->columns([
                TextColumn::make('remind_at')
                    ->label('Remind at')
                    ->dateTime('Y-m-d H:i')
                    ->description(fn (Reminder $record): string => $record->timingLabel())
                    ->color(fn (Reminder $record): ?string => $record->remind_at->isPast() ? 'gray' : null)
                    ->sortable(),
                TextColumn::make('message')
                    ->placeholder(__('-'))
                    ->wrap()
                    ->limit(120),
                TextColumn::make('recipient_names')
                    ->label('Remind')
                    ->state(fn (Reminder $record): array => $record->recipients
                        ->map(fn (User $user): string => $user->displayName().($user->pivot->sent_at ? ' ✓' : ''))
                        ->all())
                    ->badge()
                    ->color('gray')
                    ->tooltip(__('✓ = delivered')),
                IconColumn::make('send_email')
                    ->label('E-mail')
                    ->boolean(),
                TextColumn::make('creator_name')
                    ->label('Set by')
                    ->state(fn (Reminder $record): ?string => $record->creator?->displayName())
                    ->placeholder(__('-'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New reminder')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading(__('New reminder'))
                    ->mutateDataUsing(fn (array $data, Action $action): array => $this->toAttributes($data, $action)),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit'))
                    ->mutateRecordDataUsing(fn (array $data, Reminder $record): array => $this->toFormData($data, $record))
                    ->mutateDataUsing(fn (array $data, Action $action): array => $this->toAttributes($data, $action)),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('No reminders'))
            ->emptyStateDescription(__('Add one to be reminded of this record at a chosen time.'));
    }

    /**
     * Filament makes every addon on a View page read-only by default;
     * reminders are set from the record's page, as in Epesi.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function visibleReminders(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user?->hasAnyRole(['super_admin', 'manager'])) {
            return $query;
        }

        return $query->where(fn (Builder $query): Builder => $query
            ->where('epesi_reminders.created_by', $user?->getKey())
            ->orWhereHas('recipientRows', fn (Builder $q) => $q->where('user_id', $user?->getKey())));
    }

    /**
     * The form's "N units before" / "at" choice as the table's columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function toAttributes(array $data, Action $action): array
    {
        $timing = $data['timing'] ?? 'at';

        if ($timing === 'before') {
            $start = $this->startTime();

            if ($start === null) {
                Notification::make()->title(__('This record has no time to remind you before.'))->danger()->send();
                $action->halt();
            }

            $minutes = (int) $data['before_amount'] * match ($data['before_unit'] ?? 'minutes') {
                'days' => 1440,
                'hours' => 60,
                default => 1,
            };

            $data['before_minutes'] = $minutes;
            $data['remind_at'] = $start->copy()->subMinutes($minutes);
        } else {
            $data['before_minutes'] = null;
        }

        unset($data['timing'], $data['before_amount'], $data['before_unit']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function toFormData(array $data, Reminder $record): array
    {
        if (! $record->isRelative()) {
            return [...$data, 'timing' => 'at'];
        }

        [$amount, $unit] = Reminder::splitMinutes($record->before_minutes);

        return [...$data, 'timing' => 'before', 'before_amount' => $amount, 'before_unit' => $unit];
    }

    protected function startTime(): ?CarbonInterface
    {
        return Reminders::startOf($this->getOwnerRecord());
    }

    /**
     * @return array<int, int>
     */
    protected function eligibleRecipientIds(): array
    {
        // Memoised for the request: the options, the preload and the
        // validation of the Select each ask, and every candidate costs a
        // visibility check.
        return $this->eligibleRecipientIds ??= Reminders::eligibleRecipients($this->getOwnerRecord())->modelKeys();
    }
}
