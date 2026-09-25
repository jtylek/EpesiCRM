<?php

namespace App\Filament\Administration\Resources\LoginAudits;

use App\Filament\Administration\Resources\LoginAudits\Pages\ListLoginAudits;
use App\Filament\Administration\Resources\LoginAudits\Tables\LoginAuditsTable;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Models\LoginAudit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Port of Epesi's CRM_LoginAudit admin screen — read-only, no create/edit/
 * delete: rows are written only by App\Http\Middleware\TrackLoginAudit and
 * App\Listeners\FinalizeLoginAudit. Lives in the "administration" panel
 * (App\Providers\Filament\AdministrationPanelProvider), not the main one —
 * reached via the "Administration" item in the main panel's user menu,
 * gated to super_admin by User::canAccessPanel().
 */
class LoginAuditResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = LoginAudit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightOnRectangle;

    protected static ?string $navigationLabel = 'Login Audit';

    protected static ?string $modelLabel = 'Login Audit';

    public static function table(Table $table): Table
    {
        return LoginAuditsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoginAudits::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
