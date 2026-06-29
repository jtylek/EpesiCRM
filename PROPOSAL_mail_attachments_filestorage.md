# PROPOSAL — Store archived e-mail attachments in Utils_FileStorage (branch `experiment/mail-attachments-filestorage`)

> **Status:** proposal for review. Requested by **Jasiek** (phone, 2026-06-29): archived e-mail
> attachments should live in the central **Utils_FileStorage**, not in a per-module `CRM_Mail`
> folder — "it was never done." Authorized; design-first because it touches the data model + needs a
> data migration. **NOT a PHP-8 migration blocker** (e-mail archiving works on 8.2 as-is, just stores
> files in the wrong place) — keep it independent of the migration merge.

---

## 1. Problem

When an e-mail is archived (`CRM_MailCommon::archive_message()`), each attachment's bytes are written
**raw** to disk and the central content store is bypassed:

```php
// MailCommon_0.php  archive_message()  (lines ~587-594)
$attachments_dir = DATA_DIR.'/CRM_Mail/attachments/';
...
DB::Execute('INSERT INTO rc_mails_attachments(mail_id,type,name,mime_id,attachment) VALUES(...)', ...);
file_put_contents($attachments_dir.$id.'/'.$m['mime_id'], $m['content']);   // <-- raw, per-module folder
```

Read side (`CRM/Mail/get.php`) reads straight from that folder:
```php
$filename = DATA_DIR.'/CRM_Mail/attachments/'.$_GET['mail_id'].'/'.$_GET['mime_id'];
$buffer   = file_get_contents($filename);
```

Consequences:
- **No deduplication** (Utils_FileStorage is content-addressed by sha512; the same attachment sent to
  many recipients is stored once — here it's stored every time).
- **Inconsistent storage** — every other Epesi attachment goes through Utils_FileStorage; e-mail is the
  odd one out, in `data/CRM_Mail/attachments/`.
- No single place for storage policy, quotas, backup, or the path-resolution fixes (§20/§36).

`rc_mails_attachments` schema today: `mail_id, type, name, mime_id, attachment` (a disposition flag) —
**no file reference**; the bytes exist only on disk.

---

## 2. Proposed design (4 parts)

### 2a. Schema
Add a nullable `file_id BIGINT` to `rc_mails_attachments` (FK → `utils_filestorage.id`). Null = legacy
row not yet migrated.

### 2b. Write path (`archive_message`)
Replace the raw write with the central store:
```php
$file_id = Utils_FileStorageCommon::add_data_from_content($m['content'], $m['filename']);
DB::Execute('INSERT INTO rc_mails_attachments(mail_id,type,name,mime_id,attachment,file_id)
             VALUES(%d,%s,%s,%s,%b,%d)', array($id,$m['type'],$m['filename'],$m['mime_id'],$m['attachment'],$file_id));
```
(Drop the `mkdir`/`file_put_contents` block.)

### 2c. Read path (`get.php`) — with legacy fallback
```php
[$mimetype,$name,$attachment,$file_id] = DB::GetRow('SELECT type,name,attachment,file_id FROM rc_mails_attachments WHERE mail_id=%d AND mime_id=%s', ...);
if ($file_id) {
    $buffer = Utils_FileStorageCommon::read_content($file_id);
} else { // legacy, not yet migrated
    $buffer = file_get_contents(DATA_DIR.'/CRM_Mail/attachments/'.$_GET['mail_id'].'/'.$_GET['mime_id']);
}
```
Fallback keeps every instance working **before/while** the migration runs.

### 2d. Data migration (an Epesi patch, runs via `runpatches.php`/update)
For each `rc_mails_attachments` row: ensure `file_id` (read legacy file → `add_data_from_content()`
→ set `file_id`), then **once the content is confirmed in FileStorage** (`file_exists($file_id)`),
**delete** the legacy `data/CRM_Mail/attachments/<mail_id>/<mime_id>` and remove the now-empty
per-mail dir. **Jasiek decided this is a MOVE** (2026-06-29) — legacy files are removed after
verification, not kept. Verify-before-delete keeps it safe; idempotent.

---

## 3. Reuse (no new storage code)
- `Utils_FileStorageCommon::add_data_from_content(&$content, $filename)` — store + dedup, returns id (`FileStorageCommon_0.php:208`)
- `Utils_FileStorageCommon::read_content($id)` (`:514`), `meta($id)` (`:455`), `file_exists($id)` (`:486`)

Files affected: `modules/CRM/Mail/MailCommon_0.php` (write), `modules/CRM/Mail/get.php` (read),
`modules/CRM/Mail/get_remote.php` if it also serves attachments (verify), a new patch under
`modules/CRM/Mail/patches/`, and an install hook to add the `file_id` column.

---

## 4. Backward compatibility & data safety
- The read fallback means **nothing breaks** before the migration patch runs.
- The migration **moves** each attachment: it stores the bytes in FileStorage and sets `file_id`,
  then deletes the legacy file **only after** `file_exists($file_id)` confirms the content is stored
  (Jasiek's decision, 2026-06-29). Verify-before-delete = no data loss window.
- Content-addressing means re-storing is idempotent (same bytes → same hash → same file); after the
  move the canonical copy lives only in FileStorage (backed up with the rest of `data/`).

---

## 5. Risks
- Low–moderate. Touches the e-mail attachment data model + a data migration over potentially many rows
  (this client: 41 files; large instances: many more — the patch should be batch/timeout-aware like
  other Epesi patches).
- `%b` binding of the disposition flag is preserved as-is.

---

## 6. Verification
1. Archive a **new** e-mail with an attachment → row has `file_id`; the file appears under
   `data/Utils_FileStorage/` (not `CRM_Mail/attachments/`); opening it via `get.php` works.
2. On data with **legacy** attachments (this client's 41): before patch → opens via fallback; run the
   patch → rows get `file_id`, files now resolve from Utils_FileStorage, opening still works.
3. Dedup check: archive the same attachment twice → one physical file in Utils_FileStorage, two rows.

---

## 7. Decision for Jasiek
Adopt the above (schema `file_id` + write via FileStorage + read with legacy fallback + migration
patch). It centralizes e-mail attachments in Utils_FileStorage with dedup, keeps existing data
readable throughout, and reuses existing storage helpers. Independent of the PHP-8 migration — ship on
its own branch.
