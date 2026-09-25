<?php

namespace App\Support;

/**
 * Blocks autofill on plain business-data text fields (name/address/phone/etc.).
 * Kept recurring on the same seeded records through four earlier attempts
 * before the actual culprit was confirmed by the user: Kaspersky Password
 * Manager's browser extension. Each earlier attempt targeted a real but
 * wrong mechanism:
 *
 * 1. `data-1p-ignore`/`data-lpignore`/`data-bwignore` — documented opt-out
 *    attributes for 1Password/LastPass/Bitwarden specifically. Kaspersky
 *    doesn't publish an equivalent, so none of these mean anything to it.
 * 2. `autocomplete="new-password"` — defeats Chrome/Edge's own *built-in*
 *    address autofill (a separate thing from any extension, and one that
 *    deliberately ignores plain `autocomplete="off"`). Real fix for that
 *    mechanism, irrelevant to a password-manager extension.
 * 3. `readonly` from first paint, lifted permanently on the user's own first
 *    focus/click/touch — correct for the "fills instantly on render" part,
 *    but left the field permanently unlocked for the rest of the page's
 *    life once touched once. Filament's forms sync *every* field's current
 *    DOM value on any Livewire round-trip, not just the field that
 *    triggered it — so once First Name had been focused once, a later
 *    autofill landing while the user was working an unrelated field (e.g.
 *    the "Related Companies" searchable Select in the same section, whose
 *    own search request is exactly such a round-trip) still got swept up
 *    and persisted on the next save, overwriting a value the user had
 *    already typed and moved on from. Confirmed via the edit history on a
 *    real record: first_name flipping between the typed value and the
 *    logged-in user's own name across saves, in lockstep with edits to
 *    other fields on the same form.
 *
 * Fix for #3: re-lock on blur, not just unlock on focus. The field is only
 * ever writable while the user is actively focused in it; the moment focus
 * leaves, it goes back to `readonly` and is immune again for however long
 * the user is working elsewhere on the form.
 *
 * @return array<string, string>
 */
class NoIdentityAutofill
{
    public static function attributes(): array
    {
        $unlock = "this.removeAttribute('readonly')";
        $relock = "this.setAttribute('readonly','readonly')";

        return [
            'readonly' => 'readonly',
            'onfocus' => $unlock,
            'onmousedown' => $unlock,
            'ontouchstart' => $unlock,
            'onblur' => $relock,
            'autocomplete' => 'new-password',
            'data-1p-ignore' => 'true',
            'data-lpignore' => 'true',
            'data-bwignore' => 'true',
            'data-form-type' => 'other',
        ];
    }
}
