# Notes' files — storage, View, Download and Get link

**Status: built.** Covered by `tests/Feature/Modules/AttachmentsTest.php` (the download,
preview and Get link tests) and `tests/Feature/Modules/RecordHistoryTest.php` (the note's
History tab).

"Notes" in the menu is the `Epesi/Attachments` module, the port of Epesi's `Utils/Attachment`.
A note holds a title, a rich-text body, any number of files, a permission and a sticky flag,
and is attached to any number of records. This document is about its **files**: where the bytes
live, and what the user can do with one. The storage layer itself is described once, in
[architecture.md](architecture.md) (search for "Utils/FileStorage"); this page only repeats
what the rest needs.

## Where a note's files live

Every file goes through `App\Services\FileStorage`, the port of `Utils/FileStorage`. Notes and
e-mail attachments share it, so identical bytes are stored once however many notes or messages
carry them.

### The tables

| Table | Model | A row is | Columns that matter |
|---|---|---|---|
| `stored_file_contents` | `StoredFileContent` | one distinct content | `hash` (sha512, unique), `size`, `mime_type` |
| `stored_files` | `StoredFile` | one *use* of a content under a name | `content_id`, `name`, `created_by` |
| `epesi_attachments` | `Attachment` | a note | `files`: a JSON list of `stored_files` ids |
| `epesi_mail_attachments` | `MailAttachment` | an e-mail's attachment | `stored_file_id`: one `stored_files` id |

`stored_files.content_id` restricts deletes, so a content can't be removed while a name still
points at it. A note keeps `StoredFile` ids as **strings** (the `FileUpload` field's state; its
tamper check only recognises a note's own files when they compare equal). The migration is
`database/migrations/2026_09_27_000000_create_file_storage_tables.php`. The names of the two
core tables in Epesi were `utils_filestorage_files` and `utils_filestorage`.

### On disk

The bytes are on the private `filestorage` disk, defined in `config/filesystems.php`, at
`storage/app/private/filestorage/`. A content is stored under its sha512: the first five hex
digits are one directory each, the rest is the file name (`FileStorage::pathFor()`,
`StoredFileContent::path()`). For a hash starting `a1b2c3d4…`:

```text
storage/app/private/filestorage/a/1/b/2/c/3d4…
```

- The **original file name is not on disk**, only in `stored_files.name`. Copying a file out of
  the directory gives you bytes with a hash for a name; the name and MIME type are in the tables.
- Epesi's `data/Utils_FileStorage` tree names the same bytes the same way.
- Two notes (or a note and an e-mail) with the same bytes have two `stored_files` rows and one
  `stored_file_contents` row and one file on disk.
- The disk is under `storage/` and private: no URL reaches it. The release zip doesn't contain
  it (see [Epesi-Laravel-distro.md](Epesi-Laravel-distro.md)) and an update leaves it in place.
- **A backup needs both halves**: the database (the two tables and `epesi_attachments`) and
  `storage/app/private/filestorage/`. Either alone leaves files that can't be named or names
  with no bytes.

### How a file is stored and released

- The MIME type is read from the bytes when the content is first stored. For what the bytes
  don't tell apart (plain text among them) it comes from the extension of the first name the
  content was stored under, as Epesi's `get_mime_type()` did.
- A file taken off a note, or a note deleted for good, deletes its `StoredFile`; the content
  and its file (and any directories that leave empty) go with the last `StoredFile` that used
  them. Storing and releasing the same content never overlap (a cache lock on the hash).
- A content whose `stored_file_contents` row is missing but whose file is on disk (a rolled-back
  transaction) is reused rather than written again; a `stored_files` row whose file is gone from
  disk is a "missing file", and the download answers 404.

Nothing serves the disk directly. A file is reachable only through the two routes below.

## What the user sees

The Files row of a note's View page, the Files column of the Notes list, and a record's Notes
tab all draw a file the same way, from `AttachmentResource::fileLinks()`:

- **A pill per file**: an icon for its type (image, video, audio, PDF or text, archive,
  anything else) and its name. Clicking either opens the file: a preview if its type can be
  previewed, a download if not.
- **View** (eye icon): opens the file in a new tab, rendered by the browser. Only shown for a
  type that can be previewed.
- **Download** (arrow icon): always saves the file.
- **Get link** (link icon): copies a link to the clipboard, for handing the file to someone who
  has no login. The button's tooltip says "Copied!" for a moment.

## The two routes

| Route | Name | Who may use it |
|---|---|---|
| `GET attachments/{attachment}/files/{file}` | `epesi.attachments.download` | signed-in users who may view the note |
| `GET attachments/{attachment}/files/{file}/shared` | `epesi.attachments.shared` | anyone holding a valid signed URL |

**`DownloadController`** (`?preview=1` for View) asks the question the Notes tab asks: may this
user see this note? It answers 403 to a guest. The model's ownership scope hides someone else's
private note, so that is a 404, the same answer a note that doesn't exist gets. A file is only
served through a note that holds it, so a note's id can't be paired with another note's file.
`?preview=1` serves `Content-Disposition: inline`, but only for a previewable type; on any other
file it is ignored and the file downloads.

**`SharedFileController`** has no `Auth::check()` and no `Gate`: the route's `signed`
middleware is the only gate. It serves a previewable type inline and anything else as a
download. The note's ownership scope adds no constraint for a guest (`Auth::user()` is null), so
a Private note's files are reachable through a link too. That is the point of the link, and
what Epesi's remote link did.

Both send `X-Content-Type-Options: nosniff`.

## What can be previewed

`StoredFile::isPreviewable()` is an allow-list, not a block-list:

- `image/*` except SVG, `video/*`, `audio/*`
- `application/pdf`, `text/plain`, `text/csv`, `application/json`

**SVG is excluded on purpose**, and so are HTML and XML, which fall outside the list anyway.
An SVG can carry a `<script>`; served inline from the application's own address, with the
viewer's session cookie, that is stored XSS. It downloads instead. Keep it that way if the list
grows: add a type only if a browser renders it without running anything from it.

To add a type, add its MIME type to `isPreviewable()`, and to `fileIcon()` in
`AttachmentResource` if it should have an icon of its own.

## Get link

The URL is `URL::temporarySignedRoute('epesi.attachments.shared', now()->addWeek(), …)`,
built while the page renders (`AttachmentResource::shareLinkButton()`). There is no token table.

- **Who can make one:** whoever can see the page it is built into, since that page is the only
  place a link appears.
- **Who can use one:** anyone who has it, until it expires (7 days), with no login and no
  permission check.
- **Expiry:** a fixed seven days from the page render. Every page load builds a new URL; the
  earlier ones stay valid until their own time is up.
- **Revoking:** a signed URL can't be revoked one by one. Deleting the file or the note stops
  it (404), and so does changing `APP_KEY` (which invalidates every signature, and much else).
- **Not logged.** Epesi wrote each download and preview to `utils_filestorage_access`; this
  port keeps no access log.

## Epesi's version, and how it maps here

Epesi opened a lightbox from the file name (`Utils_FileStorage_FileLeightbox`); here the same
choices are inline buttons.

| Epesi | Here |
|---|---|
| Lightbox → **View** (`preview` action, `inline` disposition) | View button, `?preview=1` |
| Lightbox → **Download** (`attachment` disposition) | Download button |
| Lightbox → **Get link**: a random token in `utils_filestorage_remote`, 7 days, served by `remote.php` to anyone | Get link: a signed URL, 7 days, no table |
| Lightbox → **File History** (who viewed or downloaded, from `utils_filestorage_access`) | not ported |
| **Mail** option, added by `CRM_Roundcube::file_field_getters()` when the user has a mail account: opens a new message with the Get link URL in the body | not built; see below |
| Inline thumbnails for JPEG, GIF, PNG and BMP, and a page-1 JPEG of a PDF rendered with Imagick | none: the browser draws images, and PDF is opened in its own viewer |
| Preview allowed for those types only | the allow-list above: also WebP, AVIF, text, CSV, JSON, audio and video |
| File names, sizes and download counts in a tooltip | none |

## Not built

- **E-mail.** In Epesi it was a plug-in action of the mail module and it did not attach the
  file: it put the Get link URL into a new message. The same route here is to let the Mail
  module add an action that opens a compose window with `shareLinkButton()`'s URL. That needs
  compose to accept a prefilled body, which it doesn't yet.
- **File history / download log.**
- **Office documents** (docx, xlsx, pptx) can't be shown by a browser. Previewing them means a
  converter (LibreOffice) or an embedded viewer service: infrastructure, not a UI change.

## Traps

- **A row link and a file link can't nest.** The Notes list makes every row clickable, and
  Filament wraps each cell's content in an `<a class="fi-ta-col">`. A pill's own `<a>` inside
  it is invalid HTML: the browser closes the outer link at the first inner one and tears the
  first pill apart (an empty pill and loose contents). The Files column therefore has
  `->disabledClick()`. Any column that prints its own links needs the same. A test asserts it.
- **The file pills' CSS is in the plugin, not in a stylesheet.** The panels' theme
  (`resources/css/filament/epesi/theme.css`) is compiled when a release is built, from the
  modules present then. A module installed later from a zip isn't scanned, so nothing
  guarantees that a utility class written into `fileChip()`'s markup exists. The pill's
  styles are therefore a `<style>` block added by `AttachmentsPlugin` through the panel's
  `STYLES_AFTER` render hook (as the History addon's red and green are), using Filament's own
  colour variables (`--gray-*`, `--primary-*`) and a `.dark` variant each.
- **`svg()` returns an `Htmlable`, not a string.** Icons in raw markup are
  `svg('heroicon-o-eye', 'w-4 h-4')->toHtml()`; the `heroicon-o-` names are the ones Filament
  ships.
- **Everything interpolated into the markup is escaped once**, including the whole `onclick`
  value (which holds a JSON-encoded string). The file's name is the one piece of user data in
  it.
- **A test file's MIME type comes from its bytes.** `'%PDF …'` content is stored as
  `application/pdf`; use another extension with plain content (`report.docx`) for a type that
  is not previewable.

## The History tab of a note

**Status: built.** Covered by `tests/Feature/Modules/RecordHistoryTest.php`.

Most of what follows is the shared History addon, so it applies to every long text, not only a
note's: Tasks' and Phone Calls' `description` and Companies' `memo` are `FieldType::LongText`
too.

### Labels and value formats

`Attachment` isn't built on the RecordBrowser engine (its rich-text editor and file upload
need a form of their own), so the shared History addon had no field labels or value formats
for it and printed the stored values: the note's HTML tags and `Permission: 0`.
`AttachmentResource::historyFields()` hands it `Field` objects (title; the note as
`->richText()`; permission as `RecordPermission`; sticky as Yes/No; files, see below), and
`HistoryRelationManager::loggedFields()` reads `historyFields()` from any resource that has it,
when it is not a `RecordsetResource`.

### Why the history is kept

The first version of this tab printed a long note twice, the whole old text and the whole new
one, and dropping `note` from the log was considered. It stays, because the fault was the
display, not the data:

- A note is where text gets overwritten, and the log's `old` value is the only copy of what
  was there before.
- Epesi kept every revision of a note in `utils_attachment_edit_history`, and re-encrypted the
  old revisions along with an encrypted note.
- The cost is small. Each entry holds the whole old and new text: a few kilobytes per edit.

### Long text: the words that changed

A short field shows `old → new`. A long text shows only the words that changed, with 8
unchanged words on each side and "…" for the rest, as a wiki's history does:

```text
Note: … the full note's own page [-instead of-] {+rather than+} expanding it inline. … 20 tests pass. {+Also checked in dark mode.+}
```

Removed words are a `<del class="epesi-history-old">` (red, struck through), added ones an
`<ins class="epesi-history-new">` (green). `Epesi\Modules\RecordBrowser\History\TextDiff` does
it:

- **Words, not markup.** Rich text is reduced to plain text first (`plainText()`). A block's
  end (`</p>`, `<br>`, `</li>`, a heading) becomes a line break, so paragraphs stay apart:
  before this, `<p>inline.</p><p>Removing` read "inline.Removing". `formatLoggedValue()`
  uses the same.
- **Spacing is no change.** Both texts are normalized first (spaces run together, lines
  trimmed, empty lines dropped).
- **An image is a word**, "[image]", so adding or removing one is a change. The first note
  edit this was tried on added a screenshot and nothing else, and came out as "formatting".
  Swapping one image for another still reads as no change of words (both are "[image]"), and
  so does a link's new address under the same link text.
- **A formatting-only edit** leaves the words equal while the HTML differs: the entry says
  "Note: Only the formatting changed" rather than nothing.
- **A line break is a token.** Unchanged, it is a `<br>`. At either end of an added run it is a
  `<br>` before or after the green text (an added paragraph); at either end of a removed run it
  is dropped. Only a break inside a changed run, or a change of breaks alone (a paragraph split
  or joined), shows as a "¶". A break alone between two changes keeps them apart.
- **Changes read as changes.** An unchanged run of one word between two changes joins them into
  one, so a rewritten sentence doesn't come out as words picked out around every "the" it kept.
- **Cut where it saves something.** Context is cut only when that hides more than two words. A
  removed or added run longer than 30 words is cut too, so a created note shows its first 30
  words and "…" (a created entry is a diff from nothing).
- **Everything is escaped.** Each word goes through `e()` before it is wrapped; a note's text
  is user data.

The diff is `sebastian/diff`'s `Differ::diffToArray()` over lists of words, which trims the
common start and end before it compares anything. It came with PHPUnit, but as a dev
dependency, and the release zip is built from `composer install --no-dev`, so it is in
`require` in its own right (`^6.0`, the version PHPUnit uses). It is PHPUnit's own library.
`jfcherng/php-diff` has ready HTML renderers, but a single maintainer.

### The hook: a change from both values

`formatLoggedValue()` formats one value at a time, and a word diff or a list of added and
removed files needs the old and the new value together. `Field::historyUsing()` takes a closure
`(mixed $old, mixed $new, Activity $entry, bool $whole)` that returns escaped HTML, or null for
`old → new`. `Field::formatLoggedChange()` calls it, and without one gives long text the word
diff. `$old` is null in a created entry; `$entry` is there for what a model logs beside the
values; `$whole` asks for the Show modal's uncut version.

### Files by name

- `Attachment::tapActivity()` (activitylog calls it on the model before saving an entry) logs
  `file_names` (id => name) with any entry whose old or new values hold files. It has to: a
  file taken off a note is deleted, name and all (`Attachment::booted()`). It runs before that
  deletion, since `LogsActivity` registers its listeners in `bootTraits()`, ahead of
  `booted()`'s. A test holds this.
- `historyFields()` declares `files` with `historyUsing()`, and
  `AttachmentResource::historyFileChanges()` shows only what changed:
  `Files: − draft.docx + screenshot-4.png`, the removed file struck through on red, the added
  one on green.
- Entries logged before names were logged hold ids only. Their names are looked up in
  `stored_files`, and a file that is gone shows as "deleted file".

### Show

On an entry that changes a long text, a Show row action (eye icon, "Show in full") opens a modal
with, for each long text in the entry, the whole diff (nothing cut) and the text as it read
after that edit ("As created" for a created entry, which has no diff). Rich text is rendered,
through `Str::sanitizeHtml()` since it is stored HTML; plain text keeps its line breaks
(`Field::formatLoggedVersion()`). Every entry holds the whole text already, so nothing more is
stored for it. It is the counterpart of the "Record historical view" tab of Epesi's
RecordBrowser, which showed a record as it was at a chosen edit.

### Restore

The Show modal has a **Restore this version** button: it saves the text as it read after that
edit ("As created" for a created entry) back into the record. With two long texts in one
entry, there is one button per field ("Restore Note").

- **A normal edit.** It is `forceFill()->save()` on the record, so it is logged like any other
  edit (the text it replaced is the new entry's old value, and can be restored in turn), and
  Watchdog, which acts on every new activity entry, treats it as one. A notification says
  "Version restored"; the tab
  re-renders, and a `refresh-page` event has the page read the record again, so the View
  page's own copy of the text is the restored one.
- **Who and when.** Offered to whoever may edit the record (the resource's `canEdit()`, i.e.
  the `update` policy: for a note, its author, a manager, or anyone when it is Public), and
  only when the version differs from the text the record has now. The latest version is the
  current text, so its entry has no button.
- **Asked twice.** The button submits the Show action with a `restore` argument naming the
  field (`makeModalSubmitAction()`). Arguments come from the browser, so `action()` checks both
  conditions again and does nothing otherwise.
- **Text only.** It restores the long text, not the rest of the entry: not the title, and
  never files, which are deleted when taken off a note.

### Rejected

- **Dropping `note` from the log.** See "Why the history is kept".
- **A revisions table, or storing only the changes.** It would duplicate `activity_log`, and
  rebuilding a version would mean replaying changes. Full copies are cheap at a note's size.
- **Merging quick edits into one entry** (the same user within a few minutes). It rewrites the
  audit trail.
- **Comparing any two versions.** More than a note needs. Show covers the usual question: what
  did it say before?

## Files

- `app/Services/FileStorage.php`, `app/Models/StoredFile.php`, `app/Models/StoredFileContent.php`:
  the storage, and `isPreviewable()`.
- `modules/Epesi/Attachments/routes/web.php`: the two routes.
- `modules/Epesi/Attachments/src/Http/Controllers/DownloadController.php`,
  `SharedFileController.php`.
- `modules/Epesi/Attachments/src/Filament/Resources/Attachments/AttachmentResource.php`:
  `fileLinks()`, `fileChip()`, `shareLinkButton()`, `fileIcon()`, `historyFields()`,
  `historyFileChanges()`.
- `modules/Epesi/Attachments/src/Models/Attachment.php`: `tapActivity()`, the files' names in
  the log.
- `modules/Epesi/Attachments/src/AttachmentsPlugin.php`: the pills' CSS.
- `modules/Epesi/RecordBrowser/src/Filament/RelationManagers/HistoryRelationManager.php` (the
  `changes` column, the Show action and its Restore), `src/Recordset/Field.php` (`richText()`,
  `historyUsing()`, `formatLoggedValue()`, `formatLoggedChange()`, `formatLoggedVersion()`)
  and `src/History/TextDiff.php`: the History tab. Its CSS is in
  `src/RecordBrowserServiceProvider.php`.
