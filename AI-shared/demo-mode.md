# `DEMO_MODE`: what it actually does, and the gap vs. "let a visitor pick an employee"

Scanned 2026-08-27 (all 19 `DEMO_MODE` call sites in the tree) after being asked whether it could
back a "replace login with a select box, let a test visitor pick an employee" flow. Short answer:
the select-box login already exists, but it selects **user login accounts**, not CRM "Employee"
contacts - see the gap section below before building anything on this.

## Enabling it

Plain config constant, off by default:

- `include/config.php:68` - `if(!defined('DEMO_MODE')) define('DEMO_MODE',0);` (the ultimate
  fallback if nothing else defines it)
- `setup.php`'s generated `data/config.php` template ships it **commented out**:
  `//define('DEMO_MODE',0);` - a fresh install is never in demo mode by accident
- No admin-UI toggle anywhere - like `HOSTING_MODE`/`TRIAL_MODE`, it's a `data/config.php`
  hand-edit only

## The login screen swap

`modules/Base/User/Login/Login_0.php::body()`, ~line 96:

```php
if(DEMO_MODE) {
    global $demo_users;
    $form->addElement('select', 'username', __('Username'), $demo_users, array('id'=>'username', ...
        'onChange'=>'this.form.elements["password"].value=this.options[this.selectedIndex].value;'));
    $form->addElement('hidden', 'password', key($demo_users));
} else {
    // normal text/password fields
}
```

- `$demo_users` is a **global PHP array**, `['login_value' => 'Displayed label', ...]` - the array
  key becomes each `<option value="...">`, the array value is the visible dropdown text.
- Selecting an option submits `username = <that key>`; the `onChange` JS copies the same key into
  the hidden `password` field. So the form always submits `username === password`.
- **Nothing in this codebase defines `$demo_users` anywhere** - grepped the whole tree, the only
  hit is this one read site. It's meant to be hand-added as a plain global (not a `define()`) in
  `data/config.php`, right alongside `DEMO_MODE`. This repo has never actually populated it.

## This does not weaken authentication - and that's the catch

`Base_User_LoginCommon::check_login()` (`LoginCommon_0.php:24`) runs unconditionally, demo or not:

```php
$hash = DB::GetOne('SELECT p.password FROM user_login u JOIN user_password p ON u.id=p.user_login_id WHERE u.login=%s AND u.active=1', array($username));
if(strlen($hash)==32) return md5($pass)==$hash;
return password_verify($pass,$hash);
```

Since the demo form always submits `username === password`, the dropdown only produces a working
login if **that account's real, actually-hashed password already equals its own username**
(e.g. user `karina` really does have password `karina`). Nothing in the demo-mode code sets this
up or enforces it - it's a manual account-provisioning convention the login-page feature assumes.
Get it wrong (an account whose password isn't its own username) and picking that name from the
dropdown just fails to log in, silently confusing, since the page never says why.

`submit_recover()` (password reset) and `Administrator_0.php::submit_user_preferences()`
(My Settings) both special-case `DEMO_MODE && username=='admin'` to block changing that one
account's password/email - consistent with wanting `admin`'s password to stay `admin` forever in
a public demo. One place in `Administrator_0.php` (~line 336) blocks password/email changes for
**any** user, not just `admin`, while in demo mode.

## Everything else `DEMO_MODE` gates

Almost entirely via a per-module `admin_access()` static returning `!DEMO_MODE` (or equivalent) -
the same convention `HOSTING_MODE`/`TRIAL_MODE` use elsewhere:

| Area | File | Effect |
|---|---|---|
| RecordBrowser admin | `Utils/RecordBrowser/RecordBrowserCommon_0.php` | admin panel hidden |
| ACL admin | `Base/Acl/AclCommon_0.php` | admin panel hidden |
| Error-log admin | `Base/Error/ErrorCommon_0.php` | admin panel hidden |
| Mail-settings admin | `Base/Mail/MailCommon_0.php` | admin panel hidden |
| Language admin | `Base/Lang/Administrator/AdministratorCommon_0.php` | admin panel hidden; default-language change blocked (`Administrator_0.php:249`); demo's `admin` account is pinned to `en` (`AdministratorCommon_0.php:42`) |
| Module store/install | `Base/EpesiStore/EpesiStoreCommon_0.php` | admin panel hidden; `simple_install`/`simple_uninstall`/`validate` in `Setup_0.php` all print "Feature unavailable in DEMO" and no-op |
| Standalone `/admin/` suite | `admin/AdminIndex.php::demo_or_hosting()` | entire tool blocked, shows "Feature unavailable" |
| Dashboard | `Base/Admin/Admin_0.php` | "Admin Tools" launcher card hidden (`!DEMO_MODE && !HOSTING_MODE` guard) |
| Web-triggered updates | `update.php:581` | blocked for non-CLI requests when `TRIAL_MODE \|\| DEMO_MODE` |
| Print templates | `Base/Print/Template/SectionFromString.php` | raw PHP-tag execution disabled (security hardening, shared with `HOSTING_MODE`) |
| Config info panel | `admin/modules/ConfigInfo.php` | just displays `DEMO_MODE`'s value as a read-only row |

Net effect: turning `DEMO_MODE` on locks the *entire* admin surface for everyone, even a real
superadmin logged in normally - it's an all-or-nothing switch, not scoped to the demo-selected
account.

## The gap vs. "let a test user pick an employee"

The dropdown is keyed to **`user_login`/`user_password` accounts**, not CRM `contact` records.
A contact (an "Employee" - see `demo-data.md` for what that term means in this codebase) is only
selectable through this screen if *all* of:

1. it has a real linked user account (`contact.login` → `user_login.id`)
2. that account's password is literally set to equal its own username
3. someone has manually added `'that_username' => 'Display label'` to `$demo_users`

None of this is automated anywhere in the codebase today - `$demo_users` has no population code,
and there's no code path deriving it from the `contact`/`employee` pool at all.

**This also directly conflicts with the policy in `demo-data.md`**: `demo:generate:contacts`
deliberately never creates a login (`--create-user` was removed outright - see that file), and the
whole intended employee-setup flow is "create your own contact, clone it for more employees,"
none of which involves creating `user_login` rows either. So out of the box, none of the employees
this demo tooling or that manual workflow produces would ever show up in `DEMO_MODE`'s dropdown -
closing that gap would mean either relaxing the no-new-logins rule for employees specifically, or
building a different selection mechanism that doesn't route through real login accounts at all
(e.g., a pre-auth screen that calls `Acl::set_user()` directly for a chosen contact's linked
account, bypassing the password-equals-username convention altogether). Neither exists yet -
flagging the fork, not picking one, since it's a product decision, not just an implementation detail.
