<?php

namespace Epesi\Modules\ActivityReport\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Filament\Concerns\UsesEpesiFormLayout;
use BackedEnum;
use Epesi\Modules\ActivityReport\ActivityReport;
use Epesi\Modules\ActivityReport\ActivitySummary;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use UnitEnum;

class ActivityDashboard extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;
    use UsesEpesiFormLayout;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'User Activity Report';

    protected static ?string $title = 'User Activity Report';

    protected static ?string $slug = 'activity-report';

    protected string $view = 'epesi-activity-report::dashboard';

    public array $data = [];

    #[Locked]
    public array $filters = [];

    public static function canAccess(): bool
    {
        return ActivityReport::canAccess();
    }

    public function mount(): void
    {
        $data = session()->get('activity-dashboard.filters', ['user' => (string) auth()->id(), 'period' => '30']);
        if ($data['period'] !== 'custom') {
            [$data['from'], $data['until']] = ActivitySummary::dates($data['period']);
        }
        $this->filters = ['user' => (string) $data['user'], 'from' => $data['from'], 'until' => $data['until']];
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(['default' => 1, 'md' => 2, 'xl' => 4])->components([
            Select::make('user')->label(__('User'))->required()->searchable()
                ->visible(fn (): bool => ActivityReport::canPickUser())
                ->options(fn (): array => ActivityReport::canPickUser() ? [
                    auth()->id() => __('Me'), 'all' => __('All active users'),
                ] + array_diff_key(ActivityReport::userOptions(), [auth()->id() => true]) : [auth()->id() => __('Me')]),
            Select::make('period')->label(__('Period'))->required()->selectablePlaceholder(false)
                ->options([
                    'today' => __('Today'), 'week' => __('This week'), 'month' => __('This month'),
                    '7' => __('Last 7 days'), '30' => __('Last 30 days'), '90' => __('Last 90 days'),
                    'year' => __('This year'), 'custom' => __('Custom dates'),
                ])->live()->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($state !== 'custom') {
                        [$from, $until] = ActivitySummary::dates($state ?? 'month');
                        $set('from', $from);
                        $set('until', $until);
                    }
                }),
            DatePicker::make('from')->label(__('From'))->required()
                ->live()->afterStateUpdated(fn (Set $set) => $set('period', 'custom')),
            DatePicker::make('until')->label(__('To'))->required()
                ->live()->afterStateUpdated(fn (Set $set) => $set('period', 'custom')),
        ]);
    }

    public function applyFilters(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $this->validate(['data.until' => ['after_or_equal:data.from']]);
        $this->filters = [
            'user' => (string) ($data['user'] ?? auth()->id()),
            'from' => $data['from'], 'until' => $data['until'],
        ];
        session()->put('activity-dashboard.filters', $this->filters + ['period' => $data['period']]);
        unset($this->summary);
    }

    #[Computed]
    public function summary(): array
    {
        return ActivitySummary::forUser(auth()->user(), $this->filters['user'], $this->filters['from'], $this->filters['until']);
    }

    public function detailsUrl(?string $alias = null, ?string $kind = null, ?string $date = null): string
    {
        $files = $alias === ActivityReport::attachmentAlias();

        return UserActivityReport::getUrl(['filters' => [
            'user' => ['value' => $this->filters['user'] ?? (string) auth()->id()],
            'report' => [
                'from' => $date ?? $this->filters['from'] ?? null,
                'until' => $date ?? $this->filters['until'] ?? null,
                'recordsets' => $alias && ! $files ? [$alias] : [],
                'kinds' => $files ? [ActivityReport::FILES] : ($kind ? [$kind] : ($alias ? ActivityReport::DEFAULT_KINDS : [...ActivityReport::DEFAULT_KINDS, ActivityReport::FILES])),
            ],
        ]]);
    }
}
