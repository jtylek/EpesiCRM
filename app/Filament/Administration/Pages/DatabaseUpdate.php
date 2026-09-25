<?php

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Services\Setup\SystemUpdate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Epesi's update.php in the browser: after a new release has been unpacked
 * over the installation, lists the database migrations it brought — the core
 * app's and every enabled module's — and runs them. For a host without a
 * shell; `php artisan epesi:update` is the same on the command line.
 */
class DatabaseUpdate extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'Database update';

    protected static ?string $title = 'Database update';

    protected static ?string $slug = 'database-update';

    protected static ?int $navigationSort = 90;

    public static function getNavigationBadge(): ?string
    {
        $waiting = SystemUpdate::waiting();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function content(Schema $schema): Schema
    {
        $pending = app(SystemUpdate::class)->pending();

        if ($pending === []) {
            return $schema->components([
                Callout::make(__('The database is up to date'))
                    ->description(__('After unpacking a new epesi release over this installation, open this page to apply the database changes it brings.'))
                    ->success(),
            ]);
        }

        $count = array_sum(array_map('count', $pending));

        return $schema->components([
            Callout::make(trans_choice('{1} :count database change is waiting|[2,*] :count database changes are waiting', $count, ['count' => $count]))
                ->description(__('The files of a newer version are in place, but its database changes haven\'t been made yet. Until they are, some pages may show errors. Back up the database, then click "Update the database".'))
                ->warning(),
            ...collect($pending)->map(fn (array $names, string $source): Section => Section::make($source)
                ->compact()
                ->schema([
                    Text::make(new HtmlString('<ul style="margin:0;padding-left:1.25rem;list-style:disc">'
                        .collect($names)->map(fn (string $name): string => '<li><code>'.e($name).'</code></li>')->implode('')
                        .'</ul>')),
                ]))->values()->all(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('update')
                ->label('Update the database')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->visible(fn (): bool => SystemUpdate::waiting() > 0)
                ->requiresConfirmation()
                ->modalHeading(__('Update the database?'))
                ->modalDescription(__('This makes the database changes of the installed version. It can\'t be undone from here, so back up the database first. It can take a minute; keep this page open.'))
                ->modalSubmitActionLabel(__('Update the database'))
                ->action(function (SystemUpdate $update): void {
                    try {
                        $update->run();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title(__('The database update stopped'))->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    SystemUpdate::flush();
                    Notification::make()->title(__('The database is up to date'))->success()->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
