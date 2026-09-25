<?php

namespace Epesi\Modules\CRM\Contacts\Models;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasCustomFields;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasOwnershipVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Port of Epesi's CRM_Contacts "contact" recordset (CRM_ContactsInstall::install()).
 *
 * Epesi's Login/Username/Set Password/Confirm Password/Admin/Access block was a
 * bespoke QFfield_callback-driven UI writing straight into user_login — no
 * Filament field-type equivalent, and not ported as fields. Instead: `user_id`
 * is a real belongsTo(User), password changes are a Filament Action (see
 * ContactResource), and Epesi's per-contact "Access" (Contacts/Access CommonData:
 * manager/employee) becomes real spatie/laravel-permission roles on that User.
 */
class Contact extends Model
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
        'last_name',
        'first_name',
        'company_id',
        'memo',
        'groups',
        'title',
        'work_phone',
        'mobile_phone',
        'fax',
        'email',
        'web_address',
        'address_1',
        'address_2',
        'city',
        'country',
        'zone',
        'postal_code',
        'permission',
        'home_phone',
        'home_address_1',
        'home_address_2',
        'home_city',
        'home_country',
        'home_zone',
        'home_postal_code',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            // Keys into the Contacts_Groups shared list, not an enum — the
            // administrator maintains that list, so nothing here can enumerate it.
            'groups' => 'array',
            'permission' => RecordPermission::class,
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Companies related to this contact beyond its primary employer —
     * Epesi's "Related Companies" multiselect.
     */
    public function relatedCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    /**
     * Contacts belonging to (or related to) the given company — Epesi's
     * `employees_crits()` ("(company_name => X, |related_companies => [X])"),
     * used by the Employees pickers on Meetings/Tasks/PhoneCalls to scope to
     * the acting user's own company's staff. Deliberately unrelated to
     * `user_id`: an employee doesn't need a portal login.
     */
    public function scopeOfCompany(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($companyId) {
            $query->where('company_id', $companyId)
                ->orWhereHas('relatedCompanies', fn (Builder $q) => $q->whereKey($companyId));
        });
    }

    /**
     * Inverse of Task::customers() — tasks logged against this contact as
     * the customer. Backs the "Tasks" addon on Contact's View page.
     */
    public function tasksAsCustomer(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_customer');
    }

    /**
     * Inverse of PhoneCall::contact() — calls logged against this contact.
     * Backs the "Phone Calls" addon on Contact's View page.
     */
    public function phoneCallsAsCustomer(): HasMany
    {
        return $this->hasMany(PhoneCall::class);
    }

    /**
     * Inverse of Meeting::customers() — meetings this contact is a customer
     * of. Backs the "Meetings" addon on Contact's View page.
     */
    public function meetingsAsCustomer(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_customer');
    }

    /**
     * The login this contact is linked to, i.e. Epesi's contact.login FK
     * into user_login.id. Nullable — most contacts have no login.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        $query->orWhere('user_id', $user->id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('contact');
    }
}
