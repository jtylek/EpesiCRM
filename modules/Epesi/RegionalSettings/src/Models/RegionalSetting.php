<?php

namespace Epesi\Modules\RegionalSettings\Models;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
            $defaults->only(['timezone', 'date_format', 'time_format', 'country', 'state']),
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
            ? $moment->format(static::dateFormat())
            : static::toUser($moment)->format(static::dateTimeFormat());
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
        return $moment->clone()->setTimezone($this->timezone)->format($this->date_format);
    }

    /**
     * Legacy's time2reg($t, true, false).
     */
    public function formatTime(CarbonInterface $moment): string
    {
        return $moment->clone()->setTimezone($this->timezone)->format($this->time_format);
    }

    /**
     * Legacy's time2reg($t) default (date and time, seconds dropped).
     */
    public function formatDateTime(CarbonInterface $moment): string
    {
        return $this->formatDate($moment).' '.$this->formatTime($moment);
    }
}
