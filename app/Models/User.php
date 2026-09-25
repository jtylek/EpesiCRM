<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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

        if ($panel->getId() === 'administration') {
            return $this->hasRole('super_admin');
        }

        return $this->hasAnyRole(['super_admin', 'manager', 'employee']);
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
