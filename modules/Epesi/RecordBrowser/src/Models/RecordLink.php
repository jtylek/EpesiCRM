<?php

namespace Epesi\Modules\RecordBrowser\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One link of a "link to any record" field (Field::related()): record
 * `source` points at record `target` through its field `field`. Read and
 * written through HasRecordLinks.
 *
 * A link is named by a token, "alias:id" ("company:12") — the form's value
 * and the port of legacy's "company/12".
 *
 * @property string $source_type
 * @property int $source_id
 * @property string $field
 * @property string $target_type
 * @property int $target_id
 */
class RecordLink extends Model
{
    protected $table = 'epesi_recordbrowser_links';

    protected $fillable = [
        'source_type',
        'source_id',
        'field',
        'target_type',
        'target_id',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'target_id' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Loaded through the target model's own query, so its ownership scope
     * applies: a record the user can't see comes back null.
     *
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function token(): string
    {
        return static::tokenFor($this->target_type, $this->target_id);
    }

    public static function tokenFor(string $alias, int|string $id): string
    {
        return $alias.':'.$id;
    }

    /**
     * @return array{0: string, 1: int}|null [alias, id], or null for anything
     *                                       that isn't a token
     */
    public static function parseToken(mixed $token): ?array
    {
        if (! is_string($token) || ! preg_match('/^([a-z0-9_]+):(\d+)$/', $token, $matches)) {
            return null;
        }

        return [$matches[1], (int) $matches[2]];
    }
}
