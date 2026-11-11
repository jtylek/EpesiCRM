<?php

namespace Epesi\Modules\MigrationWizard\Import;

use Illuminate\Support\Str;
use Throwable;

/**
 * What the migration is doing right now, for the migration page's status lines ("Importing e-mails: 10,000 of 82,000
 * messages", "Verifying the moved files: 12,000 of 75,152 files" and "Estimated time for this step: about 25 min").
 * The import, the verification and the cleanup write it while they hold the migration
 * lock; the page reads it without taking the lock. It is only a display aid: never state, never read back by the
 * migration itself, and any failure to write it is ignored.
 */
final class ImportProgress
{
    /** A report older than this is not shown (the import is not running). */
    private const FRESH_SECONDS = 600;

    /** The estimate needs this much time and this many records of the current step before it is shown. */
    private const MIN_SECONDS = 15;

    private const MIN_RECORDS = 50;

    /** The estimate uses the speed of about the last five minutes. */
    private const WINDOW_SECONDS = 300;

    private const SAMPLE_SECONDS = 20;

    public static function path(): string
    {
        return storage_path('app/migration-wizard/progress.json');
    }

    /**
     * A recordset import reports its own counts. A step (one tab and phase) keeps when it began and how many
     * records were in then, across slices, and a few recent points, so the estimate follows the current speed.
     */
    public static function records(string $tab, string $phase, int $done, int $total): void
    {
        self::track($tab, $phase, $done, $total);
    }

    /** The tab of a step outside the import (verification, removal of data nobody owns, cleanup). */
    private const STAGE = '_stage';

    /** When a stage last wrote its counts: a stage reports every item, the file is written at most once a second. */
    private static float $lastStageWrite = 0.0;

    private static ?string $lastStageText = null;

    /**
     * A step outside the import: "Verifying the moved files: 12,000 of 75,152 files", with speed and estimate like
     * an importer's, or just its text when it has no counts. Safe to call for every item: written at most once a
     * second, and always on the first and the last item.
     */
    public static function stage(string $text, ?int $done = null, ?int $total = null, string $unit = 'files'): void
    {
        $now = microtime(true);
        // The first and the last item of a stage, and a new stage, are always written.
        $edge = $done === null || $total === null || $done <= 1 || $done >= $total || $text !== self::$lastStageText;
        if (! $edge && $now - self::$lastStageWrite < 1.0) {
            return;
        }
        self::$lastStageWrite = $now;
        self::$lastStageText = $text;
        if ($done === null || $total === null) {
            self::write(['tab' => self::STAGE, 'phase' => $text, 'text' => $text, 'unit' => $unit, 'done' => null, 'total' => null]);

            return;
        }
        self::track(self::STAGE, $text, $done, $total, ['text' => $text, 'unit' => $unit]);
    }

    /**
     * A step (one tab and phase) keeps when it began and how many were done then, across slices, and a few recent
     * points, so the estimate follows the current speed.
     *
     * @param  array<string, mixed>  $extra
     */
    private static function track(string $tab, string $phase, int $done, int $total, array $extra = []): void
    {
        $now = time();
        $previous = self::read();
        $same = $previous !== null
            && ($previous['tab'] ?? null) === $tab
            && ($previous['phase'] ?? null) === $phase
            && is_int($previous['since'] ?? null)
            && is_int($previous['since_done'] ?? null)
            && $previous['since_done'] <= $done;

        self::write([
            'tab' => $tab,
            'phase' => $phase,
            'done' => $done,
            'total' => $total,
            'since' => $same ? $previous['since'] : $now,
            'since_done' => $same ? $previous['since_done'] : $done,
            'samples' => self::samples($same ? (array) ($previous['samples'] ?? []) : [], $now, $done),
        ] + $extra);
    }

    /**
     * A few recent [time, done] points of the step, at least SAMPLE_SECONDS apart, none older than WINDOW_SECONDS,
     * so the estimate follows the current speed (which changes: bigger messages, a resumed run, a faster setting).
     *
     * @param  array<int, mixed>  $samples
     * @return list<array{0: int, 1: int}>
     */
    private static function samples(array $samples, int $now, int $done): array
    {
        $kept = array_values(array_filter($samples, static fn ($s): bool => is_array($s) && count($s) === 2 && is_int($s[0]) && is_int($s[1]) && $s[0] >= $now - self::WINDOW_SECONDS && $s[1] <= $done));
        $last = $kept === [] ? null : $kept[count($kept) - 1];
        if ($last === null || $now - $last[0] >= self::SAMPLE_SECONDS) {
            $kept[] = [$now, $done];
        }

        return $kept;
    }

    /** An importer that does not report counts: just its name. */
    public static function started(string $tab): void
    {
        // A new slice of the same importer keeps the count it had until it reports a newer one.
        $previous = self::read();
        if ($previous !== null && ($previous['tab'] ?? null) === $tab) {
            return;
        }
        self::write(['tab' => $tab, 'phase' => 'records', 'done' => null, 'total' => null]);
    }

    public static function clear(): void
    {
        try {
            @unlink(self::path());
        } catch (Throwable) {
        }
    }

    /** @return string|null the line to show, null when nothing is being imported */
    public static function line(): ?string
    {
        $data = self::read();
        if ($data === null) {
            return null;
        }

        $tab = (string) $data['tab'];
        $phase = (string) ($data['phase'] ?? '');
        $counts = self::counts($data) ? number_format(min($data['done'], $data['total'])).' of '.number_format($data['total']) : null;

        if ($tab === self::STAGE) {
            $text = (string) ($data['text'] ?? $phase);

            return $counts === null ? $text.'...' : $text.': '.$counts.' '.(string) ($data['unit'] ?? 'files');
        }

        if ($tab === 'mail') {
            // The mail import has its own two stages: the messages, then joining them into threads.
            return match ($phase) {
                'messages' => 'Importing e-mails'.($counts === null ? '' : ": {$counts} messages"),
                'threads' => 'Importing e-mails: joining threads'.($counts === null ? '' : ", {$counts}"),
                default => 'Importing e-mails',
            };
        }

        $text = 'Importing '.Str::plural(str_replace('_', ' ', $tab));
        if ($phase === 'history') {
            $text .= ' (history)';
        }

        return $counts === null ? $text : "{$text}, {$counts}";
    }

    /**
     * The time left for the current step, from its speed over the last few minutes (the whole step's average until
     * there is a minute of it); null when the step has no counts, "calculating..." until there is enough to go on.
     */
    public static function estimate(): ?string
    {
        $data = self::read();
        if ($data === null || ! self::counts($data) || $data['done'] >= $data['total']) {
            return null; // nothing counted, or the count is complete (what follows reports its own line)
        }
        $rate = self::rate($data);

        return $rate === null ? 'calculating...' : self::duration((int) ceil(max(0, $data['total'] - $data['done']) / $rate));
    }

    /** "1,200 records per minute", from the same speed as the estimate; null until there is enough to go on. */
    public static function speed(): ?string
    {
        $data = self::read();
        $rate = $data === null || ! self::counts($data) ? null : self::rate($data);

        $unit = ($data['tab'] ?? null) === self::STAGE ? (string) ($data['unit'] ?? 'files') : 'records';

        return $rate === null ? null : number_format((int) round($rate * 60)).' '.$unit.' per minute';
    }

    /**
     * Records per second: over the recent samples when they cover a minute, otherwise since the step began.
     *
     * @param  array<string, mixed>  $data
     */
    private static function rate(array $data): ?float
    {
        $oldest = is_array($data['samples'][0] ?? null) ? $data['samples'][0] : null;
        if ($oldest !== null && (int) ($data['at'] ?? 0) - (int) $oldest[0] >= 60) {
            $elapsed = (int) $data['at'] - (int) $oldest[0];
            $progressed = (int) $data['done'] - (int) $oldest[1];
        } else {
            if (! is_int($data['since'] ?? null)) {
                return null;
            }
            $elapsed = (int) ($data['at'] ?? 0) - $data['since'];
            $progressed = (int) $data['done'] - (int) ($data['since_done'] ?? $data['done']);
        }
        if ($elapsed < self::MIN_SECONDS || $progressed < self::MIN_RECORDS) {
            return null;
        }

        return $progressed / $elapsed;
    }

    public static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return 'less than a minute';
        }
        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return "about {$minutes} min";
        }

        return 'about '.intdiv($minutes, 60).' h '.($minutes % 60).' min';
    }

    /** @param  array<string, mixed>  $data */
    private static function counts(array $data): bool
    {
        return is_int($data['done'] ?? null) && is_int($data['total'] ?? null) && $data['total'] > 0;
    }

    /** @return array<string, mixed>|null the fresh report, null when there is none */
    private static function read(): ?array
    {
        try {
            $data = json_decode((string) @file_get_contents(self::path()), true);
        } catch (Throwable) {
            return null;
        }

        return is_array($data) && is_string($data['tab'] ?? null) && (int) ($data['at'] ?? 0) >= time() - self::FRESH_SECONDS ? $data : null;
    }

    /** @param  array<string, mixed>  $data */
    private static function write(array $data): void
    {
        try {
            @file_put_contents(self::path(), json_encode($data + ['at' => time()]), LOCK_EX);
        } catch (Throwable) {
        }
    }
}
