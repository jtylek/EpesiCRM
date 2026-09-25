<?php

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\DemoData;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Removes the demo data the setup wizard loaded, once the system is to be
 * used for real: the demo users, companies, contacts, tasks, calls, meetings
 * and shoutbox messages (see App\Support\DemoData). The administrator's own
 * account, contact and company — and anything added since — stay. Only in
 * the menu while there is demo data to remove.
 */
class DemoDataPage extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Demo data';

    protected static ?string $title = 'Demo data';

    protected static ?string $slug = 'demo-data';

    protected static ?int $navigationSort = 95;

    /** What each demo table holds, as people know it. */
    protected const LABELS = [
        'companies' => 'Companies',
        'contacts' => 'Contacts',
        'tasks' => 'Tasks',
        'phone_calls' => 'Phone Calls',
        'meetings' => 'Meetings',
        'epesi_shoutbox_messages' => 'Shoutbox',
        'users' => 'Users',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return DemoData::present();
        } catch (Throwable) {
            return false;
        }
    }

    public function content(Schema $schema): Schema
    {
        $counts = DemoData::counts();

        if ($counts === []) {
            return $schema->components([
                Callout::make(__('There is no demo data'))
                    ->description(__('Everything in this system was entered by its users.'))
                    ->success(),
            ]);
        }

        $rows = collect($counts)
            ->map(fn (int $n, string $table): string => '<li>'.e(__(self::LABELS[$table] ?? $table)).': <strong>'.$n.'</strong></li>')
            ->implode('');

        return $schema->components([
            Callout::make(__('This system contains demo data'))
                ->description(__('It was loaded during setup to try epesi out. Remove it before you start working with your own data.'))
                ->info(),
            Text::make(new HtmlString('<ul style="margin:0;padding-left:1.25rem;list-style:disc">'.$rows.'</ul>')),
            Text::make(__('Your own user account, your contact and your company, and everything you added yourself, are kept.')),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('remove')
                ->label('Remove the demo data')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => DemoData::present())
                ->requiresConfirmation()
                ->modalHeading(__('Remove the demo data?'))
                ->modalDescription(__('The demo companies, contacts, tasks, phone calls, meetings, shoutbox messages and the demo users (manager@example.com, employee@example.com) are deleted for good, with their history and notes. This can\'t be undone.'))
                ->modalSubmitActionLabel(__('Remove the demo data'))
                ->action(function (): void {
                    try {
                        DemoData::remove();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title(__('The demo data could not be removed'))->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    Notification::make()->title(__('The demo data was removed'))->success()->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
