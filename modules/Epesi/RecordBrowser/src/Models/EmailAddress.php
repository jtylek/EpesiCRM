<?php

namespace Epesi\Modules\RecordBrowser\Models;

use App\Models\User;
use Closure;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * An e-mail address, as a collection item: a contact's or a company's own
 * address and any extra one, each with its kind (`Email_Kinds`: Work,
 * Private, Other) — as many as a record needs. Any recordset takes them with
 * `Field::collection('emails', EmailAddress::class)`.
 *
 * An address belongs to one live record: it is unique across every record
 * using this type (contacts, companies, and any module's recordset that takes
 * it), so mail from it files to exactly one of them. The form's rule
 * (uniqueRule()) checks this before save and names the record that already
 * has it; a unique index on `live_value` enforces it regardless.
 *
 * `live_value` is `value` while the owner holds the address, NULL once a soft
 * delete has released it: a deleted record keeps its addresses for Restore
 * without keeping them from anyone else. Its login address stays held, as the
 * account can still sign in with it. Restore takes an address back unless
 * another record has it by then; then the live record keeps it and the
 * restored one's History shows it gone (ownerRestored()).
 *
 * `verified_at` is set only by the customer portal's verification link or a
 * password reset (App\Support\Auth\PortalEmails), never by a form: it isn't a
 * field.
 *
 * @property ?string $value
 * @property ?string $live_value
 * @property ?Carbon $verified_at
 */
class EmailAddress extends CollectionItem
{
    protected $table = 'epesi_recordbrowser_email_addresses';

    protected static function booted(): void
    {
        static::saving(function (self $address): void {
            $address->value = mb_strtolower(trim((string) $address->value));
            $address->live_value = $address->ownerHoldsIt() ? $address->value : null;

            // A changed address is a different one: not verified until its owner says so.
            if ($address->exists && $address->isDirty('value') && ! $address->isDirty('verified_at')) {
                $address->verified_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public static function fields(): array
    {
        return [
            // The list's column: a record's first address.
            Field::email('value')->label('E-mail')->required()->inTable()
                ->formUsing(fn (TextInput $input): TextInput => $input
                    // The login address: shown, not changeable (isLocked()).
                    ->disabled(fn (Get $get): bool => filled($get('id')) && (static::query()->find($get('id'))?->isLocked() ?? false))
                    ->prefixIcon(fn (Get $get): ?Heroicon => filled($get('id')) && (static::query()->find($get('id'))?->isLocked() ?? false)
                        ? Heroicon::OutlinedKey
                        : null)
                    ->extraInputAttributes(fn (Get $get): array => filled($get('id')) && (static::query()->find($get('id'))?->isLocked() ?? false)
                        ? ['title' => __('The address this person signs in with. It can not be changed or removed here.')]
                        : [])
                    ->rule(fn (Get $get): Closure => static::uniqueRule($get))),
        ];
    }

    public static function inlineRow(): bool
    {
        return true;
    }

    public static function kinds(): string
    {
        return 'Email_Kinds';
    }

    public static function defaultKind(): ?string
    {
        return 'work';
    }

    public static function addActionLabel(): string
    {
        return __('Add e-mail address');
    }

    /**
     * The address a login signs in with (users.email of the owner's linked
     * user): it can't be removed or changed here — a customer's sign-in is
     * moved only by the portal's verification (App\Support\Auth\PortalEmails),
     * an administrator's by Change Username.
     */
    public function isLocked(): bool
    {
        $owner = $this->owner;

        return $owner !== null && static::loginOf($owner) === mb_strtolower((string) $this->value);
    }

    /** A soft delete releases the owner's addresses, all but its login. */
    public static function ownerTrashed(Model $owner): void
    {
        static::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->when(static::loginOf($owner), fn ($query, string $login) => $query->where('value', '!=', $login))
            ->toBase()
            ->update(['live_value' => null]);
    }

    /** Takes back each address no one else has taken meanwhile, and removes the others. */
    public static function ownerRestored(Model $owner): array
    {
        $dropped = [];

        $released = static::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->whereNull('live_value')
            ->get();

        foreach ($released as $item) {
            if (static::query()->where('live_value', $item->value)->exists()) {
                $item->delete();
                $dropped[] = $item;

                continue;
            }

            static::query()->whereKey($item->getKey())->toBase()->update(['live_value' => $item->value]);
        }

        return $dropped;
    }

    /** Asked of the owner's row itself: `owner` hides a soft-deleted one. */
    protected function ownerHoldsIt(): bool
    {
        $class = Relation::getMorphedModel((string) $this->owner_type);
        $owner = $class !== null ? $class::query()->withoutGlobalScopes()->find($this->owner_id) : null;

        return $owner === null
            || ! method_exists($owner, 'trashed')
            || ! $owner->trashed()
            || static::loginOf($owner) === $this->value;
    }

    /** The address $owner's linked user signs in with, lower-cased; null without one. */
    protected static function loginOf(Model $owner): ?string
    {
        $userId = $owner->getAttribute('user_id');
        $login = $userId !== null ? User::query()->whereKey($userId)->value('email') : null;

        return $login !== null ? mb_strtolower((string) $login) : null;
    }

    public function flags(): array
    {
        return $this->isLocked() ? [__('Login')] : [];
    }

    public function statusBadges(): array
    {
        // Verification only matters for a contact that has a user account.
        if ($this->owner?->getAttribute('user_id') === null) {
            return [];
        }

        return $this->verified_at !== null
            ? [['label' => __('Verified'), 'color' => 'success']]
            : [['label' => __('Not verified'), 'color' => 'danger']];
    }

    /** The address as typed. */
    public function summary(): string
    {
        return (string) $this->value;
    }

    /** Opens Mail's compose page for the owner, as the old `email` column did. */
    public function url(): ?string
    {
        if (blank($this->value) || $this->owner === null) {
            return null;
        }

        return RecordExtensions::emailUrlFor($this->owner, $this->value);
    }

    /**
     * Not whether the address is real, but whether another live record
     * already has it: an address belongs to one record, so mail from it files
     * to one. Excludes this item itself (by id, from the repeater's own hidden
     * field), so re-saving an unchanged address never conflicts with itself.
     * A deleted record's form may keep an address it has released, even when
     * someone has taken it since.
     */
    protected static function uniqueRule(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $value = mb_strtolower(trim((string) $value));

            if ($value === '') {
                return;
            }

            if (filled($get('id')) && static::query()->whereKey($get('id'))->whereNull('live_value')->where('value', $value)->exists()) {
                return;
            }

            $conflict = static::query()
                ->when($get('id'), fn ($query, $id) => $query->whereKeyNot($id))
                ->whereRaw('LOWER(live_value) = ?', [$value])
                ->with('owner')
                ->first();

            if ($conflict === null) {
                return;
            }

            $owner = $conflict->owner;

            $fail($owner instanceof Model
                ? __('This address is already used by :record.', ['record' => LinkedRecords::title($owner)])
                : __('This address is already used by another record.'));
        };
    }
}
