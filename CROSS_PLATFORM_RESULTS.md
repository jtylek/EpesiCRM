# Cross-platform test results — Epesi PHP 8.2 (RC2 `v20260701-rc2`)

Fill this in as you test each platform. Legend: ✅ pass · ⚠️ works with a caveat · ❌ fail · ⬜ not tested yet.
Any ❌ → paste the error-log line to Claude → fix in Epesi code (not vendor) → number in `MIGRATION_NOTES.md` → re-test.
Baseline reference: Linux/XAMPP PHP 8.2 = fully ✅ (dev box).

## Summary matrix

| Check | Win (XAMPP) | cPanel | DirectAdmin | macOS |
|---|:--:|:--:|:--:|:--:|
| A. Prerequisites (`check.php` green) | ⬜ | ⬜ | ⬜ | ⬜ |
| A. PHP is 8.2 | ⬜ | ⬜ | ⬜ | ⬜ |
| A. mbstring/intl/xml/fileinfo/imap | ⬜ | ⬜ | ⬜ | ⬜ |
| A. mcrypt native OR polyfill | ⬜ | ⬜ | ⬜ | ⬜ |
| A. DB LOCK permission | ⬜ | ⬜ | ⬜ | ⬜ |
| A. `data/` writable | ⬜ | ⬜ | ⬜ | ⬜ |
| B. Fresh install (`setup.php`) | ⬜ | ⬜ | ⬜ | ⬜ |
| C1. Login / logout | ⬜ | ⬜ | ⬜ | ⬜ |
| C2. Contact CRUD | ⬜ | ⬜ | ⬜ | ⬜ |
| C3. A–Z quick-jump | ⬜ | ⬜ | ⬜ | ⬜ |
| C4. File upload + view/download | ⬜ | ⬜ | ⬜ | ⬜ |
| C5. Print / PDF | ⬜ | ⬜ | ⬜ | ⬜ |
| C6. Roundcube mail opens | ⬜ | ⬜ | ⬜ | ⬜ |
| C7. §22 encrypted note roundtrip | ⬜ | ⬜ | ⬜ | ⬜ |
| C8. Search / filter | ⬜ | ⬜ | ⬜ | ⬜ |
| **Verdict** | ⬜ | ⬜ | ⬜ | ⬜ |

Priority: **Windows + one panel (cPanel or DirectAdmin)** first; macOS + the second panel are follow-ups.

---

## Per-platform detail

Copy the block below per platform as you go. Record the PHP version, extensions, and any error-log lines.

### Platform: __________  (e.g. Windows 11 + XAMPP 8.2.x)

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
