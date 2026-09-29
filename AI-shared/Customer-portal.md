# Customer portal

The beginning of a portal for people who aren't your staff — a customer, so far — signed in
through their own contact record rather than the main CRM. Prompted by testing the "any contact
can be made a user" change (see [User-management.md](User-management.md)) on a real `customer`
login: it had nowhere to sign in, and "Reset Password" correctly refused to e-mail it a link. This
is that portal's first, narrow slice: view and edit your own contact's personal details, nothing
else.

## Why a separate panel

The alternative — add `customer` to `User::MAIN_PANEL_ROLES` and loosen `ContactPolicy` and
`HasOwnershipVisibility` so a customer could reach Contacts inside the main panel — was considered
and rejected:

- The main panel's ownership scope (`HasOwnershipVisibility`) shows every *Public* record to any
  signed-in non-manager role, not just "your own"; a customer would see every public contact in
  the CRM, not only theirs.
- Every other main-panel resource or page (Companies, Tasks, Shoutbox, Mailbox, Watched,
  PriorityList, …) hardcodes `hasAnyRole(['super_admin', 'manager', 'employee'])` in its own
  Policy or `canAccess()`. Safe today only because none of them list `customer`.
- The Contacts Edit form is one shared schema; keeping a customer off Company, Permission, Groups,
  Related Companies and the linked login would need per-field disabling sprinkled through
  `ContactResource`, not a clean boundary.

A separate panel avoids all three: a customer reaches one page, which never takes a record id from
the URL — it always loads `Auth::user()->contact` — so there is no URL to change to reach anyone
else's, and the field list is whatever that page's own form declares.

## The panel (`app/Providers/Filament/PortalPanelProvider.php`)

`id`/`path` both `portal`. One page, `MyContact`, and no resources — `->navigation(false)`, so
there is no sidebar. Colors are `Emerald`/`Neutral`, distinct from the Slate (Administration) and
Amber (Main, Setup, User Settings) used elsewhere. `->passwordReset(RequestPasswordReset::class)`
reuses the same page every other panel already uses; nothing there changed. In demo mode it
answers 404 (`DisabledInDemo`), like Administration — there is no demo customer account for it to
be meaningful for.

Access: `User::canAccessPanel()` — `'portal'` → `hasRole('customer')` (and not disabled, not in
demo mode), mirroring the `'administration'` branch right above it. `main` and `user-settings`
still require `MAIN_PANEL_ROLES` as before, so `customer` cannot open those.

## The page (`app/Filament/Portal/Pages/MyContact.php`)

A single page, built like Administration → Mail Server
(`App\Filament\Administration\Pages\MailServer`) rather than on the RecordBrowser engine: one
record, no list, no create, no delete.

**It opens in View mode**, as every resource here does ("View before Edit, everywhere",
[conventions.md](conventions.md)). **Edit** swaps the read-only entries for the form, filled from
the record as it is now; **Save** writes it and goes back to View; **Cancel** goes back to View
and drops what was typed. The mode is a public `$editing` property, not a second route.

Two Filament details make this work, both easy to "simplify" back into a bug:

- **All three header actions (Edit, Save, Cancel) are always declared, each shown by its own
  `visible()`** — likewise the read-only entries and the embedded form in `content()`. Returning
  a different set of actions or components depending on `$editing` doesn't take: Filament
  registers a page's actions and schemas once, so only a component's own `visible()` is
  re-evaluated on every render.
- **The entries' `state()` is a closure, not a value.** Filament resolves a page's schemas
  early in the request and reuses that build for the final render, so a plain value captured in
  `content()` is what `$this->contact` held *before* Save's `update()`, and the View shows the old
  data after saving. A closure is evaluated at render time.
- `mount()` loads `Auth::user()->contact` (never a route parameter). The form is filled when Edit
  is pressed, not before.
  The ownership scope already resolves this correctly for a customer without any change:
  `Contact::applyExtraVisibility()` matches `user_id = $user->id`, always true for your own
  linked contact.
- Shown read-only in both modes: the contact's company, as plain text.
- Editable — the "personal details" the user asked for, reusing the exact `Field` declarations
  `ContactResource::fields()` already has (`toFormComponent()` for Edit, `toInfolistEntry()` for
  View — both self-contained, needing no Resource around them), so labels, `maxLength`, rules and
  the way an e-mail address or a website shows as a linked badge match the main Contacts screens:
  `last_name`, `first_name`, `title`, `email`, and three collections, the same as
  `ContactResource`'s (a card per item, see
  [Epesi-custom-fields.md](Epesi-custom-fields.md#collections)): phone numbers
  (`Field::collection('phones', PhoneNumber::class)`, each with its messengers), addresses
  (`Address`) and online accounts (`OnlineAccount`, the website among them). The form is bound
  to the contact (`->model()`), so the items load from it and `getState()` saves them through
  `syncCollection()`. `email` gets its own uniqueness rule
  (`unique(table: 'contacts', column: 'email', ignorable: $this->contact)`), since this form
  isn't Resource-bound.
- **Left off the form entirely, not merely disabled**: `company_id`, `permission`, `groups`,
  `relatedCompanies`, `memo`, `user_id` (the linked login). Because the form never declares these
  fields, `$data` never holds a key for them — there is nothing for a customer to post that would
  change them, a stronger guarantee than disabling a shared field would give. Staff still manage
  these from Contacts in the main panel.
- `save()` calls `Gate::authorize('update', $this->contact)` before saving — defense in depth,
  always true here since `ContactPolicy::update()` already allows `$contact->user_id ===
  $user->id` for any role; no change needed to that policy. The update is logged automatically by
  `Contact::getActivitylogOptions()` (`LogsActivity`), the customer as causer, same as any other
  edit to that contact.

## Password reset / new-account e-mail (`App\Support\Auth\NewAccountMailer`)

Used to hard-code the `main` panel. `panelFor(User $user)` now tries `main` then `portal` and uses
whichever the account can open; `canReceiveLink()`/`sendSetPasswordLink()` build on it. This is
what actually fixes a `customer` login: `whyNoLink()` returns `null` for one, Reset Password left
blank on Users → View e-mails a link to `/portal/password-reset/reset?...`, and creating a new
`customer` user does too. The "wrong role" message no longer names a fixed allowed-list (there are
now two, one per panel): `"Their role (:roles) has nowhere to sign in yet."`

## A reset link opened while signed in as someone else (`App\Filament\Auth\ResetPassword`)

Every panel shares one login session (none declares its own `->authGuard()`), and Filament's
reset-password page sends a signed-in visitor straight into the *current* panel instead of
showing the form. That is right for your own link, and wrong for someone else's: an administrator
still signed in from Administration who opened a customer's e-mailed link was sent to `/portal`,
which they can't open — a bare 403 with nothing to explain it.

The app's own `ResetPassword` (wired into all four panels through `->passwordReset(
RequestPasswordReset::class, ResetPassword::class)`) signs the visitor out first when the
signed-in account isn't the link's own — a real logout, the same one `Impersonation::stop()` does
(`Auth::logout()`, the session invalidated, its token regenerated, so the Logout event still
closes the Login Audit row) — and then Filament's own `mount()` shows the form as it does for any
guest. A link for the account already signed in is untouched: it still goes straight into the
app. Nothing else about the token or the page changed, so the link still works for its owner.

## Not built yet
- Anything beyond "view/edit your own contact": no history of your own orders, invoices, tickets —
  there is nothing else to show a customer yet.
- "Log in as user" (`Impersonation`) doesn't reach a customer: `Impersonation::allowed()` checks
  `canAccessPanel(Filament::getPanel('main'))` only. Support seeing what a customer's portal looks
  like would need that check (and the "back" redirect) generalized the same way `NewAccountMailer`
  was.
- A customer role beyond `customer` (a supplier's, say) would need its own branch in
  `canAccessPanel()` — there's no generic "any non-staff role gets the portal" rule, on purpose,
  so a new role doesn't silently gain portal access before someone decides it should.

## Tests

`tests/Feature/PortalTest.php`: only `customer` opens `/portal` (`super_admin`/`manager`/
`employee`/no role all refused, a deactivated `customer` refused, 404 in demo mode); a customer
cannot open `/`, `/administration` or `/user-settings`; the page shows only the signed-in
customer's own contact in View mode with their company shown; Edit switches to the form filled with
the current values; Cancel drops changes and goes back to View; Save updates the contact, is
logged with the customer as causer, shows the new values and goes back to View; the restricted
fields aren't on the form, and posting one under `data.*` anyway changes nothing; a new `customer`
user is e-mailed a link that opens `/portal/password-reset/reset`.

`tests/Feature/ResetPasswordAcrossPanelsTest.php`: an administrator signed in opens a customer's
link and gets the form (not a 403) with their own session ended, and the link still sets the
customer's password; the account's own link still goes straight in, unchanged; a guest sees the form.

`tests/Feature/UserManagementTest.php::test_the_reason_no_link_is_sent_is_the_accounts_own` and
the new `test_a_customer_can_receive_a_link_that_opens_the_portal` cover the `NewAccountMailer`
side.
