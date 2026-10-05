<?php

namespace Epesi\Modules\Store\Services;

use App\Services\Modules\ModuleException;
use App\Support\Version;
use Epesi\Modules\Store\Models\StoreSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Talks to the epesi store's API. Everything it returns is treated as
 * untrusted input: the catalog only decides *what to offer*, and the downloaded
 * package still goes through the core's own ModuleArchive validation plus a
 * checksum comparison before anything is installed.
 *
 * A registered installation identifies itself on every call with its instance
 * UUID and secret, its URL and version, and its licence key (headers()); the
 * store binds the licence to that URL.
 */
class StoreClient
{
    public const API_URL = 'https://store.epe.si/manage/store-api';

    /**
     * The store's public key: diagnostics are sealed with it, so only the store
     * can read them. Its private half is STORE_SERVER_SEAL_SECRET on the store.
     */
    public const SEAL_PUBLIC_KEY = <<<'PEM'
        -----BEGIN PUBLIC KEY-----
        MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEA4GHGh8rIavYz+1l0jA7d
        tunQEs8bn5SUhf7DFeBfhBIZAdqj1aKzw7rEX5I/LeeD1WTFNDjpFLm42f6EG5IN
        ys68fzscAUaSvqMfS7oZ1fu0eALbromtqif9GRnU3npKvsO4EBUmALZ41+xVnqK+
        SrlUu4a8cA++JArVecZgCp2l73zDvmtY+NSUZIVeraGU2RPlYn5uLIqDt57Wu0PK
        p2RDJzyjR+L1wcFnpEbrjjlmv7pzLS+Wa8CEJnv8J+DlSIWmjdH+AUITEyUlAzB+
        dcivdYgOOKQF4x6wLITYf9WxPV1hwoWgdQ5W2eQ6BQDb3+kf/gV0dAwdSQfAp6gE
        3hvrppPNf4yoA1C2qv4mM35y+N9GH5VG58lz/BeUR48wiM8bHWaHJ0g2wrOzhKwG
        f9HCU9a3iPCsbqrvYdSiIVK0Vx1Q56oDgM4GE4gaXLnJK75vGdEcUXhFYUAqfnT+
        /gnFx4zvqGAROXNiPkP0udMZczqJvkfQ8DLv7Xusmu21AgMBAAE=
        -----END PUBLIC KEY-----
        PEM;

    public const TIMEOUT_SECONDS = 20;

    /** A core release is tens of megabytes. */
    public const DOWNLOAD_TIMEOUT_SECONDS = 300;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return $this->payload()['modules'];
    }

    /**
     * The core release the store offers, or null when it offers none.
     *
     * @return array{version: string, sha256: string, size: int, changelog: ?string, download_url: string}|null
     */
    public function core(): ?array
    {
        return $this->payload()['core'];
    }

    /**
     * @return array{modules: array<int, array<string, mixed>>, core: array<string, mixed>|null}
     */
    public function payload(): array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->get(self::API_URL.'/catalog'));

        $modules = $response->json('modules');

        if (! is_array($modules)) {
            throw new ModuleException('The store returned an unexpected response.');
        }

        $core = $response->json('core');

        return [
            'modules' => array_values(array_filter($modules, 'is_array')),
            'core' => is_array($core) && filled($core['version'] ?? null) && filled($core['download_url'] ?? null) && filled($core['sha256'] ?? null) ? $core : null,
        ];
    }

    /**
     * Introduces this installation to the store (or again, after a new click on
     * Register) and returns the store's answer, with the form link in
     * `register_url`.
     *
     * @param  array<string, string>  $prefill
     * @return array<string, mixed>
     */
    public function handshake(StoreSetting $setting, array $prefill): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post(self::API_URL.'/instances', [
            'uuid' => $setting->instance_uuid,
            'secret' => $setting->instance_secret,
            'url' => self::installationUrl(),
            'version' => Version::current(),
            'prefill' => $prefill,
        ]))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get(self::API_URL.'/instance'))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function checkIn(?string $sealedDiagnostics): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post(self::API_URL.'/instance/check-in', array_filter([
            'diagnostics' => $sealedDiagnostics,
        ])))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function requestTransfer(): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post(self::API_URL.'/instance/transfer'))->json();
    }

    public function deleteRegistration(): void
    {
        $this->send(fn (PendingRequest $request) => $request->delete(self::API_URL.'/instance'));
    }

    /**
     * Downloads a package to a temporary file and verifies the catalog's
     * checksum before handing the path back.
     */
    public function download(string $url, string $expectedSha256): string
    {
        try {
            $response = Http::timeout(self::DOWNLOAD_TIMEOUT_SECONDS)->get($url);
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

    /** The address this installation is reached at, which the licence is bound to. */
    public static function installationUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * The headers a known installation identifies itself with.
     *
     * @return array<string, string>
     */
    public static function headers(StoreSetting $setting): array
    {
        if (! $setting->hasIdentity()) {
            return [];
        }

        return [
            'X-Epesi-Instance' => (string) $setting->instance_uuid,
            'X-Epesi-Secret' => (string) $setting->instance_secret,
            'X-Epesi-Url' => self::installationUrl(),
            'X-Epesi-Version' => Version::current(),
        ];
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    protected function send(callable $call): Response
    {
        $setting = StoreSetting::current();

        try {
            $response = $call(
                Http::timeout(self::TIMEOUT_SECONDS)
                    ->acceptJson()
                    ->withHeaders(self::headers($setting))
                    ->when(filled($setting->licence_key), fn (PendingRequest $request) => $request->withToken($setting->licence_key)),
            );
        } catch (Throwable $exception) {
            throw new ModuleException('Could not reach the store: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            $error = $response->json('error');

            throw is_string($error)
                ? new StoreApiException($error, (string) ($response->json('message') ?? $error), $response->json() ?? [])
                : new ModuleException("The store returned HTTP {$response->status()}.");
        }

        return $response;
    }
}
