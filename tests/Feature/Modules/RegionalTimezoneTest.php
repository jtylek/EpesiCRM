<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Support\Calendar\CalendarRegistry;
use Carbon\Carbon;
use Epesi\Modules\CRM\PhoneCalls\Calendar\PhoneCallCalendarProvider;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Calendar\TaskCalendarProvider;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/** Stored times are UTC; every screen shows and reads them in the user's regional timezone. */
class RegionalTimezoneTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function warsawUser(string $timeFormat = 'H:i'): User
    {
        $user = $this->userWithRole('manager');
        RegionalSetting::query()->create([
            'user_id' => $user->id,
            'timezone' => 'Europe/Warsaw',
            'date_format' => 'd/m/Y',
            'time_format' => $timeFormat,
        ]);

        return $user;
    }

    public function test_filament_uses_the_signed_in_users_timezone_and_formats(): void
    {
        $this->actingAs($this->warsawUser());

        $this->assertSame('Europe/Warsaw', FilamentTimezone::get());
        $this->assertSame('d/m/Y H:i', RegionalSetting::dateTimeFormat());
        $this->assertSame(
            '15/06/2026 14:30',
            RegionalSetting::display(Carbon::parse('2026-06-15 12:30:00', 'UTC')),
        );
    }

    public function test_a_user_without_settings_gets_the_system_defaults(): void
    {
        $this->actingAs($this->userWithRole('manager'));

        $this->assertSame(config('app.timezone'), FilamentTimezone::get());
        $this->assertSame('Y-m-d H:i', RegionalSetting::dateTimeFormat());
    }

    public function test_wall_clock_values_round_trip_through_the_users_timezone(): void
    {
        $this->actingAs($this->warsawUser());

        $stored = RegionalSetting::fromUser(Carbon::parse('2026-01-10 00:30:00'));

        $this->assertSame('2026-01-09 23:30:00', $stored->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-10 00:30:00', RegionalSetting::toUser($stored)->format('Y-m-d H:i:s'));
    }

    public function test_calendar_events_are_shifted_to_the_users_clock_and_all_day_ones_are_not(): void
    {
        $user = $this->warsawUser();
        $this->actingAs($user);

        PhoneCall::create(['subject' => 'Call', 'called_at' => '2026-06-15 22:30:00']);
        Task::create(['title' => 'Timeless', 'deadline' => '2026-06-16 00:00:00', 'timeless' => true]);

        $call = CalendarRegistry::eventsBetween(
            PhoneCallCalendarProvider::class,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-22'),
            $user,
        );
        $task = CalendarRegistry::eventsBetween(
            TaskCalendarProvider::class,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-22'),
            $user,
        );

        // 22:30 UTC is already the next morning in Warsaw (UTC+2 in June).
        $this->assertSame('2026-06-16 00:30:00', $call->first()->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-16 00:00:00', $task->first()->start->format('Y-m-d H:i:s'));
    }

    public function test_creating_from_a_calendar_slot_and_rescheduling_store_utc(): void
    {
        $user = $this->warsawUser();
        $this->actingAs($user);

        $url = PhoneCallCalendarProvider::calendarCreateUrl(Carbon::parse('2026-06-15 10:00:00'), false);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('2026-06-15 08:00:00', Carbon::parse($query['called_at'])->utc()->format('Y-m-d H:i:s'));

        $call = PhoneCall::create(['subject' => 'Call', 'called_at' => '2026-06-15 08:00:00']);
        $this->assertTrue(PhoneCallCalendarProvider::calendarReschedule(
            (string) $call->id,
            RegionalSetting::fromUser(Carbon::parse('2026-06-17 11:00:00')),
            null,
            false,
            $user,
        ));
        $this->assertSame('2026-06-17 09:00:00', $call->fresh()->called_at->format('Y-m-d H:i:s'));
    }
}
