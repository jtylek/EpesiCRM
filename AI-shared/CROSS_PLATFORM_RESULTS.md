# Cross-platform test results — Epesi PHP 8.2 (RC2 `v20260701-rc2`)

Fill this in as you test each platform. Legend: ✅ pass · ⚠️ works with a caveat · ❌ fail · ⬜ not tested yet.
Any ❌ → paste the error-log line to Claude → fix in Epesi code (not vendor) → number in `MIGRATION_NOTES.md` → re-test.
Baseline reference: Linux/XAMPP PHP 8.2 = fully ✅ (dev box).

## Summary matrix

| Check | Win (XAMPP) | cPanel | DirectAdmin | macOS |
|---|:--:|:--:|:--:|:--:|
| A. Prerequisites (`check.php` green) | ⚠️* | ✅ | ✅ | ⬜ |
| A. PHP is 8.2 | ✅ 8.2.12 | ✅ 8.2.31 | ✅ 8.3 ¤ | ⬜ |
| A. mbstring/intl/xml/fileinfo/imap | ✅‡ | ✅‡ | ✅‡ | ⬜ |
| A. mcrypt native OR polyfill | ✅ polyfill | ✅ polyfill | ✅ polyfill | ⬜ |
| A. DB LOCK permission | ✅ | ✅ | ✅ | ⬜ |
| A. `data/` writable | ✅ | ✅ | ✅ | ⬜ |
| B. Fresh install (`setup.php`) | ✅ | ✅ | ✅¤¤ | ⬜ |
| C1. Login / logout | ✅ | ✅ | ✅ | ⬜ |
| C2. Contact CRUD | ✅ | ✅ | ✅ | ⬜ |
| C3. A–Z quick-jump | ✅ | ✅ | ✅ | ⬜ |
| C4. File upload + view/download | ✅ | ✅§ | ✅¶ | ⬜ |
| C5. Print / PDF | ✅ | ✅ | ✅ | ⬜ |
| C6. Roundcube mail opens | ✅ | ✅ | ✅ | ⬜ |
| C7. §22 encrypted note roundtrip | ✅ | ✅ | ✅ | ⬜ |
| C8. Search / filter | ✅ | ✅ | ✅ | ⬜ |
| **Verdict** | ✅ FULL | ✅ FULL | ✅ FULL | ⬜ |

`✅‡` Windows XAMPP ships several needed extensions **disabled** — enable ALL of these in `php.ini` + restart
Apache: **`zip`, `gd`, `imap`, `pdo_mysql`, `intl`** (see Windows findings for what each breaks). `check.php`
only tests zip/gd, so imap/pdo_mysql/intl must be verified manually (phpinfo).

`†` C6 **RESOLVED (2026-07-07): Roundcube opens.** Root cause was **not** the earlier autoindex red-herring — it
was (a) the missing `rc_` table prefix on fresh install (fixed in **§54**) and (b) the **`intl`** extension being
disabled (`INTL_IDNA_VARIANT_UTS46` undefined during RC login). With §54 + intl, the mailbox opens. The Apache
autoindex on `data/Base_Theme/templates/default/` is a separate cosmetic theme-asset quirk, still open (low prio).

`✅§` **cPanel C4:** file upload (5.8 MB presentation), save, download-all, and **get-link for PDF** all work;
**get-link for a `.png` downloaded an empty/unopenable file** ("system doesn't recognise the extension") — minor,
possibly a one-off; re-test with a known-good PNG to confirm reproducible vs one-off. Low priority, not blocking.

**cPanel (`test.epesibim.com`, PHP 8.2.31) — FULLY VALIDATED ✅ (2026-07-07).** Smoother than Windows: extensions
via the panel (Select PHP Version → Extensions), not php.ini. **Same 5 extensions required** (`zip/gd/imap/pdo_mysql/
intl`) — **`intl` was OFF despite `check.php` all-green** (identical to Windows → confirms the check.php gap on TWO
platforms; strong case for the pre-public check.php fix). Also had to raise `upload_max_filesize`/`post_max_size`/
`memory_limit` via **MultiPHP INI Editor** (host defaults 2M/8M/64M too low — 64M memory risks OOM at install; set
32M/32M/256M). Mail account was an **external server** (not the host's own) with `IMAP Root='INBOX.'` set proactively
→ mail send/receive + `INBOX.CRM Archive`/`INBOX.CRM Archive Sent` work. All C1–C8 + §22 + 5.8 MB upload + watchdog +
agenda pass. Deploy = `main` ZIP + File Manager server-side Extract; DB via cPanel MySQL panel (account prefix).

**DirectAdmin (`test.epesibim.com`, PHP 8.3) — FULLY VALIDATED ✅ (2026-07-07).** DA is a **stricter host** than
cPanel/Windows and surfaced **3 real portability bugs the others hid** (all fixed on `main`):
`¤¤` **§55** — setup's `.htaccess` compat-check flagged the default template incompatible: the `Header` directives
were guarded by `<IfModule mod_alias.c>` instead of `<IfModule mod_headers.c>`; DA has mod_alias but not mod_headers
→ 500. Fixed the guard (+ dropped PHP-5 magic_quotes, memory 64→256M). Install proceeds via "Ok" (.htaccess is
hardening, not required to run).
`¶` **§56** — attaching a file fataled `Call to undefined function passthru()`: `get_mime_type()` shelled out to the
`file` command first; DA disables `passthru` (shared-hosting `disable_functions`), and on PHP 8 a disabled function is
*undefined* → fatal before the fallback. Fixed: PHP `fileinfo` primary + guarded passthru.
**§57** — the red "multiple Roundcube sessions not supported" alarm (shown when the `RCWIN_` rewrite is unavailable,
as on DA) softened to a calm once-per-session note (Epesi's `Roundcube_0.php`, not the RC vendor).
`¤` Tested on **PHP 8.3** (host offered 8.1/8.3, not 8.2) — bonus real runtime validation on 8.3 (previously only
CI-linted). All C1–C8 + §22 + mail send/receive + `INBOX.CRM Archive`/`Sent` archiving pass. Same INI bumps as cPanel
(upload/post/memory) + the 5 extensions (`intl` was off despite `check.php` green — **3rd platform confirming that
gap**). Perf: RC noticeably slower than cPanel → enable **OPcache** (no LiteSpeed/FPM tuning here). Multiwin
unsupported (RCWIN_ rewrite needs mod_rewrite + AllowOverride) → single mail window only, graceful.

`⚠️*` = `check.php` (which only checks zip/gd) is green after enabling those; full 5-extension set above.

Priority: **Windows + one panel (cPanel or DirectAdmin)** first; macOS + the second panel are follow-ups.

---

## Per-platform detail

Copy the block below per platform as you go. Record the PHP version, extensions, and any error-log lines.

### Platform: Windows + XAMPP (PHP 8.2.12) — FULLY VALIDATED ✅ incl. mail (2026-07-06/07)

**Findings (recorded):**
- ⚠️ **Windows XAMPP ships 5 needed extensions DISABLED — enable ALL in the Apache php.ini + restart Apache:**
  **`extension=zip`, `extension=gd`, `extension=imap`, `extension=pdo_mysql`, `extension=intl`** (DLLs in `php/ext`).
  What each breaks if missing:
  - **`zip`** → `class BackupArchive extends ZipArchive` (`include/backups.php`) fatals → **blank page** on install.
  - **`gd`** → `Utils_Image`'s `php5-gd` dep unmet → the **entire Base module pack fails to install** (cascade
    Utils_Image → Base_Theme_Administrator → … → Base). Hard install requirement.
  - **`imap`** → Roundcube can't start (hard RC requirement).
  - **`pdo_mysql`** → Roundcube can't connect to its DB.
  - **`intl`** → RC login fatals `Undefined constant INTL_IDNA_VARIANT_UTS46` (IDN host conversion).
  `check.php` only tests zip/gd → verify imap/pdo_mysql/intl via phpinfo. **Recommend adding all five to check.php
  + a pre-flight install gate (pre-public).**
- ✅ **C6 mail RESOLVED (2026-07-07):** Roundcube opens. Two real fresh-install fixes were needed: **§54**
  (`mysql.initial.sql` lost the `rc_` table prefix in the §30 RC 1.7.1 upgrade → `Table 'rc_session' doesn't exist`)
  and enabling **`intl`**. The real RC log is `data/CRM_Roundcube/log/errors` (Epesi overrides `log_dir`), NOT
  `RC/logs/`. main now carries §54, so the default ZIP installs clean.
- ⚠️ **Mail archiving needs the account's IMAP namespace set** (Karina recalled this from PHP-7.4 installs).
  Sent copies + the `CRM Archive` / `CRM Archive Sent` IMAP folders were empty because the account's
  **`f_imap_root`** was blank; the server (`mail.mrf.epesi.cloud`) uses the `INBOX.` namespace, so folder names
  must be `INBOX.CRM Archive` etc. (`epesi_archive.php:12-14` prefixes with `f_imap_root`; `config.inc.php:103`
  sets `imap_ns_personal` from it). Setting `f_imap_root='INBOX.'` fixed it — mails now archive. The **`IMAP Root`
  field IS editable in the mail-account form** (Karina set it there; `visible=false` in `MailInstall.php:78` only
  hides the *list column*, not the edit field). **NOT a migration bug** (pre-existing config). **DECISION (Karina,
  2026-07-07): fix by DOCUMENTATION**, not code — the mail-account setup guide must tell users to set **IMAP Root**
  to their server's IMAP *personal namespace*: **`INBOX.`** for Courier / some Dovecot (e.g. the epesi.cloud
  hosting), **empty** for flat-namespace servers (Gmail / Office365 / modern Dovecot). Do NOT hardcode `INBOX.`
  (breaks flat-namespace servers in reverse). Auto-detect via IMAP `NAMESPACE` was considered but documentation
  was chosen (field already exposed). Cosmetic leftover: empty bare `CRM Archive`/`CRM Archive Sent` folders from
  pre-`imap_root` attempts — deletable in RC Settings→Folders.
- ✅ MySQL user must exist first (create + GRANT incl. the DB); DB permissions incl. **LOCK** all OK on Windows.
- ✅ PHP 8.2.12; `data/` writable; admin creation, module install, CRM post-install, **dashboard** all worked.
- ✅ Installer is **resumable** — after enabling GD, re-accessing continued the install (no clean reset needed).
- 🐛 **Pre-public robustness (2 items):** (1) `setup.php:299` `new mysqli()` is unguarded → on PHP 8.1+ a wrong
  DB credential throws an uncaught `mysqli_sql_exception` (scary fatal) instead of a friendly "check credentials";
  (2) a missing required extension white-screens the install instead of showing a clear prerequisites message.
  Both worth a small fix before public (wrap in try/catch + a pre-flight extension gate). `check.php` already
  *reports* the missing extensions (it caught zip+gd) but the installer doesn't *block* on them.
- ⚠️⚠️ **OPcache on Windows/XAMPP crashes Apache outright unless `ThreadStackSize` is raised (2026-07-23).**
  Simply enabling OPcache (`zend_extension=opcache`, `opcache.enable=1` in `php.ini` — the earlier perf tip in
  this doc) makes every `mpm_winnt` worker thread crash-loop: Apache's error log fills with `VirtualProtect()
  failed [87] The parameter is incorrect`, and Windows Event Viewer shows `httpd.exe` faulting inside
  `php8ts.dll` with `0xc00000fd` (STATUS_STACK_OVERFLOW) — every worker dies before it can serve a request, so
  the whole site times out (**looks exactly like "Epesi isn't running"**, not an OPcache problem — cost real
  debugging time before Event Viewer pointed at the actual fault). Root cause: Apache's `<IfModule
  mpm_winnt_module>` block (`apache/conf/extra/httpd-mpm.conf`) has **no `ThreadStackSize` directive at all**,
  so it silently falls back to a built-in default too small for PHP 8.1+'s per-thread stack/guard-page handling
  on Windows (documented upstream, e.g. [php-src#8250](https://github.com/php/php-src/issues/8250)). **Fix:
  add `ThreadStackSize 8388608` (8MB) to that block, THEN enable OPcache** — verified stable across multiple
  clean restarts with both in place. Do the `ThreadStackSize` fix first; enabling OPcache without it *will* take
  the site down.
- ⚠️ **`memcached` PHP extension needs manually-downloaded native dependency DLLs on Windows (2026-07-23).**
  XAMPP's bundled `php_memcached.dll` (`php/ext/`) fails with `Unable to load dynamic library
  'php_memcached.dll' (The specified module could not be found)` even though the file exists — the real cause is
  missing native dependencies (`libmemcached.dll`, `libhashkit.dll`, `libmemcachedprotocol.dll`,
  `libmemcachedutil.dll`). Fix: download the matching build from `downloads.php.net/~windows/pecl/releases/
  memcached/<version>/php_memcached-<version>-8.2-ts-vs16-x64.zip` (match PHP's exact build via `php -v`: thread
  safety + VC version + arch) and copy all four dependency DLLs into **both** `php/ext/` and `apache/bin/` —
  `php/ext/` alone loads fine under PHP CLI (`php.exe` lives in `php/`, its own DLL search path) but silently
  fails under Apache's `mod_php` (`httpd.exe` lives in `apache/bin/`, a different search path); copying to both
  locations is what actually fixes it everywhere. Also prefer the maintained `memcached` extension (`Memcached`
  class) over the old, unmaintained `memcache` extension (`Memcache` class, last released ~2013) — Epesi's own
  session code already prefers `memcached` when both are loaded (`include/session.php`,
  `EpesiSessionMemcachedStorage::__construct`). A memcached **server** (daemon) must also be installed/running
  separately (e.g. as a Windows service on port 11211) — the PHP extension only lets PHP talk to one, it doesn't
  provide the server itself. To actually route Epesi's sessions through it, `data/config.php` needs
  `SESSION_TYPE` set to `'memcache'` or `'memcached'` **and** `MEMCACHE_SESSION_SERVER` set to e.g.
  `'127.0.0.1:11211'` — both default to file-based sessions otherwise (`include/config.php` fallback defines).
- ⚡ **Bundled MariaDB 10.4 ships a tiny "small systems (≤64M)" `my.ini` template — fine for install-testing,
  not for real use (2026-07-23).** Bumped for local dev/test on a 16GB box: `innodb_buffer_pool_size` 16M→1GB;
  `innodb_log_file_size` 5M→128M (MariaDB 10.4 auto-resizes the redo log cleanly on next start after a clean
  shutdown — no manual log-file surgery needed, confirmed via `mysql_error.log`: "Resizing redo log..."/"New log
  files created"); `innodb_flush_log_at_trx_commit` 1→2 (durability tradeoff: up to ~1s of committed
  transactions could be lost on an OS crash, not a MySQL crash — acceptable for local dev, **not** recommended
  for production).

### Platform: __________  (blank template — copy this block per platform)

**Environment**
- [ ] PHP version reported: `__________` (must be 8.2.x)
- [ ] `check.php` opened — screenshot / notes: __________
- [ ] Extensions present: mbstring ☐  intl ☐  xml ☐  fileinfo ☐  imap ☐  gd ☐  zip ☐  curl ☐  openssl ☐
- [ ] mcrypt: native ☐  /  polyfill engaged (no native, notes still work) ☐
- [ ] DB permissions all green in `check.php` incl. **LOCK** ☐
- [ ] `open_basedir` not blocking `data/` `vendor/` tmp ☐
- [ ] `data/` and subtrees writable by web user ☐ (Windows: web user can write; panel: 755/writable)

**Install**
- [ ] `setup.php` fresh install completed, FirstRun ran, dashboard loaded ☐
- [ ] `data/config.php` written, `data/` dirs created, theme compiled ☐

**Core smoke** (watch the server error log throughout)
- [ ] C1 Login / logout ☐
- [ ] C2 Contact create / edit / delete ☐
- [ ] C3 RecordBrowser list + A–Z quick-jump (click letter / "All" / "123") ☐
- [ ] C4 File upload + view/download ☐ (Windows: watch deep `data/Utils_FileStorage/…` paths / 260-char limit)
- [ ] C5 Print / PDF ☐
- [ ] C6 Roundcube mail module opens (needs imap); send/receive if a test mailbox exists ☐
- [ ] C7 §22 encrypted note: create + reopen with password → decrypts ☐
- [ ] C8 Search / filter ☐

**Platform-specific watch-items**
- Windows/macOS: file permissions on `data/`; Windows 260-char path limit; CRLF in generated files.
- cPanel/DirectAdmin: PHP selector = 8.2; restricted DB user (LOCK); mcrypt usually absent → polyfill; mail
  (sendmail vs SMTP); cron (`cron_token.php`); compiled-theme dir writable.

**Issues found** (paste error-log lines here → to Claude)
- __________

**Verdict:** ⬜ (✅ all green + clean log = "cross-platform confirmed")

---

### §22 native→polyfill gold test (do on ≥1 host that has, or had, native mcrypt)
- [ ] Encrypt a note on a **native-mcrypt** PHP (or the real 7.4 client instance) → open it on the 8.2 target
      → decrypts to the same plaintext ☐
- Notes: __________
- This closes the deferred §22 proof (byte-compatibility of the phpseclib/mcrypt_compat polyfill with native mcrypt).
