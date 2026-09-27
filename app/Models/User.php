<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Auth\UserActivity;
use App\Support\Demo;
use App\Support\Locale\Locales;
use Database\Factories\UserFactory;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * The roles that open epesi's main panel. Other roles have their own,
     * narrower panel instead — 'customer' opens the customer portal
     * (Administration → Users still creates their login; see
     * AI-shared/Customer-portal.md) — or none yet, until one is built.
     */
    public const MAIN_PANEL_ROLES = ['super_admin', 'manager', 'employee'];

    /**
     * The History of a user: who changed the name, e-mail address or active
     * flag, and when. The password is left out on purpose (the log would keep
     * its hash) and recorded as an event of its own instead, below; roles and
     * the contact link aren't columns, see UserActivity. remember_token
     * changes at every login, and isn't logged either.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }

    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if ($user->wasChanged('password')) {
                UserActivity::passwordChanged($user);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
    ];

    /**
     * Mirrors the column default, which a freshly created model doesn't see
     * until it is re-read — canAccessPanel() would otherwise treat a user just
     * created in this request (a test's actingAs(), say) as deactivated.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->active) {
            return false;
        }

        // Demo mode has no Administration panel at all (DisabledInDemo); this
        // is the second lock on it.
        if ($panel->getId() === 'administration') {
            return ! Demo::enabled() && $this->hasRole('super_admin');
        }

        // The customer portal: its own panel, its own role, nothing else.
        if ($panel->getId() === 'portal') {
            return ! Demo::enabled() && $this->hasRole('customer');
        }

        return $this->hasAnyRole(self::MAIN_PANEL_ROLES);
    }

    /**
     * The CRM contact record representing this login, mirroring Epesi's
     * contact.login FK to user_login.id (not every user has one).
     *
     * @return HasOne<Contact, $this>
     */
    public function contact(): HasOne
    {
        return $this->hasOne(Contact::class);
    }

    /**
     * The company of the contact this user is linked to, i.e. Epesi's
     * USER_COMPANY ACL crit — "always allow viewing/editing your own company".
     */
    public function companyId(): ?int
    {
        return $this->contact?->company_id;
    }

    /**
     * The linked CRM contact's name where one exists (the identity most
     * users recognize), falling back to the login's own name for accounts
     * with no linked contact — e.g. a super_admin with no CRM record.
     */
    public function displayName(): string
    {
        return $this->contact?->full_name ?: $this->name;
    }

    /**
     * Mail and notifications go out in the recipient's language, not the
     * language of whoever's request triggered them.
     */
    public function preferredLocale(): string
    {
        return Locales::forUser($this);
    }
}
