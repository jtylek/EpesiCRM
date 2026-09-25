<?php

namespace Epesi\Modules\Store\Services;

use App\Services\Modules\ModuleException;
use Epesi\Modules\Store\Models\StoreSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Talks to a store server's public API. Everything it returns is treated as
 * untrusted input: the catalog only decides *what to offer*, and the downloaded
 * package still goes through the core's own ModuleArchive validation plus a
 * checksum comparison before anything is installed.
 */
class StoreClient
{
    public const TIMEOUT_SECONDS = 20;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        $settings = StoreSetting::current();

        if (! $settings->isConfigured()) {
            throw new ModuleException('No store configured yet — set the catalog URL in Store settings.');
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->when(filled($settings->licence_key), fn ($request) => $request->withToken($settings->licence_key))
                ->acceptJson()
                ->get(rtrim($settings->catalog_url, '/').'/catalog');
        } catch (Throwable $exception) {
            throw new ModuleException('Could not reach the store: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            throw new ModuleException("The store returned HTTP {$response->status()}.");
        }

        $modules = $response->json('modules');

        if (! is_array($modules)) {
            throw new ModuleException('The store returned an unexpected response.');
        }

        return array_values(array_filter($modules, 'is_array'));
    }

    /**
     * Downloads a package to a temporary file and verifies the catalog's
     * checksum before handing the path back.
     */
    public function download(string $url, string $expectedSha256): string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (Throwable $exception) {
            throw new ModuleException('Download failed: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            throw new ModuleException("Download failed with HTTP {$response->status()}.");
        }

        $path = storage_path('app/private/store-downloads');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $file = $path.DIRECTORY_SEPARATOR.Str::random(12).'.zip';
        file_put_contents($file, $response->body());

        $actual = hash_file('sha256', $file);

        if (! hash_equals($expectedSha256, $actual)) {
            unlink($file);

            throw new ModuleException('The downloaded package does not match the checksum the catalog advertised.');
        }

        return $file;
    }
}
