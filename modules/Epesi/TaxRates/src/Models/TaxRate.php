<?php

namespace Epesi\Modules\TaxRates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A tax rate and its code (VAT 23%, Exempt, Reverse charge…). A document line
 * copies the rate when it is saved, so changing a rate doesn't rewrite lines
 * already saved.
 *
 * No HasCustomFields: a lookup, not a recordset administrators add fields to.
 */
class TaxRate extends Model
{
    use LogsActivity, SoftDeletes;

    /** Tax returns report the 0% kinds apart, so they are kept apart here too. */
    public const TYPES = [
        'standard' => 'Standard',
        'reduced' => 'Reduced',
        'zero_rated' => 'Zero rated',
        'exempt' => 'Exempt',
        'reverse_charge' => 'Reverse charge',
    ];

    protected $table = 'tax_rates';

    protected $fillable = ['name', 'code', 'description', 'rate', 'tax_type', 'country_code', 'active'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('tax_rate');
    }
}
