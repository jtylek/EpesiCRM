<?php

namespace Epesi\Modules\CRM\PhoneCalls\Filament\Widgets;

use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Filament\Dashboard\AppletTooltip;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\PhoneCallResource;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Filament\Widgets\RecordsetApplet;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * The Phone calls applet (CRM_PhoneCall::applet()): calls still to make that
 * the user is assigned to, missed ones, today's and as far ahead as they
 * choose. On hold counts as not to make, as in Epesi.
 */
class PhoneCallsWidget extends RecordsetApplet
{
    protected static ?string $resource = PhoneCallResource::class;

    protected static ?int $sort = 3;

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

    protected function getAppletCreateLabel(): string
    {
        return __('New phone call');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->calls())
            ->columns([
                TextColumn::make('customer')
                    ->label('Customer')
                    ->state(fn (PhoneCall $record): ?string => $record->other_customer
                        ? $record->other_customer_name
                        : ($record->customer ? LinkedRecords::title($record->customer) : null))
                    ->description(fn (PhoneCall $record): ?string => $record->subject)
                    ->tooltip(fn (PhoneCall $record): ?HtmlString => $this->details($record))
                    ->wrap(),
                // The time under the number: a column of its own left no room
                // for the customer on a phone.
                TextColumn::make('phone_number')
                    ->label('Phone Number')
                    ->description(fn (PhoneCall $record): ?string => RegionalSetting::display($record->called_at))
                    ->tooltip(fn (PhoneCall $record): ?HtmlString => $this->details($record)),
            ])
            ->recordUrl(fn (PhoneCall $record): string => PhoneCallResource::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (PhoneCall $record): ?string => $record->priority === RecordPriority::High ? 'epesi-applet-high-priority' : null)
            ->emptyStateHeading(__('No calls to make'))
            ->emptyStateDescription(fn (): ?string => Auth::user()?->contact === null
                ? __('Your login is not linked to a contact, so no calls are assigned to you.')
                : null)
            ->emptyStateIcon(Heroicon::OutlinedPhone);
    }

    /** The call's full details on hover. */
    private function details(PhoneCall $record): HtmlString
    {
        return AppletTooltip::details(
            type: 'Phone Call',
            icon: PhoneCallResource::getNavigationIcon(),
            title: $record->subject,
            description: $record->description,
            dateLabel: 'Date and Time',
            date: RegionalSetting::display($record->called_at),
            customers: AppletTooltip::customers($record),
        );
    }

    /**
     * @return Builder<PhoneCall>
     */
    public function calls(): Builder
    {
        $today = today();
        $future = (int) $this->appletSetting('future');

        return PhoneCall::query()
            ->with('customer')
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
