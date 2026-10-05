<?php

namespace Epesi\Modules\Appearance\Filament\Administration\Resources\Themes;

use App\Filament\Concerns\TranslatesResourceLabels;
use BackedEnum;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Pages\CreateTheme;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Pages\EditTheme;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Pages\ListThemes;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Pages\ViewTheme;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Schemas\ThemeForm;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Schemas\ThemeInfolist;
use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Tables\ThemesTable;
use Epesi\Modules\Appearance\Models\Theme;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Administration → Themes: named looks (an accent colour and a density) an
 * admin creates once and every user then picks from on the Appearance page
 * (Filament\Pages\Appearance, "user-settings" panel) — see
 * AI-shared/Epesi-custom-themes.md. Lives in the super_admin-only
 * "administration" panel; access is gated there by User::canAccessPanel(),
 * same as Login Audit, Users and Modules. No Policy: every visitor to this
 * panel already is a super_admin (see ModuleResource/UserResource, neither
 * of which needs one beyond that either).
 */
class ThemeResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Theme::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $recordTitleAttribute = 'name';

    // First under "Appearance", ahead of Logo & Title (a sort below -1 pins an item).
    protected static ?int $navigationSort = -2;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Appearance');
    }

    public static function form(Schema $schema): Schema
    {
        return ThemeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ThemeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ThemesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThemes::route('/'),
            'create' => CreateTheme::route('/create'),
            'view' => ViewTheme::route('/{record}'),
            'edit' => EditTheme::route('/{record}/edit'),
        ];
    }
}
