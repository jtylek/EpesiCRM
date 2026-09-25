<?php

namespace Epesi\Modules\Notes\Models;

use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasCustomFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Module tables carry the module's own prefix (`epesi_notes`, not `notes`) so
 * two modules can never claim the same table name.
 *
 * HasCustomFields is what lets an administrator add a field to Notes from
 * Administration → Fields: it merges the `cf_*` columns into `$fillable` and
 * `$casts` at construction, which also means logFillable() below covers them,
 * so a GUI-added field turns up in this record's History with no extra code.
 */
class Note extends Model
{
    use HasCustomFields, LogsActivity;

    protected $table = 'epesi_notes';

    protected $fillable = [
        'title',
        'content',
        'contact_id',
        'pinned',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('note');
    }
}
