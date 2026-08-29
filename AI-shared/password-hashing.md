# Login password hashing

## Current state (as of 2026-08-29)

`modules/Base/User/Login/LoginCommon_0.php` (`Base_User_LoginCommon`) owns all login-password
hashing/verification. New hashes use **argon2id** when the running PHP build has libargon2
compiled in (`defined('PASSWORD_ARGON2ID')`), falling back to bcrypt (`PASSWORD_DEFAULT`)
otherwise — Epesi runs on a wide range of hosts and not all of them ship the argon2 extension.
Both are produced via `password_hash()`/verified via `password_verify()`; the algorithm choice
is centralized in `password_algo()`/`hash_password()` so `add_user()` and
`change_user_preferences()` don't each decide independently.

`check_login()` self-heals hashes on successful login instead of just verifying and moving on:

- A legacy 32-char raw MD5 hash (pre-2015 accounts, see `patches/20150701_password_hash.php`
  which widened `user_password.password` to `C(256)` for bcrypt) is still accepted for
  backward-compat login, but is now immediately rehashed to the current algorithm right after
  a successful match — this is the only point in the app where the plaintext and a
  verified-correct hash are both available for those old rows, so it's the only place the
  upgrade can happen.
- An existing bcrypt hash gets upgraded via `password_needs_rehash($hash, $algo)` if the
  target algorithm/cost ever changes in the future (e.g. a later PHP release changing what
  `PASSWORD_DEFAULT` means, or this codebase later dropping the argon2-availability check).

No schema patch was needed for the argon2id switch: `user_password.password` was already
widened to `C(256)` back in 2015 for bcrypt, which comfortably fits an argon2id hash too, and
the upgrade path is self-healing at login rather than a batch migration (you can't rehash a
password you don't have the plaintext for, so there's nothing a `patches/` script could do for
already-stored hashes anyway).

## Verification (2026-08-29)

Confirmed live, not just by inspection: created a disposable test account via a one-off
bootstrapped script calling `Base_User_LoginCommon::add_user()` (same pattern as
`recordbrowser-live-schema-changes.md`'s non-destructive schema changes — bootstrap
`include.php` with `SET_SESSION=false` + `ModuleManager::load_modules()`, no UI/patch
needed), force-set its hash to bcrypt (`$2y$10$...`) to simulate a pre-fix row, then logged
in through the real login form (headless Edge via Playwright, `channel: 'msedge'`,
`ignoreHTTPSErrors: true` for the self-signed cert — see environment-gotchas.md's browser
recipe) and confirmed the Dashboard rendered. Re-checked `user_password.password` for that
row afterward: it had flipped to `$argon2id$v=19$m=65536,t=4,p=1$...`, proving the
rehash-on-login self-heal actually fires and not just that the code compiles. Test account
deleted afterward — the cleanup itself hit a real FK trap on the first attempt (a naive
`DELETE FROM user_login` failed silently), see `environment-gotchas.md`'s "never hard-delete
a `user_login` row" entry for the details and the fix used here.

## History

- **Pre-2015**: raw `md5($pass)`, 32-char hash.
- **2015-07-01** (`patches/20150701_password_hash.php`): switched new hashes to
  `password_hash($pass, PASSWORD_DEFAULT)` (bcrypt) and widened the column to `C(256)`;
  `check_login()` kept accepting the old 32-char MD5 hashes indefinitely for accounts that
  hadn't reset their password since, but never upgraded them.
- **2026-08-29**: switched to argon2id (bcrypt fallback) and added the rehash-on-login
  self-healing described above, so both the remaining legacy MD5 rows and any future
  algorithm change get upgraded automatically the next time each user logs in, instead of
  silently persisting forever.
