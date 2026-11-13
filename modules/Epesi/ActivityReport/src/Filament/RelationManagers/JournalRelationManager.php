<?php

namespace Epesi\Modules\ActivityReport\Filament\RelationManagers;

use App\Models\User;
use Epesi\Modules\ActivityReport\ActivityReport;
use Epesi\Modules\ActivityReport\ActivityReportRow;
use Epesi\Modules\ActivityReport\Filament\Tables\ActivityReportTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The Journal tab of a contact that has a login: that user's activity,
 * the port of the report's contact addon. Seen by the user themselves and by
 * managers and super admins; the user is the contact's, never a filter.
 */
class JournalRelationManager extends RelationManager
{
    /**
     * Not a relationship of the contact (the report is not its History, which
     * is `activities`); a name is all the record page asks of a tab, and
     * getRelationship() supplies the query.
     */
    protected static string $relationship = 'journal';

    protected static ?string $title = 'Journal';

    /** No count on the tab: `journal` is not a relation the record page could count. */
    protected static bool $isBadgeDeferred = true;

    public function getRelationship(): Builder
    {
        // The owner's own user and no other: for a manager, as for anyone.
        return ActivityReport::query([(int) $this->getOwnerRecord()->getAttribute('user_id')])->with('causer');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = Auth::user();
        $userId = $ownerRecord->getAttribute('user_id');

        return $user instanceof User
            && $userId !== null
            && ActivityReport::canAccess($user)
            && ($user->getKey() === (int) $userId || ActivityReport::canPickUser($user))
            && $pageClass::getResource()::canView($ownerRecord);
    }

    protected function makeTable(): Table
    {
        return Table::make($this);
    }

    public function table(Table $table): Table
    {
        ActivityReportRow::forget();

        return ActivityReportTable::configure($table, withUser: false)
            ->query(fn (): Builder => $this->getRelationship())
            ->heading(null);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
