<?php

namespace Epesi\Modules\StoreServer\Http\Controllers;

use Epesi\Modules\StoreServer\Models\Licence;
use Epesi\Modules\StoreServer\Models\Product;
use Epesi\Modules\StoreServer\Models\Release;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Public, unauthenticated, read-only. A licence key may be sent as a bearer
 * token; it only ever *adds* download URLs for paid products the licence covers,
 * so an anonymous caller sees the same catalog minus those links.
 */
class CatalogController
{
    public const DOWNLOAD_URL_TTL_MINUTES = 15;

    public function __invoke(Request $request): JsonResponse
    {
        $licence = $this->licence($request);

        $modules = Product::query()
            ->where('is_published', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): ?array => $this->entry($product, $licence))
            ->filter()
            ->values();

        return response()->json([
            'store' => config('app.name'),
            'modules' => $modules,
        ]);
    }

    /**
     * @return array<string, mixed>|null null when the product has no published release to offer
     */
    protected function entry(Product $product, ?Licence $licence): ?array
    {
        $release = $product->latestRelease();

        if (! $release) {
            return null;
        }

        $licensed = $product->isFree() || $this->covers($licence, $product);

        return [
            'id' => $product->module_id,
            'name' => $product->name,
            'description' => $product->description,
            'category' => $product->category,
            'icon' => $product->icon,
            'price' => $product->isFree()
                ? ['type' => 'free']
                : ['type' => 'paid', 'amount' => $product->price_amount, 'currency' => $product->price_currency],
            'version' => $release->version,
            'epesi_core' => $release->epesi_core,
            'sha256' => $release->sha256,
            'size' => $release->size,
            'changelog' => $release->changelog,
            'licensed' => $licensed,
            'download_url' => $licensed ? $this->downloadUrl($release, $licence) : null,
        ];
    }

    protected function downloadUrl(Release $release, ?Licence $licence): string
    {
        $parameters = ['release' => $release->id];

        if ($licence) {
            $parameters['licence'] = $licence->key;
        }

        return URL::temporarySignedRoute(
            'epesi-store.download',
            now()->addMinutes(self::DOWNLOAD_URL_TTL_MINUTES),
            $parameters,
        );
    }

    protected function covers(?Licence $licence, Product $product): bool
    {
        return $licence !== null
            && $licence->product_id === $product->id
            && $licence->isValid();
    }

    protected function licence(Request $request): ?Licence
    {
        $key = $request->bearerToken();

        if (! $key) {
            return null;
        }

        return Licence::query()->where('key', $key)->first();
    }
}
