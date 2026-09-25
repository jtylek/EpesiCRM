<?php

namespace Epesi\Modules\StoreServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Release extends Model
{
    protected $table = 'epesi_store_server_releases';

    protected $fillable = [
        'product_id',
        'version',
        'epesi_core',
        'sha256',
        'size',
        'changelog',
        'file_path',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function downloadFilename(): string
    {
        return str_replace('/', '-', $this->product->module_id).'-'.$this->version.'.zip';
    }
}
