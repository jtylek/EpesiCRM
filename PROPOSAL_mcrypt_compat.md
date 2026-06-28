# PROPOSAL — Restore encrypted notes on PHP 8 via `phpseclib/mcrypt_compat` (branch `experiment/mcrypt-compat`)

> **Status:** verified, reversible **proposal for Jasiek (the architect)**. NOT merged into
> `experiment/composer-deps` or `main`. Decision required: adopt `mcrypt_compat`.
>
> Cross-reference: `MIGRATION_NOTES.md` §22 (full analysis + RESOLUTION).

---

## 1. Summary

Epesi encrypts password-protected notes/attachments in
`modules/Utils/Attachment/AttachmentCommon_0.php::crypt()` using the `mcrypt_*` functions. **mcrypt
was deprecated in PHP 7.2 and removed in PHP 8.0**, so any operation on an encrypted note now fatals
(`Call to undefined function mcrypt_module_open()`). This branch fixes it by adding the
`phpseclib/mcrypt_compat` polyfill — a **drop-in** replacement, **zero Epesi code changes**.

---

## 2. The cipher (and why the choice is forced)

```
algorithm : rijndael-256  (256-bit BLOCK Rijndael — NOT AES)
mode      : CBC, zero-padding
key       : substr(sha1($password), 0, 32)
IV        : 32 bytes random, stored base64 next to the ciphertext
format    : base64(ciphertext) "\n" base64(iv) "\n" hint
integrity : md5(plaintext) appended before encryption
```

Two facts make `mcrypt_compat` the **only** option that preserves existing user data:

1. **`rijndael-256` is a 256-bit BLOCK cipher, not AES.** OpenSSL implements only AES (128-bit
   block), so **openssl cannot decrypt existing notes**. An "openssl/AES rewrite" would make every
   pre-existing encrypted note permanently unreadable.
2. **Note passwords are never stored** server-side (only `$_SESSION['client']['cp'.$id]` while the
   user is viewing). So a bulk server-side decrypt-and-re-encrypt **migration is impossible** — there
   is no key to migrate with.

→ Keeping the existing cipher (via a polyfill) is the only path that honors "users keep their old
encrypted data after the upgrade".

---

## 3. What was installed

`composer require phpseclib/mcrypt_compat`:
- `phpseclib/mcrypt_compat 2.0.8`
- `phpseclib/phpseclib 3.0.55`
- `paragonie/constant_time_encoding`, `paragonie/random_compat`

No Epesi code changed. The `mcrypt_*` functions are defined by
`vendor/phpseclib/mcrypt_compat/lib/mcrypt.php` via composer's `files` autoload (loaded with
`vendor/autoload.php`, which Epesi already requires at bootstrap). The whole block is guarded by
`if (!function_exists('mcrypt_list_algorithms'))`, so on a host that still has **native ext/mcrypt**
the native implementation takes precedence — the polyfill only fills the gap on PHP 8.

---

## 4. Verification

**Level 1 — functional, PASSED.**
- Standalone test (replicating `crypt()` exactly): encrypt→decrypt roundtrip succeeds on **php7.4 and
  php8.2.12** via the polyfill; identical deterministic ciphertext on both; `rijndael-256`; key/IV =
  32 bytes; UTF-8 preserved.
- **Real app, end-to-end:** created an encrypted note in the UI → DB row has `f_crypted=1` and
  `f_note` is ciphertext (base64), not plaintext → reopened with the password → decrypts correctly.

**Level 2/3 — native-mcrypt byte-compatibility — DEFERRED (no native mcrypt on the test machine).**
Both local PHPs lack ext/mcrypt, so both runs used the polyfill (proves consistency, not
native-compat). Assurance rests on `mcrypt_compat` being purpose-built and CI-tested byte-identical
to ext/mcrypt.

---

## 5. HARD GATE before any production upgrade

Before upgrading a production instance **that has encrypted notes**:
- On a **staging copy of the real data**, confirm the actual old notes decrypt with the user's
  password; **and/or** on a host that has native mcrypt (e.g. the cPanel / old-hosting portability
  phase), compare the ciphertext of a known plaintext between native mcrypt and `mcrypt_compat`.
- Do **not** upgrade a production instance with encrypted notes until this passes.

(Production instances created on PHP 7.x already store rijndael-256 data; the code fix alone is
expected to read it — this gate just empirically confirms it on real data.)

---

## 6. How to review / how to revert

- **Review:** `git diff experiment/composer-deps..experiment/mcrypt-compat` — the only first-party
  change is `composer.json` (+`composer.lock`, +vendor packages). No Epesi `.php` logic changed.
- **Revert (not merged):** don't merge; or delete the branch
  (`git push origin --delete experiment/mcrypt-compat`). `main`/`composer-deps` untouched.
- **Revert (if later merged):** `composer remove phpseclib/mcrypt_compat` (encrypted notes go back to
  fataling until another solution — i.e. the dependency is what enables the feature).

---

## 7. Decision for Jasiek

Adopt `phpseclib/mcrypt_compat`. It is the only data-preserving option (see §2), is a drop-in with no
code changes, works on any host (pure PHP), and is verified functional. The single open item is the
pre-production real-data decryption check (§5), which is a deployment gate, not a code concern.
