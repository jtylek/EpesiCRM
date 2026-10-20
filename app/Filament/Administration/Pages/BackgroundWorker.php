<?php

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\Background\BackgroundWorkerHealth;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class BackgroundWorker extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|UnitEnum|null $navigationGroup = 'Server Setup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Background worker';

    protected static ?string $title = 'Background worker';

    protected static ?string $slug = 'background-worker';

    public function content(Schema $schema): Schema
    {
        $status = app(BackgroundWorkerHealth::class)->status();
        $state = $status['enabled'] ? __('enabled') : __('disabled');
        $stateColor = $status['enabled'] ? 'var(--success-600)' : 'var(--gray-500)';

        return $schema->components([
            Section::make(__('Background worker'))
                ->description(__('An optional database queue for selected, retry-safe work. The normal queue remains synchronous.'))
                ->compact()
                ->schema([
                    Text::make(new HtmlString('<strong style="font-size:1.1rem">'.e(__('Worker')).' · <span style="color:'.$stateColor.'">'.e($state).'</span></strong>'))
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold),
                    ...($status['overdue'] ? [
                        Text::make(new HtmlString('<span style="color:var(--danger-600)">'.e(__('Enabled, but nothing has been drained for several minutes (or ever). Jobs are being queued that nobody works off; add the cron entry below on the server, or turn the worker off with EPESI_BACKGROUND_WORKER_ENABLED=false.')).'</span>')),
                    ] : []),
                    $this->facts([
                        __('Queue') => (string) config('epesi-background.queue'),
                        __('Last successful drain') => $status['last_successful_drain']?->toDateTimeString() ?? __('never'),
                        __('Pending jobs') => (string) $status['pending'],
                        __('Reserved jobs') => (string) $status['reserved'],
                        __('Oldest queued age') => $status['oldest_age'] === null ? __('none') : __(':count seconds', ['count' => $status['oldest_age']]),
                        __('Failed jobs') => (string) $status['failed'],
                        __('Queue tables') => $status['available'] ? __('available') : ($status['error'] ?? __('unavailable')),
                        // The host's own cron entry, next to the one for cron.php: a separate process, never started from the cron URL.
                        __('Cron entry (every minute)') => '* * * * * php '.base_path('artisan').' epesi:background:drain',
                    ]),
                ]),
        ]);
    }

    /** @param array<string, string> $facts */
    protected function facts(array $facts): Text
    {
        $body = collect($facts)->map(fn (string $value, string $label): string => '<tr><td style="padding:.15rem 1.5rem .15rem 0;color:var(--gray-500);white-space:nowrap;vertical-align:top">'.e($label).'</td><td style="padding:.15rem 0;word-break:break-all">'.e($value).'</td></tr>')->implode('');

        return Text::make(new HtmlString('<div style="overflow-x:auto"><table style="font-size:.875rem">'.$body.'</table></div>'));
    }
}
