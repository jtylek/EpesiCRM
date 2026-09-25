<?php

namespace Epesi\Modules\RegionalSettings\Models;

use App\Models\User;
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
