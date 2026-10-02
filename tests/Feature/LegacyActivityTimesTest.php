<?php

namespace Tests\Feature;

use App\Services\LegacyImport\ActivityTimes;
use App\Services\LegacyImport\Importers\MeetingsImporter;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyActivityTimesTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_meeting_imports_round_the_combined_legacy_date_and_epoch_time(): void
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');
        Schema::connection('legacy')->create('crm_meeting_data_1', function (Blueprint $table): void {
            $table->integer('id');
            $table->dateTime('created_on');
            $table->string('f_title');
            $table->date('f_date');
            $table->dateTime('f_time');
        });
        DB::connection('legacy')->table('crm_meeting_data_1')->insert([
            'id' => 9,
            'created_on' => '2026-09-01 10:07:31',
            'f_title' => 'Imported near midnight',
            'f_date' => '2026-10-01',
            'f_time' => '1970-01-01 23:58:00',
        ]);

        (new MeetingsImporter)->run(withHistory: false);
        $meeting = Meeting::where('legacy_id', 9)->firstOrFail();
        $this->assertSame('2026-10-02 00:00:00', $meeting->starts_at->toDateTimeString());
        $this->assertSame('2026-09-01 10:07:31', $meeting->created_at->toDateTimeString());
    }

    public function test_rounding_handles_midnight_seconds_and_timeless_tasks(): void
    {
        $this->assertSame(['date' => '2026-10-02', 'time' => '00:00:00'], ActivityTimes::rounded('meetings', ['date' => '2026-10-01', 'time' => '23:58:00']));
        $this->assertSame(['called_at' => '2026-10-01 12:00:00'], ActivityTimes::rounded('phone_calls', ['called_at' => '2026-10-01 12:02:29']));
        $this->assertSame(['called_at' => '2026-10-01 12:05:00'], ActivityTimes::rounded('phone_calls', ['called_at' => '2026-10-01 12:02:30']));
        $this->assertSame(['deadline' => null], ActivityTimes::rounded('tasks', ['deadline' => null]));
        $timeless = ['deadline' => '2026-10-01 23:59:00', 'timeless' => true];
        $this->assertSame($timeless, ActivityTimes::rounded('tasks', $timeless));
    }

    public function test_command_previews_then_rounds_only_imported_records_and_is_idempotent(): void
    {
        Storage::fake('local');
        $meeting = Meeting::create(['title' => 'Imported meeting', 'date' => '2026-10-01', 'time' => '23:58:00']);
        $meeting->forceFill(['legacy_id' => 1])->save();
        $reminder = $meeting->reminders()->create(['remind_at' => '2026-10-01 23:48:00', 'before_minutes' => 10]);
        $call = PhoneCall::create(['subject' => 'Imported call', 'called_at' => '2026-10-01 10:02:00']);
        $call->forceFill(['legacy_id' => 1])->save();
        $task = Task::create(['title' => 'Imported task', 'deadline' => '2026-10-01 12:03:00', 'timeless' => false]);
        $task->forceFill(['legacy_id' => 1])->save();
        $local = Meeting::create(['title' => 'Local meeting', 'date' => '2026-10-01', 'time' => '11:03:00']);
        $updatedAt = $meeting->updated_at->toDateTimeString();

        $this->artisan('import:round-times')->expectsOutput('meetings: 1')->assertSuccessful();
        $this->assertSame('23:58:00', $meeting->fresh()->time);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->travel(1)->hour();
        $this->artisan('import:round-times --apply')->assertSuccessful();
        $this->assertSame('2026-10-02 00:00:00', $meeting->fresh()->starts_at->toDateTimeString());
        $this->assertSame('2026-10-01 10:00:00', $call->fresh()->called_at->toDateTimeString());
        $this->assertSame('2026-10-01 12:05:00', $task->fresh()->deadline->toDateTimeString());
        $this->assertSame('11:03:00', $local->fresh()->time);
        $this->assertSame($updatedAt, $meeting->fresh()->updated_at->toDateTimeString());
        $this->assertSame('2026-10-01 23:50:00', $reminder->fresh()->remind_at->toDateTimeString());
        $backups = Storage::disk('local')->allFiles('legacy-time-rounding');
        $this->assertCount(1, $backups);
        $this->assertCount(3, json_decode(Storage::disk('local')->get($backups[0]), true));
        $this->artisan('import:round-times --apply')->expectsOutput('meetings: 0')->assertSuccessful();
        $this->assertCount(1, Storage::disk('local')->allFiles('legacy-time-rounding'));
    }
}
