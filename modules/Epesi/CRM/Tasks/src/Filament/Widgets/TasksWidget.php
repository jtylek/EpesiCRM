<?php

namespace Epesi\Modules\CRM\Tasks\Filament\Widgets;

use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\AppletTooltip;
use App\Filament\Dashboard\IsApplet;
use Closure;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * The Tasks applet (CRM_Tasks::applet()): tasks in the chosen statuses that
 * the user is assigned to, or is a contact on, soonest deadline first. Each
 * copy on the dashboard can have a title of its own ("Tasks - Sales").
 * Epesi's "Advanced Filter" (a crits builder) isn't ported.
 */
class TasksWidget extends TableWidget implements Applet
{
    use IsApplet;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return Auth::user()?->can('viewAny', Task::class) ?? false;
    }

    public static function getAppletCaption(): string
    {
        return __('Tasks');
    }

    public static function getAppletDescription(): ?string
    {
        return __('To do list');
    }

    public static function getAppletSettingsDefaults(): array
    {
        return [
            'subtitle' => null,
            'statuses' => [RecordStatus::Open->value, RecordStatus::InProgress->value, RecordStatus::OnHold->value],
            'related' => 'employee',
        ];
    }

    public static function getAppletSettingsSchema(): array
    {
        return [
            TextInput::make('subtitle')
                ->label('Additional title')
                ->maxLength(64),
            CheckboxList::make('statuses')
                ->label('Display with status')
                ->options(collect(RecordStatus::cases())
                    ->mapWithKeys(fn (RecordStatus $status): array => [$status->value => $status->getLabel()])
                    ->all())
                ->required(),
            Select::make('related')
                ->label('Show tasks')
                ->options([
                    'employee' => __('Assigned to me'),
                    'customer' => __('Where I am a contact'),
                    'either' => __('Assigned to me or where I am a contact'),
                    'any' => __('All I can see'),
                ])
                ->required()
                ->selectablePlaceholder(false),
        ];
    }

    public function table(Table $table): Table
    {
        $subtitle = $this->appletSetting('subtitle');

        return $table
            ->heading(filled($subtitle) ? __('Tasks').' - '.$subtitle : __('Tasks'))
            ->query(fn (): Builder => $this->tasks())
            // One cell per task: the title, then the status badge with the
            // deadline (when it has one) after it — Status and Deadline as
            // columns of their own left the title a narrow strip.
            ->columns([
                Stack::make([
                    TextColumn::make('title')
                        ->label('Title')
                        ->tooltip(fn (Task $record): ?HtmlString => $this->details($record))
                        ->wrap(),
                    Split::make([
                        TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->grow(false)
                            ->tooltip(fn (Task $record): ?HtmlString => $this->details($record)),
                        TextColumn::make('deadline')
                            ->label('Deadline')
                            ->state(fn (Task $record): ?string => $record->deadline?->format($record->timeless ? 'Y-m-d' : 'Y-m-d H:i'))
                            ->prefix(fn (Task $record): ?string => $record->deadline ? __('Deadline').': ' : null)
                            ->color(fn (Task $record): ?string => $record->deadline?->isPast()
                                && ! in_array($record->status, RecordStatus::finished(), true) ? 'danger' : null)
                            ->grow(false)
                            ->tooltip(fn (Task $record): ?HtmlString => $this->details($record)),
                    ]),
                ])->space(1),
            ])
            ->recordUrl(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (Task $record): ?string => $record->priority === RecordPriority::High ? 'epesi-applet-high-priority' : null)
            ->headerActions([
                Action::make('create')
                    ->label('New task')
                    ->tooltip(__('New task'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->iconButton()
                    ->color('gray')
                    ->url(fn (): string => TaskResource::getUrl('create'))
                    ->visible(fn (): bool => TaskResource::canCreate()),
                Action::make('fullscreen')
                    ->label('Fullscreen')
                    ->tooltip(__('Fullscreen'))
                    ->icon(Heroicon::OutlinedArrowsPointingOut)
                    ->iconButton()
                    ->color('gray')
                    ->url(fn (): string => TaskResource::getUrl()),
                $this->configureAppletAction(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading(__('No tasks'))
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }

    /** The description on hover, without a label: it is the only detail, and the deadline is in the record already. */
    private function details(Task $record): ?HtmlString
    {
        return AppletTooltip::text($record->description);
    }

    /**
     * @return Builder<Task>
     */
    public function tasks(): Builder
    {
        $userId = Auth::id();
        $mine = fn (string $relation): Closure => fn (Builder $query): Builder => $query
            ->whereHas($relation, fn (Builder $contacts): Builder => $contacts->where('user_id', $userId));

        return Task::query()
            ->whereIn('status', array_map('intval', (array) $this->appletSetting('statuses')))
            ->when($this->appletSetting('related'), fn (Builder $query, string $related): Builder => match ($related) {
                'employee' => $query->where($mine('employees')),
                'customer' => $query->where($mine('customers')),
                'either' => $query->where(fn (Builder $query): Builder => $query->where($mine('employees'))->orWhere($mine('customers'))),
                default => $query,
            })
            // Legacy sorted by deadline, status, then priority; a task
            // without a deadline goes last rather than first.
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->orderBy('status')
            ->orderByDesc('priority');
    }
}
