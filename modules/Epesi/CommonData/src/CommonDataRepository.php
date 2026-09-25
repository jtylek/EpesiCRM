<?php

namespace Epesi\Modules\CommonData;

use Epesi\Modules\CommonData\Models\CommonDataNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Reading and writing shared reference data by path — the port of
 * `Utils_CommonDataCommon`'s static API, with the method names kept close so
 * ported call sites read the same way.
 *
 * Reached through the CommonData facade rather than directly.
 */
class CommonDataRepository
{
    /**
     * Bumped on every write; every cache key carries it. Epesi loads the whole
     * tree into statics for the request and re-reads it on the next one, which
     * makes a write's effect obvious but costs a full table read per request.
     * Caching per path instead means a write has to invalidate entries it
     * cannot enumerate — a tree() result under some ancestor may include the
     * node that just changed — and a version prefix retires all of them at
     * once. Entries under retired versions are never read again; the cache
     * store expires them on its own schedule.
     */
    protected const VERSION_KEY = 'commondata:version';

    public const ORDERS = ['value', 'key', 'position'];

    /**
     * One array's entries as key => value, ready for a Select's options.
     *
     * An unknown path is an empty array, not an error: it is what a dependent
     * select asks for before its parent field has a value.
     *
     * @return array<string, string>
     */
    public function array(string $path, string $order = 'value', bool $translate = true): array
    {
        $order = $this->validateOrder($order);

        return $this->remember("array:{$path}:{$order}:".(int) $translate, function () use ($path, $order, $translate): array {
            $parent = $this->node($path);

            if (! $parent) {
                return [];
            }

            $rows = CommonDataNode::query()
                ->where('parent_id', $parent->getKey())
                ->get(['key', 'value', 'position']);

            $items = $rows->mapWithKeys(fn (CommonDataNode $node): array => [
                $node->key => $translate ? __((string) $node->value) : (string) $node->value,
            ])->all();

            return $this->sort($items, $order, $rows);
        });
    }

    /**
     * Untranslated values — what the administration editor shows, so an
     * administrator edits the stored string rather than its translation.
     *
     * @return array<string, string>
     */
    public function raw(string $path, string $order = 'value'): array
    {
        return $this->array($path, $order, translate: false);
    }

    /**
     * One node's value. Epesi's get_value().
     */
    public function value(string $path, bool $translate = true): ?string
    {
        return $this->remember("value:{$path}:".(int) $translate, function () use ($path, $translate): ?string {
            $value = $this->node($path)?->value;

            if ($value === null) {
                return null;
            }

            return $translate ? __($value) : $value;
        });
    }

    /**
     * An array and everything below it, flattened into one options list:
     * descendants keyed by their path relative to $path and labelled with a
     * "* " depth prefix. Epesi's get_translated_tree(), which is what a
     * commondata select renders when nested values are allowed.
     *
     * @return array<string, string>
     */
    public function tree(string $path, string $order = 'value', int $depth = 0): array
    {
        $output = [];

        foreach ($this->array($path, $order) as $key => $value) {
            $output[$key] = $depth > 0 ? str_repeat('* ', $depth).$value : $value;

            foreach ($this->tree($path.'/'.$key, $order, $depth + 1) as $childKey => $childValue) {
                $output[$key.'/'.$childKey] = $childValue;
            }
        }

        return $output;
    }

    public function node(string $path): ?CommonDataNode
    {
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        return CommonDataNode::query()->where('path', $path)->first();
    }

    public function exists(string $path): bool
    {
        return $this->node($path) !== null;
    }

    /**
     * Creates the node at $path and every missing ancestor, and returns it.
     * Epesi's new_id().
     */
    public function ensure(string $path, bool $readonly = false): CommonDataNode
    {
        $parentId = null;
        $walked = '';
        $node = null;

        foreach (explode('/', trim($path, '/')) as $key) {
            if ($key === '') {
                continue;
            }

            $walked = $walked === '' ? $key : $walked.'/'.$key;

            $node = CommonDataNode::query()->where('path', $walked)->first()
                ?? CommonDataNode::withoutReadonlyProtection(fn (): CommonDataNode => CommonDataNode::create([
                    'parent_id' => $parentId,
                    'key' => $key,
                    'readonly' => $readonly,
                ]));

            $parentId = $node->getKey();
        }

        if (! $node) {
            throw new InvalidArgumentException('A common data path cannot be empty.');
        }

        return $node;
    }

    /**
     * Sets one node's value, creating it and its ancestors if needed.
     * Epesi's set_value().
     */
    public function set(string $path, ?string $value, bool $readonly = false): CommonDataNode
    {
        $node = $this->ensure($path, $readonly);

        return CommonDataNode::withoutReadonlyProtection(function () use ($node, $value, $readonly): CommonDataNode {
            $node->fill(['value' => $value, 'readonly' => $readonly])->save();

            return $node;
        });
    }

    /**
     * Installs an array a module owns — Epesi's new_array()/extend_array(),
     * which differ only in $overwrite. Entries are positioned in the order
     * given. Existing entries are left alone unless $overwrite.
     *
     * @param  array<string, string>  $items
     */
    public function seed(string $path, array $items, bool $overwrite = false, bool $readonly = true): void
    {
        $parent = $this->ensure($path, $readonly);

        CommonDataNode::withoutReadonlyProtection(function () use ($parent, $items, $overwrite, $readonly): void {
            $position = (int) CommonDataNode::query()->where('parent_id', $parent->getKey())->max('position');

            foreach ($items as $key => $value) {
                $existing = CommonDataNode::query()
                    ->where('parent_id', $parent->getKey())
                    ->where('key', (string) $key)
                    ->first();

                if ($existing) {
                    if ($overwrite) {
                        $existing->fill(['value' => $value, 'readonly' => $readonly])->save();
                    }

                    continue;
                }

                CommonDataNode::create([
                    'parent_id' => $parent->getKey(),
                    'key' => (string) $key,
                    'value' => $value,
                    'readonly' => $readonly,
                    'position' => ++$position,
                ]);
            }
        });
    }

    /**
     * Removes a node and everything under it. Epesi walks the subtree itself;
     * here the foreign key cascades.
     */
    public function remove(string $path): bool
    {
        $node = $this->node($path);

        if (! $node) {
            return false;
        }

        return (bool) CommonDataNode::withoutReadonlyProtection(fn (): ?bool => $node->delete());
    }

    /**
     * Renumbers an array's entries into key order. Epesi's
     * reset_array_positions(), reached from the same administration screen.
     */
    public function resetOrderByKey(?string $path = null): void
    {
        $parent = filled($path) ? $this->node($path) : null;

        if (filled($path) && ! $parent) {
            return;
        }

        $children = CommonDataNode::query()
            ->where('parent_id', $parent?->getKey())
            ->get()
            ->sortBy('key', SORT_NATURAL | SORT_FLAG_CASE);

        CommonDataNode::withoutReadonlyProtection(function () use ($children): void {
            $position = 0;

            foreach ($children as $child) {
                $child->position = ++$position;
                $child->save();
            }
        });
    }

    /**
     * Retires every cached read. Called from CommonDataNode's own model events,
     * so writes made anywhere invalidate.
     */
    public static function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
    }

    protected function remember(string $key, callable $callback): mixed
    {
        $version = (int) Cache::rememberForever(self::VERSION_KEY, fn (): int => 1);

        return Cache::rememberForever("commondata:{$version}:{$key}", $callback);
    }

    /**
     * @param  array<string, string>  $items
     * @param  Collection<int, CommonDataNode>  $rows
     * @return array<string, string>
     */
    protected function sort(array $items, string $order, $rows): array
    {
        if ($order === 'key') {
            ksort($items, SORT_NATURAL | SORT_FLAG_CASE);

            return $items;
        }

        if ($order === 'position') {
            $positions = $rows->pluck('position', 'key')->all();

            uksort($items, fn ($a, $b): int => ($positions[$a] ?? 0) <=> ($positions[$b] ?? 0));

            return $items;
        }

        // Sorting on the translated value, not the stored one, so a list reads
        // alphabetically in the language it is displayed in — Epesi sorts after
        // translating for the same reason.
        asort($items, SORT_LOCALE_STRING);

        return $items;
    }

    protected function validateOrder(string $order): string
    {
        return in_array($order, self::ORDERS, true) ? $order : 'value';
    }
}
