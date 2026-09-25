<?php

namespace Epesi\Modules\CommonData\Facades;

use Epesi\Modules\CommonData\CommonDataRepository;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, string> array(string $path, string $order = 'value', bool $translate = true)
 * @method static array<string, string> raw(string $path, string $order = 'value')
 * @method static string|null value(string $path, bool $translate = true)
 * @method static array<string, string> tree(string $path, string $order = 'value', int $depth = 0)
 * @method static \Epesi\Modules\CommonData\Models\CommonDataNode|null node(string $path)
 * @method static bool exists(string $path)
 * @method static \Epesi\Modules\CommonData\Models\CommonDataNode ensure(string $path, bool $readonly = false)
 * @method static \Epesi\Modules\CommonData\Models\CommonDataNode set(string $path, ?string $value, bool $readonly = false)
 * @method static void seed(string $path, array $items, bool $overwrite = false, bool $readonly = true)
 * @method static bool remove(string $path)
 * @method static void resetOrderByKey(string $path)
 *
 * @see CommonDataRepository
 */
class CommonData extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CommonDataRepository::class;
    }
}
