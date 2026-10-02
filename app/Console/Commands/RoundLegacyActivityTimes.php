<?php

namespace App\Console\Commands;

use App\Services\LegacyImport\ActivityTimes;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RoundLegacyActivityTimes extends Command
{
    protected $signature = 'import:round-times {--apply : Save the rounded times after backing up their original values}';

    protected $description = 'Preview or round imported activity times to the nearest five minutes';

    public function handle(): int
    {
        $changes = [];

        foreach ([Meeting::class, PhoneCall::class, Task::class] as $class) {
            $class::withoutGlobalScopes()->whereNotNull('legacy_id')->chunkById(200, function ($records) use (&$changes, $class): void {
                foreach ($records as $record) {
                    $original = $record->getRawOriginal();
                    $rounded = ActivityTimes::rounded($record->getTable(), $original);
                    $changed = array_diff_assoc($rounded, $original);

                    if ($changed !== []) {
                        $changes[] = [
                            'model' => $class,
                            'id' => $record->getKey(),
                            'legacy_id' => $record->legacy_id,
                            'before' => array_intersect_key($original, $changed),
                            'after' => $changed,
                        ];
                    }
                }
            });
        }

        foreach ([Meeting::class, PhoneCall::class, Task::class] as $class) {
            $count = count(array_filter($changes, fn (array $change): bool => $change['model'] === $class));
            $this->line((new $class)->getTable().': '.$count);
        }

        if (! $this->option('apply') || $changes === []) {
            $this->info(count($changes).' imported activities need rounding. Use --apply to save.');

            return self::SUCCESS;
        }

        $backup = 'legacy-time-rounding/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';

        if (! Storage::disk('local')->put($backup, json_encode($changes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
            $this->error('Could not save the original times; no records were changed.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($changes): void {
            foreach ($changes as $change) {
                $record = $change['model']::withoutGlobalScopes()->lockForUpdate()->findOrFail($change['id']);

                foreach ($change['before'] as $column => $value) {
                    if ($record->getRawOriginal($column) !== $value) {
                        throw new \RuntimeException('An activity changed after the preview; no times were saved.');
                    }
                }

                // Preserve imported metadata, while saved events update relative reminders
                // and the activity log records the adjustment instead of rewriting history.
                $record->timestamps = false;
                $record->forceFill($change['after'])->save();
            }
        });

        $this->info(count($changes).' imported activities rounded. Original values: '.Storage::disk('local')->path($backup));

        return self::SUCCESS;
    }
}
