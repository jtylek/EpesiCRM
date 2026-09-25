# User management

Covers the Users screen in the Administration panel and its **Log in as user** action, the
port of legacy Epesi's "Log as user". Legacy references are paths inside the legacy epesiCRM
checkout. The main ones are `modules/Base/User/Administrator/Administrator_0.php`
(`log_as_user()`) and `AdministratorCommon_0.php` (`get_log_as_user_access()`).

## The Users screen

`App\Filament\Administration\Resources\Users\UserResource` sits in the Administration panel,
which only super_admin can open (`User::canAccessPanel()`).

- **Actions shared by the table and the View page.** `UserActions` builds each action once.
  The table adds it as an icon button, and `ViewUser` adds it to the page header. This follows
  the same pattern as `ModuleActions`.
- **Accounts are deactivated, never deleted.** "Deactivate" and "Reactivate" flip `active`.
  An inactive user can't open any panel, and their records and Login Audit rows stay.
- **Password.** The Edit form has a password field only when creating a user. After that,
  the password changes through the separate "Reset Password" action on the View page.
- **Policies don't restrict super_admin.** filament-shield is configured with
  `define_via_gate` and `intercept_gate: 'before'`, so every Gate check passes for
  super_admin. `UserPolicy` therefore never refuses the only role that can reach this screen.
  A rule that must hold for super_admin has to live outside the Gate. For example,
  "Deactivate" is hidden on your own account by the action's own `visible()` check, and
  `UserPolicy::delete()` would not stop it. The same reason applies to CommonData's readonly
  guard (see [Common-data.md](Common-data.md)).

## Log in as user

A super_admin takes over another account's session without knowing its password. It is used
for support: seeing exactly what a user sees, with their permissions, their dashboard and
their mailbox.

### Where it is offered

- **Users list:** an icon button in each row, between Edit and Deactivate.
- **User View page:** a header action, between Reset Password and Deactivate.

Both come from `UserActions::logInAs()`. The action asks for confirmation ("Log in as
ktylek"), switches the session, and does a full page load (`navigate: false`) to the main
panel's dashboard. It can't stay on the current page, because the new user may not be
allowed into the Administration panel.

### Who can, and as whom

`Impersonation::allowed(User $target)` decides who may do it, and nothing else does:

- the signed-in user has the super_admin role;
- the target is not the signed-in user;
- the target can sign in to the main panel on their own, i.e.
  `$target->canAccessPanel(Filament::getPanel('main'))`. That excludes deactivated accounts
  and accounts with no role.

Another super_admin is a valid target, as it was for legacy's Super Administrator.

The action's `visible()` uses the same check, and `Impersonation::start()` checks it again
and throws `AuthorizationException` when it fails. The UI is therefore not the only guard.

The rule is deliberately not a `UserPolicy::impersonate()` ability. Because of the
filament-shield bypass described above, that ability would return true for super_admin
every time, including for your own account and for deactivated ones.

Legacy was configurable. A Super Administrator could always do it. A plain Administrator
could do it through two admin access levels: `log_as_user`, default on, for regular users,
and `log_as_admin`, default off, for other administrators. The port allows only super_admin and has
no setting. None of its roles matches legacy's plain Administrator level.

### How the switch works (`App\Support\Impersonation`)

The session remembers who started the impersonation, under
`Impersonation::SESSION_KEY` (`impersonator_id`). Legacy only swapped the session's user
(`Acl::set_user($id, true)`), so the only way back was to log out and in again.

`start($target)`:

1. Keeps the **original** administrator. If you are already logged in as one super_admin
   and log in as someone else from their account, `impersonator_id` still names you. If the
   target is that original administrator, the key is removed, because you are simply back.
2. Closes the current Login Audit row (see below).
3. Calls `Auth::login($target)`, which regenerates the session id (`migrate(true)`).
4. **Removes `password_hash_web` from the session.** This step is required. Filament's
   `AuthenticateSession` middleware stores the signed-in user's password hash in the session
   and signs the user out when the stored hash stops matching. Once the session belongs to
   another user, it would still hold the administrator's hash, and the next page would sign
   out the impersonated user and redirect to the login page. When the hash is missing, the
   middleware stores the new user's hash itself.

   The action runs in a Livewire request, and panel middleware such as `AuthenticateSession`
   doesn't run on those. As a result, nothing refreshes the hash until the next page load,
   and by then it's too late.

`stop()` reads `impersonator_id` and removes it. If that account is still active and still a
super_admin, it switches back the same way. Otherwise it signs out completely: a session
must not be handed back to an account that has since lost the right to it.

Logging out while impersonating ends the whole session, as any logout does. It does not
return to the administrator.

### The way back

`App\Filament\Support\ImpersonationNotice` renders a bar at the top of the content: "You are
logged in as Karina Tylek. **Back to Janusz Tylek**". It uses display names, so the linked
contact's name where there is one.

- **Shown in every panel.** `AppServiceProvider::boot()` registers the bar globally on
  `PanelsRenderHook::CONTENT_START`, not per panel, because an impersonated user can end up
  on any panel (main, user settings, Administration).
- **Inline styles.** Like `UpdateNotice`, the bar uses inline styles, because the panels
  load Filament's precompiled stylesheet and Tailwind classes that aren't already in it
  wouldn't work.
- **"Back" is a POST form.** The button posts to `route('impersonation.leave')`, which is
  handled by `LeaveImpersonationController` in `routes/web.php` (web group, so
  CSRF-protected). It is a POST for the same reason logout is: a link or an image on some
  other page must not be able to switch accounts.
- **Where you land.** After switching back, the controller redirects to the Users View page
  of the account you were logged in as, i.e. where you started.
  - If the session isn't impersonating anyone, it redirects to the main panel.
  - If `stop()` signed the administrator out, it redirects to the login page.

### Login Audit

The Login Audit records who was really using the account, which legacy's `base_login_audit`
could not tell apart:

- **Rows around the switch.** `FinalizeLoginAudit::closeCurrent()` gives the outgoing
  account's row its `ended_at` time at the moment of the switch, the same as a logout. The
  Logout listener calls the same method. `TrackLoginAudit` then opens a new row on the next
  page, because `login_audit_user_id` no longer matches.
- **The `impersonated_by` column.** The new row stores the administrator in
  `login_audits.impersonated_by`, a nullable foreign key to `users` with `nullOnDelete`
  (migration `2026_09_28_120000_add_impersonated_by_to_login_audits_table`). Administration →
  Login Audit shows it in a toggleable **Logged in by** column, and `LoginAudit::impersonator()`
  is the relation.
- **Written only when set.** `TrackLoginAudit` adds `impersonated_by` to the insert only when
  it has a value. An administrator who signs in to run the database update that adds this
  column goes through the same middleware. If the insert always named the column, that
  administrator would get an error before reaching Database update.

So one visit looks like this:

- the administrator's row, ended at the switch;
- the user's row, marked `impersonated_by`;
- a fresh administrator row after "Back".

**Record history does not show impersonation.** spatie/laravel-activitylog takes the causer
from `Auth::user()`, so an edit made while logged in as someone is recorded in the record's
History under that user's name. Only the Login Audit row, by its time span, shows that an
administrator was behind it. Adding the impersonator to activity log properties would close
that gap, but it isn't built.

### Other per-user state

- **Mailbox (Roundcube).** The Mailbox page issues a new single sign-on ticket on every load,
  and redeeming a ticket first ends any existing Roundcube session (see
  [Epesi-Laravel-Roundcube.md](Epesi-Laravel-Roundcube.md)). After switching, the Mailbox
  therefore opens the impersonated user's mail accounts, not the administrator's.
- **Language and regional settings** come from the signed-in user, so the interface switches
  to the impersonated user's language.

### Differences from legacy

| Legacy | Port |
| --- | --- |
| Super Administrator always; Administrator through the `log_as_user` / `log_as_admin` access levels | super_admin only, no setting |
| A plain link with no confirmation: a callback link on the user screens, a `?log_as_user=<id>` request parameter on the contact screens | A Livewire action with confirmation; "Back" is a POST |
| No way back except logging out | A bar on every page switches back |
| Login Audit shows only the impersonated login | `impersonated_by` / "Logged in by" names the administrator |
| Offered on the user list and user edit form, and on a contact's View page (`CRM_ContactsCommon::QFfield_login()` action bar, `CRM_Contacts::user_actions()` row action) | Users list and user View page only |

The contact-side entry point is not ported. A Contact's linked login already has its own
actions in `ContactLoginEntries` (Reset Password, Change Username). To add Log in as user there,
reuse `UserActions::logInAs()` with the contact's `user` as the record, rather than a second
implementation.

### Tests

`tests/Feature/ImpersonationTest.php` covers:

- the action from the View page and from the list;
- when it is hidden: your own account, a deactivated account, an account with no role;
- `start()` refusing a non-super_admin;
- nested impersonation keeping the original administrator;
- logging in as the original administrator ending impersonation;
- the way back;
- an administrator who lost the role being signed out instead;
- the Login Audit rows.

Two details matter when changing these tests:

- **The target needs its own password.** `UserFactory` gives every user the same hash, so
  the `AuthenticateSession` sign-out described above can't happen between two factory users.
  The test gives the target a different password and loads a page first, so the session holds
  the administrator's hash. Without the `password_hash_web` step in `switchTo()`, the test
  fails.
- **Set the panel again after a page request.** A `$this->get(...)` to a main-panel page makes
  `main` the current Filament panel. A `Livewire::test(ViewUser::class)` after it then builds
  URLs such as `filament.main.resources.users.index`, which doesn't exist. Call
  `Filament::setCurrentPanel('administration')` right before the Livewire test.

### Files

- `app/Support/Impersonation.php`: the rule, start and stop.
- `app/Filament/Administration/Resources/Users/UserActions.php`: `logInAs()`, used by
  `Tables/UsersTable.php` and `Pages/ViewUser.php`.
- `app/Filament/Support/ImpersonationNotice.php`: the bar, registered in
  `AppServiceProvider::boot()`.
- `app/Http/Controllers/LeaveImpersonationController.php` and `routes/web.php`
  (`impersonation.leave`): the way back.
- `app/Listeners/FinalizeLoginAudit.php` (`closeCurrent()`),
  `app/Http/Middleware/TrackLoginAudit.php`, `app/Models/LoginAudit.php` (`impersonator()`),
  `app/Filament/Administration/Resources/LoginAudits/Tables/LoginAuditsTable.php`: the
  Login Audit side.
- `lang/pl.json`: the Polish strings. `TranslationsTest` fails if a new one is missing.
