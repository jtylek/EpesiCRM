<?php

namespace Epesi\Modules\Appearance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's theme choice — a row exists only for a user who has actually
 * saved one (see choose()), not for everyone up front.
 */
class UserAppearance extends Model
{
    protected $table = 'epesi_appearance_selections';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'theme_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Theme, $this> */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    /** The id of the theme this user last saved, if any. */
    public static function themeIdFor(User $user): ?int
    {
        return static::query()->where('user_id', $user->getKey())->value('theme_id');
    }

    /** Null clears the choice: the user goes back to following the default theme. */
    public static function choose(User $user, ?int $themeId): void
    {
        static::query()->updateOrCreate(['user_id' => $user->getKey()], ['theme_id' => $themeId]);
    }
}
