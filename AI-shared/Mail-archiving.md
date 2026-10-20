# Mail archiving

How an e-mail gets into Epesi's archive (the **E-mails** recordset of the `Epesi/Mail` module),
and what must never put it there. Roundcube itself, single sign-on, the address book and the
Mailbox page are in [Epesi-Laravel-Roundcube.md](Epesi-Laravel-Roundcube.md); the cron job in
[cron.md](cron.md).

## The rule: a person archives, nothing else does

**No e-mail is archived automatically, in bulk, or on a schedule.** A user reads the message
and decides. This was settled on 2026-10-08 and it is critical: don't add an auto-archive
option, an "archive the whole folder" job, a server-side rule that files mail, or anything
that archives mail a person didn't pick.

The only ways into the archive:

| Way in | Who triggers it |
|---|---|
| Roundcube's **Archive** button, on one message, a selection or a conversation | the user, per click |
| Roundcube's compose toggle **Archive this message after sending** | the user, per message (defaults to the account's `archive_on_sending`, which defaults to off) |
| Sending from the CRM (`ComposeMail`, "Archive" checkbox) | the user, per message |
| **Upload .eml** on the E-mails list or a record | the user, per file |
| `import:legacy mail` | an administrator, once, at cutover from old Epesi |

Selecting several messages in Roundcube and archiving them in one click is fine: the user
chose them. What's ruled out is archiving mail nobody chose.

What was removed on 2026-10-08:
- The per-account **auto-archive** option (`auto_archive`, `auto_archive_folders`): it archived
  every INBOX/Sent message involving a known contact. Columns dropped by
  `2026_10_08_140000_drop_auto_archive_from_epesi_mail_accounts`.
- The scheduled **`mail:fetch`**, which archived everything in each account's "CRM Archive"
  folder every 5 minutes, including mail moved there by other mail clients. Replaced by
  `mail:check`, which only counts unread mail (below).
- The **Fetch now** button on Settings → Mail accounts, which did the same on demand. Replaced
  by **Check now** (unread count only).

## `mail:check`: unread counts, nothing else

`mail:check` (`Epesi\Modules\Mail\Console\CheckMailCommand`) runs from the scheduler every 5
minutes (`MAIL_CHECK_SCHEDULE`, `withoutOverlapping()`, `runInBackground()`). For each account
with an IMAP server it asks for the INBOX's unread count (IMAP `STATUS UNSEEN`: no message is
downloaded or marked read) through `UnreadCounter::check()`, which:
- caches `{count, error}` for 10 minutes (`UnreadCounter::CACHE_SECONDS`), longer than the
  schedule, so a late run doesn't leave the widget empty;
- records `last_checked_at` and `last_error` on the account without touching `updated_at`,
  which is part of the cache key (`epesi-mail-unseen:<id>:<updated_at>`): editing the account
  invalidates its cached count, a check doesn't.

One account failing (bad password) doesn't stop the others; the command then exits with failure
so Administration → Cron shows it.

The dashboard's **Mail** widget (`UnreadMailWidget`) only reads that cache. It asks the server
itself only when there's no cached count (cron not running, a new or just edited account) or on
its own **Check now** button. No queue is involved: epesi runs `QUEUE_CONNECTION=sync` with no
worker, so a queued job would gain nothing; the background schedule already keeps the mail
servers off every page request.

## The Archive button in Roundcube

The plugin is `modules/Epesi/Roundcube/roundcube-plugins/epesi_archive/` (a port of Epesi's
`epesi_archive`). It adds an **Archive** button to the message list and the message view,
disabled in the archive folder and in Drafts, as in Epesi. The account's archive folder
("CRM Archive" by default, `archive_folder` on the account) is created and subscribed at login
if it's missing.

### Why the "CRM Archive" folder

Archiving does two things, in this order: the message is **archived in Epesi**, read from
whatever folder the user is in, and only then **moved to the "CRM Archive" folder** on the
IMAP server. So it leaves the inbox (it's dealt with) but stays on the mail server, readable
from any other computer or mail client. The folder is where archived mail lives on the mail
server; it is not a drop box that Epesi watches. Mail moved into it some other way (another
client, a server rule) is **not** archived.

A message that couldn't be archived is never moved. If the mail server refuses the move after
archiving, the message is archived anyway and stays in the inbox; the dialog after archiving
still opens, with that warning in place of its usual text, and still offers linking.

### The click, step by step

1. **The checks, in Epesi's order.** The button posts the selection to the plugin action
   `plugin.epesi_archive` (`check_and_move()`), which uses `epesi_archive_matcher`:
   1. **Too many?** At most 200 messages per click (`MAX_PER_CLICK`): each one is read and
      stored while the user waits.
   2. **Already archived, by anyone?** A message whose Message-ID is in the archive skips the
      contact check (3) and goes on with the rest: the page links the one copy to this person,
      moves it and the dialog says "Message already archived" (Archiving a message someone
      already archived, below). A deleted copy doesn't count; see No duplicates below. The
      check needs the real Message-ID, which Roundcube fetches only because of the plugin's
      `storage_init` hook (Bug fixed below).
   3. **Linked to anything?** The action reads each remaining message's From, To and Cc
      (Epesi checked only From and To). Any contact or company with one of those addresses
      counts, your own contact included, as in Epesi's `look_contact()`. If a message matches
      nothing, the action shows Epesi's warning: "Matching contact or company not found. Click
      again to force archive without contact association." It remembers the warned messages
      in the Roundcube session (Epesi's `force_archive`), so the next click archives them
      anyway. One warning covers every unmatched message in the selection; Epesi warned one
      message at a time.
   4. **Otherwise** it answers with the `plugin.epesi_archive_go` callback, carrying the
      folder the user is in and the UIDs that passed.
2. **Hand-over.** The plugin's JavaScript shows Roundcube's busy indicator and posts
   `{epesiRoundcube: 'archive', folder, uids}` to the parent page (postMessage, same origin
   only). Roundcube itself moves nothing.
3. **Archive, then move.** The Mailbox page (`Mailbox::archive($folder, $uids)`) calls
   `MailFetcher::archiveFrom()`. It opens the folder read-only (EXAMINE, so nothing is marked
   read), reads each message by UID and passes it to `MailArchiver` (links, thread,
   attachments into file storage, which de-duplicates them). Then it moves exactly the
   archived UIDs to the account's archive folder (`Mailbox::move()`: IMAP `UID MOVE`, or COPY
   + `\Deleted` + EXPUNGE on a server without MOVE).
4. **Refresh.** The page answers the frame with `refresh` (or `archive-done` when nothing
   left the folder). Roundcube clears its busy indicator and reloads the list, so the
   archived messages are gone from the inbox.
5. **The dialog** (below).

`epesi_archive_matcher` has to give the same answer as the linking `MailArchiver` does: no
visibility rule, deleted records left out, and the own contact counted. It is plain PHP, and
`RoundcubeTest::test_the_archive_check_agrees_with_what_archiving_links` runs it and
`ContactMatcher` over the same records.

### What archiving links

- **The contact of whoever archives is always linked**, whether or not their address is on
  the message. That is what the archive must tell first, especially for a shared mailbox
  (support@…), whose addresses say nothing about who filed it. It is also the `employee` of the
  archived mail. Epesi listed a message on its employee's contact too; `MailImporter` links
  imported mail to its employee, and a migration added the link to mail archived before.
- `MailArchiver` links every contact and company whose address is in From, To or Cc, through
  `ContactMatcher`.
- Attachments (into file storage) and the thread.
- An outgoing direction when the sender is one of your own addresses, including your
  contact's extra addresses.

### The dialog after archiving

When the Mailbox page has archived at least one message (from the Archive button, or a sent
copy from **Archive this message after sending**, below), it opens a modal
(`Mailbox::archivedDialogAction()`), **every time**: linking is part of archiving, so the
dialog can't be turned off (a "Don't show this message again" checkbox was tried and removed
on 2026-10-08).
- **Heading:** "Message archived" (or ":count messages archived"); "Message already archived"
  (":count messages already archived") when none of them was new.
- **Text:** "Message archived in Epesi and moved to CRM Archive folder" (the account's own
  folder name). When all were archived before: "It was already in Epesi's archive: it is now
  linked to you too, and moved to CRM Archive folder." A mixed click adds "One of them was
  already archived…" / ":count of them were already archived…". The page tells them apart by
  `wasRecentlyCreated` on what `MailArchiver` returns (`afterArchiving()`, `already`).
- **Also link to** (optional): any number of records, one line each (a table repeater,
  `links`): **Recordset**, then **Record**, with **Add another record** for the next line and a
  delete button per line. Empty lines are ignored, and a record picked twice is linked once.
  The recordsets are the
  ones allowed for e-mails under Administration → Related modules
  (`MailServiceProvider::recordTypes()`, the `E-mails` recordset feature): by default contacts,
  companies, tasks, meetings and phone calls, plus whatever an administrator enables (tickets,
  projects, …). Before you type, the record list shows your **recent records** of that
  recordset (the records you opened last, `RecentRecords`), or the newest ones if you opened
  none; typing searches. Every archived message of that click is linked to every record picked.
- **OK** at the bottom, under the link fields, like every modal's buttons
  ([conventions.md](conventions.md)). There is no Cancel: OK without a record just closes.

If the mail server refused the move to the archive folder, the dialog opens all the same, with
a warning icon and "Message archived in Epesi, but not moved to CRM Archive folder: the mail
server refused the move (…). The message is still in your inbox." The messages are archived,
so linking works as usual.

The record fields are `LinkRecordAction::typeField()` / `recordField()`, the same ones the
**Link to record** action on an archived message uses.

### Archive this message after sending

A compose toggle that defaults to the account's `archive_on_sending`. It sets Roundcube's
"save sent message in" (`_store_target`) to the archive folder, so the sent copy is stored
there instead of in Sent. The `message_sent` hook remembers its Message-ID in the Roundcube
session; on the next page load the plugin passes it to the parent with `archived`, and
`MailFetcher::archiveFiled()` finds it in the archive folder by Message-ID (`IMAP SEARCH HEADER
Message-ID`) and archives it. Nothing else in that folder is read. It never warns, like Epesi's archiving on send.

Step by step:

1. **Send.** Roundcube sends the message and saves its copy in the archive folder (the toggle
   set `_store_target`). The `message_sent` hook adds its Message-ID to
   `$_SESSION['epesi_archive_fetch']`. The copy isn't saved yet when the hook runs, so nothing
   is archived in this request.
2. **Next page load.** Roundcube goes back to the message list (`sent_successfully()` →
   `list_mailbox()`). `init()` passes the IDs to the page as `epesi_archive_fetch` and clears
   them from the session, so they're handed over once.
3. **Hand-over.** The plugin's JavaScript posts `{epesiRoundcube: 'archived', messageIds}` to
   the parent page, and the Mailbox page's `archived()` calls `archiveFiled()`, which archives
   them through `MailArchiver` (links, thread, attachments, no duplicates). Nothing is moved:
   the copy is already in the archive folder.
4. **The dialog.** The same dialog as the Archive button's (`afterArchiving(..., sent: true)`,
   see The dialog after archiving), with its own wording:
   - **Heading:** "Message sent and archived" (":count messages sent and archived").
   - **Text:** "Sent message archived in Epesi and saved to CRM Archive folder" (the account's
     own folder name), never "moved".
   - **Also link to** and **OK** work as they do after the Archive button: every record picked
     is linked to the sent message.

If the mail server doesn't return the copy (it wasn't saved, or the folder isn't there), the
page shows "The message was not archived", as the Archive button does.

## No duplicates

Every way into the archive goes through `MailArchiver`: the Archive button, `.eml` upload,
sending from the CRM and the legacy import. It keeps one copy per message:
- It looks the message up by Message-ID across all users. A message without one is looked up
  by sender, recipients, subject and date.
- A deleted copy is restored rather than stored again, and counts as newly archived
  (`wasRecentlyCreated` set).
- The lookup and the insert run under a cache lock per message
  (`epesi-mail-archive:<hash>`), so two colleagues filing the same message at once can't both
  find it missing and both store it.
- Archiving an existing message again links it to the archiving person's contact (below) and to
  explicit links, such as the record a message was sent from. Not to the addresses matched
  again: the first archiving linked those.

### Archiving a message someone already archived (built 2026-10-08)

The archive keeps **one object per e-mail**, shared by everyone, so when a second person
archives a message already in the archive (another user filed it, the same person filed it
earlier from another mailbox, or an .eml upload), it doesn't stop at a warning:
- **The dialog after archiving still opens**, saying "Message already archived" in place of
  "Message archived in Epesi and moved to CRM Archive folder", with the usual **Also link to**
  fields and OK.
- **The existing copy gets a link (a pointer) to this person's contact**, the same link
  archiving always adds for whoever archives (see What archiving links). No second copy is
  stored. If the link is already there, nothing is added.
- Records picked in the dialog are linked to the existing copy, as for a new one.
- **The message is moved to the archive folder**, as a new one is: the person has dealt with
  it (confirmed 2026-10-08).

How: `check_and_move()` hands such messages on (`plugin.epesi_archive_go`) without the contact
check, instead of only warning as Epesi did (its `already_archived` label is gone);
`MailArchiver::archive()`, finding the existing copy, links the archiving user's contact
(`linkTo()` adds a link once); `MailFetcher::archiveFrom()` moves it with the rest; the dialog
words it. Tested by
`RoundcubeTest::test_archiving_a_message_already_archived_links_it_to_you_moves_it_and_says_so`
and `MailTest::test_a_message_is_archived_once_whoever_archives_it`.

Verified on bim.epe.si on 2026-10-08 with j@epe.si's "IT Budget for 2025": first archived by
an .eml upload, then the Archive button in Roundcube opened the dialog with "Message already
archived", kept the one copy (`/mails/8320`) and moved the message to INBOX.CRM Archive.

## Bug fixed: "Message already archived" for mail that isn't (found and fixed 2026-10-08)

**Symptom.** Archive on j@epe.si's "IT Budget for 2025" (INBOX, UID 19854, Message-ID
`<23e398dd-461c-bd96-5d5e-75a1ebf1c2a9@epe.si>`) said "Message already archived", but the
message was in no archive row (not even a deleted one), locally or on bim.epe.si, so it
didn't show under Joe Marcozzi's e-mails or in the E-mails list.

**Cause.** `check_and_move()` reads the selection with Roundcube's
`$storage->fetch_headers()` and checks `$header->messageID`. Roundcube **doesn't fetch the
Message-ID header** by default: `rcube_imap_generic::fetchHeaders()` asks only for DATE FROM
TO SUBJECT CONTENT-TYPE CC REPLY-TO LIST-POST DISPOSITION-NOTIFICATION-TO X-PRIORITY, plus the
`fetch_headers` option, or every header with messages caching or `all_headers` on
(`rcube_imap::get_fetch_headers()`). Every header object starts with the placeholder
`messageID = 'mid:<sequence number>'` (`rcube_imap_generic.php`, `fetch()`), which only a
fetched Message-ID replaces. So the plugin checks `mid:4`, `mid:17`, … instead of real
Message-IDs.

The legacy import brought in 25 old Epesi mails whose stored `message_id` is literally `mid:N`
(old Epesi's `epesi_archive` had the same flaw and saved the placeholder). The test message
was 4th in the INBOX, so its placeholder `mid:4` matched row 4388 (a 2022 Santander "eRaty"
mail, legacy id 4394), and the plugin dropped it as a duplicate.

**Effects.**
- A false "Message already archived" for any message whose position in its folder equals one
  of those 25 `mid:N` values, in every mailbox. The message is neither archived nor moved.
- The real duplicate check never fires: a message that is in the archive passes the check and
  is archived again. `MailArchiver` looks it up by its real Message-ID (it reads the raw
  message), so no second copy is stored, but the user sees "Message archived" instead of
  "Message already archived".
- `epesi_archive_matcher::found()` is unaffected: From, To and Cc are fetched by default.

**Why the tests missed it.** `RoundcubeTest` fed the matcher Message-IDs directly; nothing
ran Roundcube's real header fetch.

**The fix.**
- `epesi_archive::storage_init()` (a `storage_init` hook) adds `MESSAGE-ID` to Roundcube's
  `fetch_headers` option through `epesi_archive_matcher::fetch_headers()`, keeping what other
  plugins added. Don't remove it: without it the already-archived check is meaningless.
- `epesi_archive_matcher::archived()` ignores a `mid:` placeholder
  (`epesi_archive_matcher::placeholder()`), should one ever get through.
- Migration `2026_10_08_160000_clear_placeholder_message_ids_on_epesi_mails` sets the
  `mid:N` values to NULL; mail without a Message-ID is matched by sender, recipients, subject
  and date. `MailImporter` no longer imports a `mid:` value as a Message-ID.
- `RoundcubeTest::test_roundcubes_header_fetch_gives_the_check_the_real_message_id` runs
  Roundcube's own `rcube_imap::fetch_headers()`, its FETCH command and parser, against a fake
  server that answers only the header fields asked for: without the hook it gets `mid:4`, with
  it the real Message-ID. Roundcube isn't committed, so the test is skipped until
  `roundcube:install`. `test_the_archive_check_asks_for_message_ids_and_ignores_roundcubes_placeholders`
  covers the rest without Roundcube.
- Checked against the real j@epe.si INBOX: Roundcube now returns
  `<23e398dd-…@epe.si>` for UID 19854, and the check finds it not archived.

## Epesi's version, and how it maps here

| Epesi | Here |
|---|---|
| `epesi_archive` wrote into Epesi's `rc_mails` from inside Roundcube, then moved the message | the plugin checks; the Mailbox page archives each message from its folder through `MailArchiver`, then moves it to the archive folder |
| "Use EPESI Archive directories": cron archived whatever was in the archive folders | not carried over: nothing is archived on a schedule |
| A separate "CRM Archive Sent" folder | one archive folder; the archiver records the direction |
| "Message already archived", "click again to force archive" | the same messages and order |
| No dialog after archiving | the dialog above, with linking to another record |

## Files

- `modules/Epesi/Roundcube/roundcube-plugins/epesi_archive/`: `epesi_archive.php` (checks,
  move, sent copies), `epesi_archive.js` (button, move, postMessage), `epesi_archive_matcher.php`
- `modules/Epesi/Roundcube/src/Filament/Pages/Mailbox.php` (`archive()`, `archived()`,
  `archivedDialogAction()`) and `resources/views/mailbox.blade.php` (the postMessage receiver)
- `modules/Epesi/Roundcube/src/Services/TicketIssuer.php`: the plugin's translated labels
- `modules/Epesi/Mail/src/Services/MailFetcher.php` (`archiveFrom()`, `archiveFiled()`),
  `Imap/Mailbox.php` + `Imap/WebklexMailbox.php` (`move()`), `MailArchiver.php`,
  `ContactMatcher.php`, `UnreadCounter.php`
- `modules/Epesi/Mail/src/Console/CheckMailCommand.php` (`mail:check`)
- `modules/Epesi/Mail/src/Filament/Actions/LinkRecordAction.php`
- `modules/Epesi/Mail/src/Filament/Widgets/UnreadMailWidget.php`

## Testing

- `RoundcubeTest::test_the_archive_button_archives_from_the_inbox_then_moves_and_offers_a_link`:
  only the clicked UIDs are archived, from the INBOX, then moved to "CRM Archive"; the dialog
  links to a task, opens every time with OK at the bottom; a message the
  server doesn't return says "The message was not archived".
- `RoundcubeTest::test_a_message_the_server_will_not_move_is_still_archived_and_said_so`: the
  dialog opens with the warning and still links
- `RoundcubeTest::test_a_sent_copy_saved_to_the_archive_folder_is_archived_by_message_id`:
  only that copy is archived, the dialog says "Message sent and archived" (not "moved"), links
  a task and a company, and the copy stays in the archive folder.
- `RoundcubeTest`: the matcher agrees with `ContactMatcher`, and knows what is already
  archived.
- `RoundcubeTest::test_archiving_a_message_already_archived_links_it_to_you_moves_it_and_says_so`:
  one copy, a link for whoever clicks, moved, "Message already archived", and a mixed click.
- `RoundcubeTest::test_roundcubes_header_fetch_gives_the_check_the_real_message_id` and
  `test_the_archive_check_asks_for_message_ids_and_ignores_roundcubes_placeholders`: the
  `mid:N` bug.
- `MailTest::test_archiving_reads_only_the_clicked_messages_from_the_archive_folder` (sent
  copies, by Message-ID)
- `MailTest::test_mail_check_only_counts_unread_mail_and_archives_nothing`
- `MailTest::test_the_dashboard_widget_shows_cached_unread_counts_without_recent_mail`
