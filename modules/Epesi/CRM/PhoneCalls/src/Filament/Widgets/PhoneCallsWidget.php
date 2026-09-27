<?php

namespace Epesi\Modules\CRM\PhoneCalls\Filament\Widgets;

use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\AppletTooltip;
use App\Filament\Dashboard\IsApplet;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\PhoneCallResource;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * The Phone calls applet (CRM_PhoneCall::applet()): calls still to make that
 * the user is assigned to, missed ones, today's and as far ahead as they
 * choose. On hold counts as not to make, as in Epesi.
 */
class PhoneCallsWidget extends TableWidget implements Applet
{
    use IsApplet;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    /** How far ahead, in days; -1 is every future call. */
    public const FUTURE = [
        0 => 'No',
        1 => 'Tomorrow',
        2 => '2 days forward',
        7 => '1 week forward',
        -1 => 'All',
    ];

    public static function canView(): bool
    {
        return Auth::user()?->can('viewAny', PhoneCall::class) ?? false;
    }

    public static function getAppletCaption(): string
    {
        return __('Phone Calls');
    }

    public static function getAppletDescription(): ?string
    {
        return __('List of phone calls to do');
    }

    public static function getAppletSettingsDefaults(): array
    {
        return [
            'past' => true,
            'today' => true,
            'future' => 0,
        ];
    }

    public static function getAppletSettingsSchema(): array
    {
        return [
            Checkbox::make('past')->label('Include missed (past) calls'),
            Checkbox::make('today')->label("Include today's calls"),
            Select::make('future')
                ->label('Include future calls')
                ->options(array_map(fn (string $label): string => __($label), self::FUTURE))
                ->required()
                ->selectablePlaceholder(false),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Phone Calls'))
            ->query(fn (): Builder => $this->calls())
            ->columns([
                TextColumn::make('customer')
                    ->label('Customer')
                    ->state(fn (PhoneCall $record): ?string => $record->other_customer
                        ? $record->other_customer_name
                        : ($record->contact?->full_name ?? $record->company?->company_name))
                    ->description(fn (PhoneCall $record): ?string => $record->subject)
                    ->tooltip(fn (PhoneCall $record): ?HtmlString => $this->details($record))
                    ->wrap(),
                // The time under the number: a column of its own left no room
                // for the customer on a phone.
                TextColumn::make('phone_number')
                    ->label('Phone Number')
                    ->description(fn (PhoneCall $record): ?string => $record->called_at?->format('Y-m-d H:i'))
                    ->tooltip(fn (PhoneCall $record): ?HtmlString => $this->details($record)),
            ])
            ->recordUrl(fn (PhoneCall $record): string => PhoneCallResource::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (PhoneCall $record): ?string => $record->priority === RecordPriority::High ? 'epesi-applet-high-priority' : null)
            ->headerActions([
                Action::make('create')
                    ->label('New phone call')
                    ->tooltip(__('New phone call'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->iconButton()
                    ->color('gray')
                    ->url(fn (): string => PhoneCallResource::getUrl('create'))
                    ->visible(fn (): bool => PhoneCallResource::canCreate()),
                Action::make('fullscreen')
                    ->label('Fullscreen')
                    ->tooltip(__('Fullscreen'))
                    ->icon(Heroicon::OutlinedArrowsPointingOut)
                    ->iconButton()
                    ->color('gray')
                    ->url(fn (): string => PhoneCallResource::getUrl()),
                $this->configureAppletAction(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading(__('No calls to make'))
            ->emptyStateDescription(fn (): ?string => Auth::user()?->contact === null
                ? __('Your login is not linked to a contact, so no calls are assigned to you.')
                : null)
            ->emptyStateIcon(Heroicon::OutlinedPhone);
    }

    /** The description on hover; the date and time are in the row already. */
    private function details(PhoneCall $record): ?HtmlString
    {
        return AppletTooltip::text($record->description);
    }

    /**
     * @return Builder<PhoneCall>
     */
    public function calls(): Builder
    {
        $today = today();
        $future = (int) $this->appletSetting('future');

        return PhoneCall::query()
            ->with(['contact', 'company'])
            ->whereHas('employees', fn (Builder $contacts): Builder => $contacts->where('user_id', Auth::id()))
            ->whereNotIn('status', [RecordStatus::OnHold, ...RecordStatus::finished()])
            ->unless($this->appletSetting('past'), fn (Builder $query): Builder => $query
                ->where('called_at', '>=', $today))
            ->unless($this->appletSetting('today'), fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('called_at', '<', $today)
                    ->orWhere('called_at', '>=', $today->copy()->addDay())))
            ->when($future !== -1, fn (Builder $query): Builder => $query
                ->where('called_at', '<', $today->copy()->addDays($future + 1)))
            ->orderBy('status')
            ->orderBy('called_at')
            ->orderByDesc('priority');
    }
}
