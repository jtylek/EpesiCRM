<?php

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Services\Setup\Requirements;
use App\Support\Optimize\PhpSettings;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * What a system administrator wants to know about the server epesi runs on:
 * the setup wizard's requirements checked again (PHP version, extensions,
 * writable folders), the php.ini recommendations (PhpSettings), and the web
 * server, operating system, database and PHP limits. All of it is read in
 * the web server's PHP: the command line's php.ini may differ.
 */
class ServerCheck extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Server Check';

    protected static ?string $title = 'Server Check';

    protected static ?string $slug = 'server-check';

    protected static ?int $navigationSort = 92;

    public static function getNavigationBadge(): ?string
    {
        $failed = count(array_filter((new Requirements)->check(phpIni: false), fn (array $row): bool => $row['required'] && ! $row['ok']));

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->requirementsSection(),
            $this->phpSettingsSection(),
            $this->serverSection(),
            $this->phpSection(),
            $this->extensionsSection(),
        ]);
    }

    protected function requirementsSection(): Section
    {
        $rows = (new Requirements)->check(phpIni: false);
        $failed = count(array_filter($rows, fn (array $row): bool => $row['required'] && ! $row['ok']));
        $warned = count(array_filter($rows, fn (array $row): bool => ! $row['required'] && ! $row['ok']));

        // The first row is the PHP version: shown as the title, without "required".
        $php = array_shift($rows);
        $phpColor = $php['ok'] ? 'var(--success-600)' : 'var(--danger-600)';
        $title = Text::make(new HtmlString(e($php['label']).' · <span style="color:'.$phpColor.'">'.e($php['ok'] ? __('OK') : $php['status']).'</span>'))
            ->size(TextSize::Large)
            ->weight(FontWeight::Bold);

        $body = collect($rows)->map(function (array $row): string {
            $color = $row['ok'] ? 'var(--success-600)' : ($row['required'] ? 'var(--danger-600)' : 'var(--warning-600)');

            return '<tr><td style="padding:.15rem 1rem .15rem 0">'.e($row['label']).'</td>'
                .'<td style="padding:.15rem 1rem .15rem 0;color:'.$color.';font-weight:600">'.e($row['ok'] ? __('OK') : $row['status']).'</td>'
                .'<td style="padding:.15rem 0;color:var(--gray-500)">'.e($row['required'] ? __('required') : __('optional')).'</td></tr>';
        })->implode('');

        return Section::make(__('Requirements'))
            ->description($failed > 0
                ? __(':count of the requirements are not met. epesi may not work until they are.', ['count' => $failed])
                : ($warned > 0 ? __('Every requirement is met; some optional ones are not.') : __('This server meets every requirement.')))
            ->compact()
            ->collapsible()
            ->schema([$title, $this->table($body)]);
    }

    protected function phpSettingsSection(): Section
    {
        $rows = PhpSettings::compare();
        $below = count(array_filter($rows, fn (array $row): bool => ! $row['ok']));

        $body = collect($rows)->map(function (array $row): string {
            $color = $row['ok'] ? 'var(--success-600)' : 'var(--warning-600)';

            return '<tr>'
                .'<td style="padding:.15rem 1rem .15rem 0"><code>'.e($row['setting']).'</code></td>'
                .'<td style="padding:.15rem 1rem .15rem 0;color:'.$color.';font-weight:600">'.e($row['current']).'</td>'
                .'<td style="padding:.15rem 1rem .15rem 0">'.e($row['recommended']).'</td>'
                .'<td style="padding:.15rem 0;color:var(--gray-500)">'.e($row['why']).'</td></tr>';
        })->implode('');

        $head = '<tr><th style="padding:.15rem 1rem .15rem 0;text-align:start">'.e(__('Setting')).'</th>'
            .'<th style="padding:.15rem 1rem .15rem 0;text-align:start">'.e(__('This server')).'</th>'
            .'<th style="padding:.15rem 1rem .15rem 0;text-align:start">'.e(__('Recommended')).'</th><th></th></tr>';

        return Section::make(__('PHP settings'))
            ->description($below === 0
                ? __('This server meets every recommended php.ini setting.')
                : __(':count of the recommended php.ini settings are not met. The file config/php-production.ini in the epesi folder holds them, ready to copy into php.ini. On shared hosting, the OPcache sizes can only be changed by the host.', ['count' => $below]))
            ->compact()
            ->collapsible()
            ->collapsed($below === 0)
            ->schema([$this->table($body, $head)]);
    }

    protected function serverSection(): Section
    {
        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || request()->isSecure();

        return Section::make(__('Server'))
            ->compact()
            ->collapsible()
            ->schema([$this->facts([
                __('Web server') => (string) ($_SERVER['SERVER_SOFTWARE'] ?? __('unknown')),
                __('Server name') => (string) ($_SERVER['SERVER_NAME'] ?? request()->getHost()),
                __('Server address') => (string) ($_SERVER['SERVER_ADDR'] ?? __('unknown')),
                __('HTTPS') => $https ? __('on') : __('off'),
                __('Operating system') => php_uname('s').' '.php_uname('r').' ('.php_uname('m').')',
                __('Host name') => (string) gethostname(),
                __('Document root') => (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
                __('epesi folder') => base_path(),
                __('Free disk space') => $this->diskSpace(),
                __('Database') => $this->database(),
                __('Server time') => now()->format('Y-m-d H:i:s').' ('.config('app.timezone').')',
                __('Environment') => config('app.env').(config('app.debug') ? ', '.__('debug on') : ''),
                __('Cache / queue / session') => config('cache.default').' / '.config('queue.default').' / '.config('session.driver'),
                __('Laravel') => app()->version(),
            ])]);
    }

    protected function phpSection(): Section
    {
        $php = PhpSettings::runtime();
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $blocked = array_values(array_intersect(['proc_open', 'exec'], $disabled));
        $quantity = fn (string $setting): int => (int) @ini_parse_quantity((string) ini_get($setting));
        $limit = fn (string $setting, string $minimum): bool => ini_get($setting) === '-1' || $quantity($setting) >= (int) ini_parse_quantity($minimum);
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        // label => [this server, recommended, ok (null: nothing to judge)]
        $rows = [
            __('PHP version') => [$php['version'], __('8.2 or newer'), $php['supported']],
            __('Interface (SAPI)') => [$php['sapi'], __('PHP-FPM, LiteSpeed or the web server module'), ! in_array($php['sapi'], ['cli', 'phpdbg'], true)],
            __('php.ini') => [$php['ini'] !== '' ? $php['ini'] : __('no php.ini loaded'), __('one loaded'), $php['ini'] !== ''],
            __('Other .ini files') => [(string) php_ini_scanned_files() ?: '—', '—', null],
            __('PHP binary') => [PHP_BINARY ?: '—', '—', null],
            __('Architecture') => [(PHP_INT_SIZE * 8).' bit', '64 bit', PHP_INT_SIZE >= 8],
            'max_execution_time' => [(string) ini_get('max_execution_time'), __('120 or more'), $limit('max_execution_time', '120') || ini_get('max_execution_time') === '0'],
            'upload_max_filesize' => [(string) ini_get('upload_max_filesize'), __('64M or more'), $limit('upload_max_filesize', '64M')],
            'post_max_size' => [(string) ini_get('post_max_size'), __('64M or more, not below upload_max_filesize'), $limit('post_max_size', '64M') && ($quantity('post_max_size') >= $quantity('upload_max_filesize') || ini_get('post_max_size') === '0')],
            'max_input_vars' => [(string) ini_get('max_input_vars'), __('3000 or more'), $limit('max_input_vars', '3000')],
            'date.timezone' => [(string) (ini_get('date.timezone') ?: '—'), __('set (epesi uses its own timezone setting)'), null],
            'open_basedir' => [(string) (ini_get('open_basedir') ?: '—'), __('empty, or including the epesi folder and the temp folder'), null],
            'disable_functions' => [(string) (ini_get('disable_functions') ?: '—'), __('proc_open and exec not disabled'), $blocked === []],
            __('OPcache') => [
                $opcache ? ($opcache['opcache_enabled'] ? __('on') : __('off')).', '.round($opcache['memory_usage']['used_memory'] / 1048576).' MB '.__('used') : __('not installed'),
                __('on (see PHP settings above)'),
                (bool) ($opcache['opcache_enabled'] ?? false),
            ],
            'cURL' => [extension_loaded('curl') ? (curl_version()['version'] ?? '—') : __('missing'), __('7.60 or newer'), extension_loaded('curl') && version_compare(curl_version()['version'] ?? '0', '7.60', '>=')],
            'OpenSSL' => [defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : __('missing'), __('1.1.1 or newer'), defined('OPENSSL_VERSION_NUMBER') && OPENSSL_VERSION_NUMBER >= 0x1010100F],
            'ICU (intl)' => [defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : __('missing'), __('any'), defined('INTL_ICU_VERSION')],
        ];

        $body = collect($rows)->map(function (array $row, string $label): string {
            [$current, $recommended, $ok] = $row;
            $color = $ok === null ? '' : ';color:'.($ok ? 'var(--success-600)' : 'var(--warning-600)').';font-weight:600';

            return '<tr><td style="padding:.15rem 1.5rem .15rem 0;color:var(--gray-500);white-space:nowrap;vertical-align:top">'.e($label).'</td>'
                .'<td style="padding:.15rem 1.5rem .15rem 0;word-break:break-all'.$color.'">'.e($current).'</td>'
                .'<td style="padding:.15rem 0;color:var(--gray-500)">'.e($recommended).'</td></tr>';
        })->implode('');

        $head = '<tr><th style="padding:.15rem 1.5rem .15rem 0;text-align:start">'.e(__('Setting')).'</th>'
            .'<th style="padding:.15rem 1.5rem .15rem 0;text-align:start">'.e(__('This server')).'</th>'
            .'<th style="padding:.15rem 0;text-align:start">'.e(__('Recommended')).'</th></tr>';

        return Section::make(__('PHP'))
            ->compact()
            ->collapsible()
            ->schema([$this->table($body, $head, '<colgroup><col style="width:13rem"><col><col></colgroup>')]);
    }

    protected function extensionsSection(): Section
    {
        $loaded = array_map('strtolower', get_loaded_extensions());
        sort($loaded, SORT_NATURAL);
        $required = Requirements::definition()['extensions'];
        $needed = array_values(array_intersect($loaded, $required));
        $other = array_values(array_diff($loaded, $required));

        $list = fn (array $names): string => '<div style="font-size:.875rem">'.e(implode(', ', $names)).'</div>';

        return Section::make(__('Loaded PHP extensions'))
            ->compact()
            ->collapsible()
            ->collapsed()
            ->schema([
                Text::make(__('Required by epesi'))->weight(FontWeight::Bold),
                Text::make(new HtmlString($list($needed))),
                Text::make(__('Also installed'))->weight(FontWeight::Bold),
                Text::make(new HtmlString($list($other))),
            ]);
    }

    /** @param  array<string, string>  $facts */
    protected function facts(array $facts): Text
    {
        $body = collect($facts)->map(fn (string $value, string $label): string => '<tr><td style="padding:.15rem 1.5rem .15rem 0;color:var(--gray-500);white-space:nowrap;vertical-align:top">'.e($label).'</td>'
            .'<td style="padding:.15rem 0;word-break:break-all">'.e($value).'</td></tr>')->implode('');

        return $this->table($body);
    }

    /** @param  string  $cols  a `<colgroup>`: switches the table to fixed layout, so columns without a width come out equal */
    protected function table(string $body, string $head = '', string $cols = ''): Text
    {
        $style = $cols === '' ? '' : ';width:100%;table-layout:fixed';

        return Text::make(new HtmlString('<div style="overflow-x:auto"><table style="font-size:.875rem'.$style.'">'.$cols.$head.$body.'</table></div>'))
            ->weight(FontWeight::Normal);
    }

    protected function diskSpace(): string
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if ($free === false || $total === false) {
            return __('unknown');
        }

        return round($free / 1073741824, 1).' GB '.__('free of').' '.round($total / 1073741824, 1).' GB';
    }

    protected function database(): string
    {
        try {
            $connection = DB::connection();
            $version = $connection->getDriverName() === 'sqlite'
                ? $connection->selectOne('select sqlite_version() as v')->v
                : $connection->selectOne('select version() as v')->v;

            return $connection->getDriverName().' '.$version;
        } catch (Throwable) {
            return __('not reachable');
        }
    }
}
