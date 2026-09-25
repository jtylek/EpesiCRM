<?php

namespace Epesi\Modules\CRM\Companies\Models;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasCustomFields;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasOwnershipVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Port of Epesi's CRM_Contacts "company" recordset (CRM_ContactsInstall::install()).
 * The Login/Username/Password/Admin block that recordset also carries lives on
 * Contact + User instead — see Contact's docblock.
 */
class Company extends Model
{
    use HasCustomFields, HasFactory, HasOwnershipVisibility, LogsActivity, SoftDeletes;

    /**
     * The column's database default, so a record created without it has
     * the same value in memory as in the table. Otherwise its first update
     * logs it as changed (null → default) in the activity log.
     */
    protected $attributes = [
        'permission' => 0,
    ];

    protected $fillable = [
        'company_name',
        'short_name',
        'phone',
        'fax',
        'email',
        'web_address',
        'memo',
        'groups',
        'permission',
        'address_1',
        'address_2',
        'city',
        'country',
        'zone',
        'postal_code',
        'tax_id',
    ];

    protected function casts(): array
    {
        return [
            // Keys into the Companies_Groups shared list, not an enum — the
            // administrator maintains that list, so nothing here can enumerate it.
            'groups' => 'array',
            'permission' => RecordPermission::class,
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Contacts related to this company beyond their primary employer —
     * Epesi's "Related Companies" multiselect on the contact side.
     */
    public function relatedContacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }

    /**
     * Inverse of Task::customerCompanies() — tasks logged against this
     * company as the customer. Backs the "Tasks" addon on Company's View
     * page.
     */
    public function tasksAsCustomer(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_customer_company');
    }

    /**
     * Inverse of PhoneCall::company() — calls logged against this company.
     * Backs the "Phone Calls" addon on Company's View page.
     */
    public function phoneCallsAsCustomer(): HasMany
    {
        return $this->hasMany(PhoneCall::class);
    }

    /**
     * Inverse of Meeting::customerCompanies() — meetings this company is a
     * customer of. Backs the "Meetings" addon on Company's View page.
     */
    public function meetingsAsCustomer(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_customer_company');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who made the most recent change, per the activity log LogsActivity
     * already writes — there's no updated_by column, so this reads it off
     * the same trail the History tab shows.
     */
    public function lastUpdater(): ?User
    {
        return $this->activities()->latest('created_at')->first()?->causer;
    }

    protected static function applyExtraVisibility(Builder $query, User $user): void
    {
        if ($companyId = $user->companyId()) {
            $query->orWhere('id', $companyId);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('company');
    }
}
