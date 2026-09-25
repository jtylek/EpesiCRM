<?php

namespace Epesi\Modules\CommonData\Models;

use Closure;
use Epesi\Modules\CommonData\CommonDataRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One reference-data entry — one row of `common_data`, one node of Epesi's
 * `utils_commondata_tree`.
 *
 * Path maintenance, position defaulting and the readonly guard all live here
 * rather than in the repository or the Filament page, so a node created by
 * seeder, tinker or import behaves the same as one typed into the GUI. Epesi
 * puts the equivalent logic in `Utils_CommonDataCommon`'s static methods, which
 * is why writing to `utils_commondata_tree` directly corrupts positions there.
 */
class CommonDataNode extends Model
{
    protected $table = 'common_data';

    protected $fillable = [
        'parent_id',
        'key',
        'value',
        'readonly',
        'position',
    ];

    /**
     * Guards a readonly node against edits. The administration panel is
     * super_admin-only and filament-shield gives super_admin a Gate::before
     * bypass, so a policy check would never fire for the one role that can
     * reach the editor — the guard has to sit in the model to mean anything.
     */
    protected static bool $readonlyProtection = true;

    protected function casts(): array
    {
        return [
            'readonly' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Runs $callback with the readonly guard lifted — how a module's own seed
     * updates the arrays it owns (Epesi's `new_array($name, $array, true, true)`).
     */
    public static function withoutReadonlyProtection(Closure $callback): mixed
    {
        $previous = static::$readonlyProtection;
        static::$readonlyProtection = false;

        try {
            return $callback();
        } finally {
            static::$readonlyProtection = $previous;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (self $node): void {
            if ($node->position === null || $node->position === 0) {
                $node->position = static::query()
                    ->where('parent_id', $node->parent_id)
                    ->max('position') + 1;
            }
        });

        static::saving(function (self $node): void {
            $node->assertKeyIsUsable();
            $node->assertWritable();

            $node->path = $node->buildPath();
        });

        static::saved(function (self $node): void {
            // A rename or a move leaves every descendant holding a path that
            // starts with the old one.
            if ($node->wasChanged('path')) {
                $node->rewriteDescendantPaths($node->getOriginal('path'), $node->path);
            }

            CommonDataRepository::invalidate();
        });

        static::deleting(function (self $node): void {
            $node->assertWritable();
        });

        static::deleted(function (self $node): void {
            CommonDataRepository::invalidate();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * The path segments, so a page can offer one breadcrumb per ancestor
     * without querying for them.
     */
    public function segments(): array
    {
        return explode('/', $this->path);
    }

    public function parentPath(): ?string
    {
        $segments = $this->segments();
        array_pop($segments);

        return $segments === [] ? null : implode('/', $segments);
    }

    protected function buildPath(): string
    {
        $parentPath = $this->parent_id
            ? static::query()->whereKey($this->parent_id)->value('path')
            : null;

        return $parentPath ? $parentPath.'/'.$this->key : (string) $this->key;
    }

    protected function assertKeyIsUsable(): void
    {
        $key = (string) $this->key;

        if (trim($key) === '') {
            throw new RuntimeException('A common data key cannot be empty.');
        }

        // The path separator. Epesi raises the same error in new_array() and
        // rename_key(); here it would also break path-prefix subtree queries.
        if (str_contains($key, '/')) {
            throw new RuntimeException("Invalid common data key \"{$key}\": keys cannot contain \"/\".");
        }
    }

    protected function assertWritable(): void
    {
        if (! static::$readonlyProtection) {
            return;
        }

        // `readonly` on an unsaved node is whatever the caller asked for, and
        // creating a readonly node is exactly what a seed does.
        $wasReadonly = $this->exists && $this->getOriginal('readonly');

        if ($wasReadonly) {
            throw new RuntimeException(
                "\"{$this->getOriginal('path')}\" is owned by a module and cannot be changed here."
            );
        }
    }

    protected function rewriteDescendantPaths(?string $oldPath, string $newPath): void
    {
        if ($oldPath === null || $oldPath === $newPath) {
            return;
        }

        DB::statement(
            'UPDATE common_data SET path = CONCAT(?, SUBSTRING(path, ?)) WHERE path LIKE ?',
            [$newPath, strlen($oldPath) + 1, static::escapeLike($oldPath).'/%']
        );
    }

    /**
     * A key may legitimately contain % or _, which LIKE would read as
     * wildcards and match sibling subtrees.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
