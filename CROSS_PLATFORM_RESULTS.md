# Cross-platform test results — Epesi PHP 8.2 (RC2 `v20260701-rc2`)

Fill this in as you test each platform. Legend: ✅ pass · ⚠️ works with a caveat · ❌ fail · ⬜ not tested yet.
Any ❌ → paste the error-log line to Claude → fix in Epesi code (not vendor) → number in `MIGRATION_NOTES.md` → re-test.
Baseline reference: Linux/XAMPP PHP 8.2 = fully ✅ (dev box).

## Summary matrix

| Check | Win (XAMPP) | cPanel | DirectAdmin | macOS |
|---|:--:|:--:|:--:|:--:|
| A. Prerequisites (`check.php` green) | ⚠️* | ⬜ | ⬜ | ⬜ |
| A. PHP is 8.2 | ✅ 8.2.12 | ⬜ | ⬜ | ⬜ |
| A. mbstring/intl/xml/fileinfo/imap | ✅‡ | ⬜ | ⬜ | ⬜ |
| A. mcrypt native OR polyfill | ✅ polyfill | ⬜ | ⬜ | ⬜ |
| A. DB LOCK permission | ✅ | ⬜ | ⬜ | ⬜ |
| A. `data/` writable | ✅ | ⬜ | ⬜ | ⬜ |
| B. Fresh install (`setup.php`) | ✅ | ⬜ | ⬜ | ⬜ |
| C1. Login / logout | ✅ | ⬜ | ⬜ | ⬜ |
| C2. Contact CRUD | ✅ | ⬜ | ⬜ | ⬜ |
| C3. A–Z quick-jump | ✅ | ⬜ | ⬜ | ⬜ |
| C4. File upload + view/download | ✅ | ⬜ | ⬜ | ⬜ |
| C5. Print / PDF | ✅ | ⬜ | ⬜ | ⬜ |
| C6. Roundcube mail opens | ✅ | ⬜ | ⬜ | ⬜ |
| C7. §22 encrypted note roundtrip | ✅ | ⬜ | ⬜ | ⬜ |
| C8. Search / filter | ✅ | ⬜ | ⬜ | ⬜ |
| **Verdict** | ✅ FULL | ⬜ | ⬜ | ⬜ |

`✅‡` Windows XAMPP ships several needed extensions **disabled** — enable ALL of these in `php.ini` + restart
Apache: **`zip`, `gd`, `imap`, `pdo_mysql`, `intl`** (see Windows findings for what each breaks). `check.php`
only tests zip/gd, so imap/pdo_mysql/intl must be verified manually (phpinfo).

`†` C6 **RESOLVED (2026-07-07): Roundcube opens.** Root cause was **not** the earlier autoindex red-herring — it
was (a) the missing `rc_` table prefix on fresh install (fixed in **§54**) and (b) the **`intl`** extension being
disabled (`INTL_IDNA_VARIANT_UTS46` undefined during RC login). With §54 + intl, the mailbox opens. The Apache
autoindex on `data/Base_Theme/templates/default/` is a separate cosmetic theme-asset quirk, still open (low prio).

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
  + a pre-flight install gate (pre-public).** Perf tip: enable **OPcache** (`zend_extension=opcache`,
  `opcache.enable=1`, `opcache.max_accelerated_files=100000`) — Epesi loads hundreds of files/request, big speedup on Windows.
- ✅ **C6 mail RESOLVED (2026-07-07):** Roundcube opens. Two real fresh-install fixes were needed: **§54**
  (`mysql.initial.sql` lost the `rc_` table prefix in the §30 RC 1.7.1 upgrade → `Table 'rc_session' doesn't exist`)
  and enabling **`intl`**. The real RC log is `data/CRM_Roundcube/log/errors` (Epesi overrides `log_dir`), NOT
  `RC/logs/`. main now carries §54, so the default ZIP installs clean.
- ✅ MySQL user must exist first (create + GRANT incl. the DB); DB permissions incl. **LOCK** all OK on Windows.
- ✅ PHP 8.2.12; `data/` writable; admin creation, module install, CRM post-install, **dashboard** all worked.
- ✅ Installer is **resumable** — after enabling GD, re-accessing continued the install (no clean reset needed).
- 🐛 **Pre-public robustness (2 items):** (1) `setup.php:299` `new mysqli()` is unguarded → on PHP 8.1+ a wrong
  DB credential throws an uncaught `mysqli_sql_exception` (scary fatal) instead of a friendly "check credentials";
  (2) a missing required extension white-screens the install instead of showing a clear prerequisites message.
  Both worth a small fix before public (wrap in try/catch + a pre-flight extension gate). `check.php` already
  *reports* the missing extensions (it caught zip+gd) but the installer doesn't *block* on them.

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
