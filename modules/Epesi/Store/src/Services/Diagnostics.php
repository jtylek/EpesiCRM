<?php

namespace Epesi\Modules\Store\Services;

use App\Models\Module;
use App\Models\User;
use App\Services\Cron\CronLog;
use App\Support\Version;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Store\Models\StoreSetting;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What an installation that opted in tells the store once a day — exactly the
 * list the registration form shows: versions, modules, basic server info and
 * record counts (numbers only, never records). Sealed with the store's public
 * key (OpenSSL: RSA-OAEP over a random AES-256-GCM key), so only the store can
 * read it.
 */
class Diagnostics
{
    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $connection = DB::connection();

        return [
            'epesi' => Version::current(),
            'php' => PHP_VERSION,
            'database' => $connection->getDriverName().' '.$this->databaseVersion(),
            'os' => PHP_OS_FAMILY.' '.php_uname('r'),
            'web_server' => StoreSetting::current()->web_server,
            'locale' => config('app.locale'),
            'timezone' => config('app.timezone'),
            'installed' => Module::query()->min('installed_at'),
            'cron_last_run' => CronLog::lastCall()?->started_at,
            'users' => User::query()->where('active', true)->count(),
            'contacts' => $this->count(Contact::class),
            'companies' => $this->count(Company::class),
            'modules' => Module::query()->orderBy('module_id')->get(['module_id', 'version', 'enabled', 'path'])
                ->map(fn (Module $module): array => [
                    'id' => $module->module_id,
                    'version' => $module->version,
                    'vendor' => strtok((string) $module->path, '/\\') ?: null,
                    'enabled' => (bool) $module->enabled,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function seal(array $data, string $publicKey = StoreClient::SEAL_PUBLIC_KEY): string
    {
        $key = random_bytes(32);
        $iv = random_bytes(12);
        $ciphertext = openssl_encrypt((string) json_encode($data), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false || ! openssl_public_encrypt($key, $wrapped, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new \RuntimeException('Could not seal the diagnostics.');
        }

        return base64_encode((string) json_encode([
            'k' => base64_encode($wrapped),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'd' => base64_encode($ciphertext),
        ]));
    }

    /**
     * The web server can only be read during a web request (cron runs from the
     * command line), so the Store and About pages note it for the next check-in.
     */
    public static function rememberWebServer(): void
    {
        $software = request()->server('SERVER_SOFTWARE');

        if (app()->runningInConsole() || blank($software)) {
            return;
        }

        $setting = StoreSetting::current();

        if ($setting->web_server !== $software) {
            $setting->update(['web_server' => mb_substr((string) $software, 0, 255)]);
        }
    }

    protected function databaseVersion(): string
    {
        try {
            return (string) DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param  class-string  $model
     */
    protected function count(string $model): ?int
    {
        if (! class_exists($model)) {
            return null;
        }

        // Past the per-user visibility scopes, but not counting the trash.
        return rescue(function () use ($model): int {
            $query = $model::query()->withoutGlobalScopes();

            return in_array(SoftDeletes::class, class_uses_recursive($model), true)
                ? $query->whereNull((new $model)->getDeletedAtColumn())->count()
                : $query->count();
        }, null, report: false);
    }
}
