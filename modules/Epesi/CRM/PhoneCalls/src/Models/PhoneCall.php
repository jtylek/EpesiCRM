<?php

namespace Epesi\Modules\CRM\PhoneCalls\Models;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasCustomFields;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasOwnershipVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Port of Epesi's CRM_PhoneCall "phonecall" recordset — see the
 * create_phone_calls_table migration's docblock for what's simplified/not
 * ported (Company-or-Contact Customer type, chained phone-number select,
 * Related links, Watchdog, Calendar handler).
 */
class PhoneCall extends Model
{
    use HasCustomFields, HasFactory, HasOwnershipVisibility, LogsActivity, SoftDeletes;

    /**
     * The columns' database defaults, so a record created without them has
     * the same values in memory as in the table. Otherwise its first update
     * logs them as changed (null → default) in the activity log.
     */
    protected $attributes = [
        'other_customer' => false,
        'permission' => 0,
        'status' => 0,
        'priority' => 1,
    ];

    protected $fillable = [
        'subject',
        'contact_id',
        'company_id',
        'other_customer',
        'other_customer_name',
        'phone_number',
        'permission',
        'description',
        'status',
        'priority',
        'called_at',
    ];

    protected function casts(): array
    {
        return [
            'other_customer' => 'boolean',
            'permission' => RecordPermission::class,
            'status' => RecordStatus::class,
            'priority' => RecordPriority::class,
            'called_at' => 'datetime',
        ];
    }

    /**
     * The customer contact this call was with — nullable, since "Other
     * Customer" lets a call be logged against someone not in the system.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The customer company this call was with — the Company side of the
     * Company-or-Contact Customer, alongside contact() rather than instead
     * of it (see the phone_calls migration's docblock for why this is two
     * plain FKs, not one polymorphic field).
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'phone_call_employee');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastUpdater(): ?User
    {
        return $this->activities()->latest('created_at')->first()?->causer;
    }

    protected static function applyExtraVisibility(Builder $query, User $user): void
    {
        $query->orWhereHas('employees', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('phone_call');
    }
}
