<?php

namespace App\Console\Commands;

use App\Models\LoginAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * `php artisan demo:audit`: who used the demo and from where — the login
 * audit, which demo:reset keeps, read from the command line. In demo mode
 * there is no Administration panel to read it in, and the demo has no
 * administrator login at all.
 */
class DemoAudit extends Command
{
    protected $signature = 'demo:audit
        {--days=30 : how many days back}
        {--csv : print every session as CSV, for a spreadsheet}
        {--raw : print every column of every session as CSV, for another machine to import}';

    protected $description = 'Show the demo\'s login audit: sessions, where they came from, and visits per day';

    public function handle(): int
    {
        $timezone = (string) config('demo.timezone');
        $since = now($timezone)->startOfDay()->subDays(max(0, (int) $this->option('days') - 1));

        $sessions = LoginAudit::query()
            ->where('started_at', '>=', $since->clone()->utc())
            ->orderBy('started_at')
            ->get();

        if ($this->option('raw')) {
            return $this->raw($sessions);
        }

        $rows = $sessions->map(fn (LoginAudit $audit): array => [
            'started' => $audit->started_at?->clone()->setTimezone($timezone)->format('Y-m-d H:i'),
            'minutes' => $audit->started_at && $audit->ended_at ? (int) $audit->started_at->diffInMinutes($audit->ended_at) : null,
            'login' => $audit->login,
            'ip' => $audit->ip_address,
            'host' => $audit->host_name,
            'device' => $audit->device,
        ]);

        if ($this->option('csv')) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['started', 'minutes', 'login', 'ip', 'host', 'device']);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);

            return self::SUCCESS;
        }

        if ($rows->isEmpty()) {
            $this->components->info('No logins since '.$since->toDateString().'.');

            return self::SUCCESS;
        }

        $this->table(['Started ('.$timezone.')', 'Minutes', 'Login', 'IP address', 'Host', 'Device'], $rows->all());

        $this->newLine();
        $this->table(
            ['Day', 'Sessions', 'IP addresses'],
            $sessions
                ->groupBy(fn (LoginAudit $audit): string => Carbon::parse($audit->started_at)->setTimezone($timezone)->toDateString())
                ->map(fn ($day, string $date): array => [$date, $day->count(), $day->pluck('ip_address')->unique()->count()])
                ->values()
                ->all(),
        );

        $this->components->twoColumnDetail('Sessions', (string) $sessions->count());
        $this->components->twoColumnDetail('Different IP addresses', (string) $sessions->pluck('ip_address')->unique()->count());

        return self::SUCCESS;
    }

    /**
     * Every column, UTC, unformatted — for a script on another machine to
     * merge and dedupe by id, not for a person to read (that's --csv above).
     *
     * @param  Collection<int, LoginAudit>  $sessions
     */
    protected function raw(Collection $sessions): int
    {
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'user_id', 'login', 'impersonated_by', 'started_at', 'ended_at', 'ip_address', 'host_name', 'device']);

        foreach ($sessions as $audit) {
            fputcsv($out, [
                $audit->id,
                $audit->user_id,
                $audit->login,
                $audit->impersonated_by,
                $audit->started_at?->toDateTimeString(),
                $audit->ended_at?->toDateTimeString(),
                $audit->ip_address,
                $audit->host_name,
                $audit->device,
            ]);
        }

        fclose($out);

        return self::SUCCESS;
    }
}
