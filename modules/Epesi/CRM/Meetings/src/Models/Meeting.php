<?php

namespace Epesi\Modules\CRM\Meetings\Models;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Models\User;
use Carbon\Carbon;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasCustomFields;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasOwnershipVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Port of Epesi's CRM_Meeting "crm_meeting" recordset — see the
 * create_meetings_table migration's docblock for what's simplified/not
 * ported, recurrence included.
 */
class Meeting extends Model
{
    use HasCustomFields, HasFactory, HasOwnershipVisibility, LogsActivity, SoftDeletes;

    /**
     * The columns' database defaults, so a record created without them has
     * the same values in memory as in the table. Otherwise its first update
     * logs them as changed (null → default) in the activity log.
     */
    protected $attributes = [
        'status' => 0,
        'priority' => 1,
        'permission' => 0,
    ];

    protected $fillable = [
        'title',
        'date',
        'time',
        'duration_minutes',
        'description',
        'status',
        'priority',
        'permission',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'permission' => RecordPermission::class,
            'status' => RecordStatus::class,
            'priority' => RecordPriority::class,
        ];
    }

    /**
     * Date + time combined, for display/sorting convenience — not persisted.
     */
    protected function startsAt(): Attribute
    {
        return Attribute::make(
            get: fn (): ?Carbon => $this->date && $this->time
                ? Carbon::parse($this->date->toDateString().' '.$this->time)
                : null,
        );
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'meeting_employee');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'meeting_customer');
    }

    public function customerCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'meeting_customer_company');
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
        $query->orWhereHas('employees', fn (Builder $q) => $q->where('user_id', $user->id))
            ->orWhereHas('customers', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('meeting');
    }
}
