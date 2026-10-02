<?php

namespace Epesi\Modules\RegionalSettings\Models;

use App\Models\User;
use App\Support\Demo;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Epesi\Modules\RegionalSettings\Calendar\CalendarDateConverter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * One row per user — the port of legacy's Base_RegionalSettingsCommon, which
 * kept these as per-user key/value pairs in Base_User_Settings. A dedicated
 * table with a real user_id column is the plain Eloquent equivalent.
 */
class RegionalSetting extends Model
{
    protected $table = 'epesi_regional_settings';

    protected $fillable = [
        'user_id',
        'language',
        'timezone',
        'date_format',
        'time_format',
        'calendar_system',
        'hijri_variant',
        'country',
        'state',
    ];

    /**
     * date()-format tokens, not legacy's strftime() ones — strftime() is
     * removed as of PHP 8.1, so there is nothing to stay parity-compatible
     * with here.
     *
     * @return array<string, string>
     */
    public const DATE_FORMATS = [
        'Y-m-d' => 'Y-m-d',
        'm/d/Y' => 'm/d/Y',
        'd/m/Y' => 'd/m/Y',
        'd F Y' => 'd F Y',
        'd M Y' => 'd M Y',
        'M d, Y' => 'M d, Y',
    ];

    /**
     * @return array<string, string>
     */
    public const TIME_FORMATS = [
        'g:i A' => '12h am/pm',
        'H:i' => '24h',
    ];

    /** Resolved settings per signed-in user. Scoped to the request, so it never outlives one (or a test). */
    public const RESOLVED_BINDING = 'epesi.regional-settings.resolved';

    /** Demo-mode languages that come with their own calendar: Arabic the Umm al-Qura Hijri, Persian the Jalali. */
    private const DEMO_CALENDARS = ['ar' => 'hijri', 'fa' => 'jalali'];

    protected static function booted(): void
    {
        static::saved(fn () => app(self::RESOLVED_BINDING)->exchangeArray([]));
        static::deleted(fn () => app(self::RESOLVED_BINDING)->exchangeArray([]));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function current(): self
    {
        $defaults = static::defaults();

        // The extra attributes are spelled out even where they match the
        // column defaults: firstOrCreate()'s returned instance reflects only
        // what it was given, never what the database itself filled in.
        return static::query()->firstOrCreate(
            ['user_id' => Auth::id()],
            $defaults->only([
                'timezone', 'date_format', 'time_format', 'calendar_system', 'hijri_variant', 'country', 'state',
            ]),
        );
    }

    /**
     * The system-wide defaults a user starts from — the row with no user, as
     * set by the setup wizard, or the built-in ones when there is none.
     */
    public static function defaults(): self
    {
        return static::query()->whereNull('user_id')->first() ?? new static([
            'timezone' => config('app.timezone', 'UTC'),
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'calendar_system' => 'gregorian',
            'hijri_variant' => 'umalqura',
        ]);
    }

    /**
     * The settings that apply to whoever is signed in right now, without
     * creating a row the way current() does — this runs on every date a page
     * renders, including for guests. A user with no row of their own gets the
     * system defaults. Cached for the request.
     */
    public static function effective(): self
    {
        $resolved = app(self::RESOLVED_BINDING);
        $key = (string) Auth::id();

        if (! isset($resolved[$key])) {
            $resolved[$key] = (Auth::id() === null ? null : static::query()->where('user_id', Auth::id())->first())
                ?? static::defaults();

            // The demo's accounts are shared by every visitor, so a visitor's
            // calendar can't be stored on one: Arabic and Persian visitors
            // get theirs (see DEMO_CALENDARS) for this request instead.
            if (Demo::enabled() && isset(self::DEMO_CALENDARS[app()->getLocale()])) {
                $resolved[$key] = $resolved[$key]->replicate()->forceFill([
                    'calendar_system' => self::DEMO_CALENDARS[app()->getLocale()],
                    'hijri_variant' => 'umalqura',
                ]);
            }
        }

        return $resolved[$key];
    }

    /**
     * The timezone every stored (UTC) moment is shown and typed in for the
     * signed-in user.
     */
    public static function timezoneName(): string
    {
        $timezone = static::effective()->timezone;

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : (string) config('app.timezone', 'UTC');
    }

    /** PHP date() format of the signed-in user's dates. */
    public static function dateFormat(): string
    {
        return static::effective()->date_format ?: 'Y-m-d';
    }

    /** PHP date() format of the signed-in user's times; $seconds adds them. */
    public static function timeFormat(bool $seconds = false): string
    {
        $format = static::effective()->time_format ?: 'H:i';

        return $seconds ? str_replace('i', 'i:s', $format) : $format;
    }

    public static function dateTimeFormat(bool $seconds = false): string
    {
        return static::dateFormat().' '.static::timeFormat($seconds);
    }

    public static function calendarSystem(): string
    {
        $system = static::effective()->calendar_system;

        return in_array($system, CalendarDateConverter::SYSTEMS, true) ? $system : 'gregorian';
    }

    public static function hijriVariant(): string
    {
        $variant = static::effective()->hijri_variant;

        return in_array($variant, CalendarDateConverter::HIJRI_VARIANTS, true) ? $variant : 'umalqura';
    }

    public static function formatCalendarDate(string $gregorianDate): string
    {
        return CalendarDateConverter::format($gregorianDate, static::calendarSystem(), static::hijriVariant());
    }

    public static function parseCalendarDate(string $calendarDate): string
    {
        return CalendarDateConverter::parse($calendarDate, static::calendarSystem(), static::hijriVariant());
    }

    public static function formatDateInput(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return static::formatCalendarDate(Carbon::parse($state)->format('Y-m-d'));
    }

    public static function parseDateInput(string $calendarDate): string
    {
        return static::parseCalendarDate($calendarDate);
    }

    public static function formatDateTimeInput(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $moment = static::toUser(Carbon::parse($state, config('app.timezone', 'UTC')));

        return static::formatCalendarDate($moment->format('Y-m-d')).' '.$moment->format('H:i');
    }

    public static function parseDateTimeInput(string $value): string
    {
        $value = CalendarDateConverter::normalizeInputDigits($value);

        if (! preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})(?::(\d{2}))?$/', $value, $matches)) {
            throw new \InvalidArgumentException('Date and time must use YYYY-MM-DD HH:MM.');
        }

        $date = static::parseCalendarDate($matches[1]);
        $time = $matches[2].':'.($matches[3] ?? '00');
        $wallClock = Carbon::createFromFormat('!Y-m-d H:i:s', $date.' '.$time, 'UTC');

        if ($wallClock === false || $wallClock->format('Y-m-d H:i:s') !== $date.' '.$time) {
            throw new \InvalidArgumentException('The date and time are invalid.');
        }

        return static::fromUser($wallClock)->format('Y-m-d H:i:s');
    }

    /**
     * A stored moment as the signed-in user sees it: same instant, their
     * timezone. Hand the result to ->format(static::dateTimeFormat()).
     */
    public static function toUser(CarbonInterface $moment): Carbon
    {
        return Carbon::instance($moment)->setTimezone(static::timezoneName());
    }

    /**
     * A stored moment rendered for the signed-in user, in their timezone and
     * formats; a $dateOnly one (a timeless task) is a calendar day, so it
     * keeps its own date instead of being shifted.
     */
    public static function display(?CarbonInterface $moment, bool $dateOnly = false): ?string
    {
        if ($moment === null) {
            return null;
        }

        return $dateOnly
            ? static::effective()->formatDateOnly($moment)
            : static::effective()->formatDateTime($moment);
    }

    /**
     * The signed-in user's current wall-clock time as a floating value
     * (labelled UTC, like the calendar's own dates) — what "now" reads as on
     * their clock.
     */
    public static function nowAsWallClock(): Carbon
    {
        return Carbon::parse(static::toUser(Carbon::now())->format('Y-m-d H:i:s'), 'UTC');
    }

    /**
     * The reverse, for a wall-clock value the user picked or dragged to: it is
     * read in their timezone and returned as the same instant in the app's.
     */
    public static function fromUser(CarbonInterface $wallClock): Carbon
    {
        return Carbon::parse($wallClock->format('Y-m-d H:i:s'), static::timezoneName())
            ->setTimezone((string) config('app.timezone', 'UTC'));
    }

    /**
     * Render a moment in this user's own timezone and date format —
     * legacy's time2reg($t, false, true).
     */
    public function formatDate(CarbonInterface $moment): string
    {
        $gregorianDate = $moment->clone()->setTimezone($this->timezone ?: config('app.timezone', 'UTC'))->format('Y-m-d');

        if (($this->calendar_system ?? 'gregorian') !== 'gregorian') {
            return CalendarDateConverter::format($gregorianDate, $this->calendar_system, $this->hijri_variant ?? 'umalqura');
        }

        return $moment->clone()->setTimezone($this->timezone ?: config('app.timezone', 'UTC'))->format($this->date_format ?: 'Y-m-d');
    }

    public function formatDateOnly(CarbonInterface $moment): string
    {
        $gregorianDate = $moment->format('Y-m-d');

        if (($this->calendar_system ?? 'gregorian') !== 'gregorian') {
            return CalendarDateConverter::format($gregorianDate, $this->calendar_system, $this->hijri_variant ?? 'umalqura');
        }

        return Carbon::parse($gregorianDate)->format($this->date_format ?: 'Y-m-d');
    }

    /**
     * Legacy's time2reg($t, true, false).
     */
    public function formatTime(CarbonInterface $moment): string
    {
        return $moment->clone()->setTimezone($this->timezone ?: config('app.timezone', 'UTC'))->format($this->time_format ?: 'H:i');
    }

    /**
     * Legacy's time2reg($t) default (date and time, seconds dropped).
     */
    public function formatDateTime(CarbonInterface $moment): string
    {
        return $this->formatDate($moment).' '.$this->formatTime($moment);
    }

    public function formatWallClockDateTime(CarbonInterface $moment): string
    {
        return $this->formatDateOnly($moment).' '.$moment->format($this->time_format ?: 'H:i');
    }
}
