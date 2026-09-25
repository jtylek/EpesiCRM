<?php

namespace Epesi\Modules\StoreServer\Services;

use App\Services\Modules\ModuleArchive;
use App\Services\Modules\ModuleException;
use Epesi\Modules\StoreServer\Models\Product;
use Epesi\Modules\StoreServer\Models\Release;
use Illuminate\Support\Facades\Storage;

/**
 * Publishing a release runs the customer's own install-time validation
 * (App\Services\Modules\ModuleArchive) against the uploaded zip, so a package
 * that would be refused on install is refused at publish time instead — the
 * store never serves an archive it knows is broken.
 */
class ReleasePublisher
{
    public const DISK = 'local';

    public const DIRECTORY = 'store-releases';

    public function publish(Product $product, string $zipPath, ?string $changelog = null): Release
    {
        $archive = new ModuleArchive($zipPath);
        $manifest = $archive->manifest();
        $archive->close();

        if ($manifest->id !== $product->module_id) {
            throw new ModuleException("This package is {$manifest->id}, but the product is {$product->module_id}.");
        }

        if ($product->releases()->where('version', $manifest->version)->exists()) {
            throw new ModuleException("Version {$manifest->version} of {$product->module_id} has already been published.");
        }

        $path = self::DIRECTORY.'/'.str_replace('/', '-', $manifest->id).'-'.$manifest->version.'.zip';

        Storage::disk(self::DISK)->put($path, file_get_contents($zipPath));

        return $product->releases()->create([
            'version' => $manifest->version,
            'epesi_core' => $manifest->epesiCore,
            'sha256' => hash_file('sha256', $zipPath),
            'size' => filesize($zipPath),
            'changelog' => $changelog,
            'file_path' => $path,
            'is_published' => true,
        ]);
    }
}
