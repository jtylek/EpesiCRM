<?php

namespace Epesi\Modules\CRM\Tasks\Models;

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
 * Port of Epesi's CRM_Tasks "task" recordset — see the create_tasks_table
 * migration's docblock for what's simplified/not ported.
 */
class Task extends Model
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
        'timeless' => false,
    ];

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'permission',
        'deadline',
        'timeless',
    ];

    protected function casts(): array
    {
        return [
            'permission' => RecordPermission::class,
            'status' => RecordStatus::class,
            'priority' => RecordPriority::class,
            'deadline' => 'datetime',
            'timeless' => 'boolean',
        ];
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'task_employee');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'task_customer');
    }

    public function customerCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'task_customer_company');
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
            ->useLogName('task');
    }
}
