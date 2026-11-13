<?php

namespace Epesi\Modules\ActivityReport\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\User;
use BackedEnum;
use Epesi\Modules\ActivityReport\ActivityReport;
use Epesi\Modules\ActivityReport\ActivityReportRow;
use Epesi\Modules\ActivityReport\Filament\Tables\ActivityReportTable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The port of Apps_ActivityReport's page. Employees and accountants see their
 * own activity; managers and super admins choose between their own (the
 * default), one other active user, and every active user.
 *
 * The scope is the table's base query, so it holds whatever the User filter
 * is sent: that filter can only narrow it.
 */
class UserActivityReport extends Page implements HasTable
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    /** The User filter's value for every active user. */
    public const ALL = 'all';

    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'User Activity Report';

    protected static ?string $title = 'User Activity Report';

    protected static ?string $slug = 'activity-report/events';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ActivityReport::canAccess();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        /** @var User $viewer */
        $viewer = Auth::user();

        ActivityReportRow::forget();

        $table = ActivityReportTable::configure($table, withUser: true)
            ->query(fn (): Builder => ActivityReport::query(ActivityReport::allowedUserIds($viewer))->with('causer'))
            ->heading(__('User Activity Report'))
            ->headerActions([
                Action::make('dashboard')
                    ->label(__('Activity dashboard'))
                    ->icon('heroicon-o-chart-bar')
                    ->url(ActivityDashboard::getUrl()),
            ]);

        if (! ActivityReport::canPickUser($viewer)) {
            return $table;
        }

        return $table->filters([
            SelectFilter::make('user')
                ->label('User')
                ->columnSpan(['default' => 1, 'lg' => 1])
                ->options(fn (): array => [
                    $viewer->getKey() => __('Me'),
                    self::ALL => __('All active users'),
                ] + array_diff_key(ActivityReport::userOptions(), [$viewer->getKey() => true]))
                ->default($viewer->getKey())
                ->selectablePlaceholder(false)
                ->searchable()
                ->query(function (Builder $query, array $data): Builder {
                    $value = $data['value'] ?? null;

                    return filled($value) && $value !== self::ALL
                        ? $query->where('causer_id', (int) $value)
                        : $query;
                }),
            ActivityReportTable::filter(span: 3),
        ], layout: FiltersLayout::AboveContent);
    }
}
