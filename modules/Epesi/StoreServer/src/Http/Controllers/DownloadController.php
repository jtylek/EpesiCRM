<?php

namespace Epesi\Modules\StoreServer\Http\Controllers;

use Epesi\Modules\StoreServer\Models\Licence;
use Epesi\Modules\StoreServer\Models\Release;
use Epesi\Modules\StoreServer\Services\ReleasePublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reached only through a signed URL the catalog issued. The licence is checked
 * again here rather than trusted from the signature: a signed URL stays valid
 * for its whole TTL, so a licence revoked in the meantime must still stop the
 * download.
 */
class DownloadController
{
    public function __invoke(Request $request, Release $release): StreamedResponse
    {
        abort_unless($release->is_published && $release->product->is_published, 404);

        if (! $release->product->isFree()) {
            $licence = Licence::query()
                ->where('key', (string) $request->query('licence'))
                ->where('product_id', $release->product_id)
                ->first();

            abort_if($licence === null || ! $licence->isValid(), 403, 'A valid licence is required for this module.');
        }

        abort_unless(Storage::disk(ReleasePublisher::DISK)->exists($release->file_path), 404);

        return Storage::disk(ReleasePublisher::DISK)->download($release->file_path, $release->downloadFilename());
    }
}
