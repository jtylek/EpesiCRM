# Porting status: Epesi → epesi-laravel

This file tracks which parts of the original PHP Epesi app
([jtylek/epesi](https://github.com/jtylek/epesi)) exist here and which don't.
The "Epesi module" column uses that repository's `modules/` paths.

## Ported

| Epesi module | Here | Notes |
|---|---|---|
| CRM/Contacts (contacts, companies) | `modules/Epesi/CRM/{Contacts,Companies}` | |
| CRM/PhoneCall, CRM/Tasks, CRM/Meeting | `modules/Epesi/CRM/{PhoneCalls,Tasks,Meetings}` | |
| CRM/Calendar | `app/Filament/Pages/Calendar.php` | Providers are pluggable (`CalendarRegistry`) |
| CRM/LoginAudit | Administration → Login audits | |
| Base/User, Base/Acl | Administration → Users, Shield roles | |
| Utils/RecordBrowser | `modules/Epesi/RecordBrowser` | Engine, custom fields, history. `RecordExtensions` lets modules add addon tabs and header actions to any record |
| Base/Setup, Base/EpesiStore | Module system, `modules/Epesi/{Store,StoreServer}` | |
| Utils/Attachment | `modules/Epesi/Attachments` | A Notes tab on companies, contacts, tasks, meetings and phone calls: rich text, files on the private disk, permission, sticky. Files download through an authorised route |
| Utils/Watchdog | `modules/Epesi/Watchdog` | Watch toggle on every record, auto-watch on create, watch whole record types, bell notifications, and a **Watched** page with unread counts. Events are `activity_log` rows |
| CRM/Followup | `modules/Epesi/Followup` | **Close / Follow-up** on open tasks, meetings and calls: sets the status, adds an optional note, and schedules a new task, meeting or call with the same people. Leaves tracing notes on both records when Attachments is enabled |
| CRM/Mail, CRM/Roundcube (archive) | `modules/Epesi/Mail` | Per-user IMAP/SMTP **Mail accounts**; an **E-mails** archive with threads, attachments and a sandboxed body viewer; **E-mails** tabs on contacts, companies, tasks, meetings and calls; **E-mail addresses** tab (extra addresses) on contacts and companies. Mail is archived by moving it to the account's `CRM Archive` IMAP folder from any client, by optional auto-archive of INBOX/Sent when a known contact is involved, by uploading `.eml` files, or by sending from the CRM (reply, reply all, forward, attachments, archive-on-send; a copy also goes into the IMAP Sent folder). A **Mail** dashboard widget shows unread counts per account (cached 3 minutes, as in Epesi) and recently archived mail. `mail:fetch` runs from the scheduler every 5 minutes |
| Utils/Messenger (alerts) | `modules/Epesi/Reminders` | A **Reminders** tab on tasks, meetings and phone calls: remind yourself or colleagues "N minutes/hours/days before" the record's time (task deadline, meeting date + time, call time) or at a fixed date and time, with an optional message and optional e-mail. Relative reminders move when the record is rescheduled. `reminders:send` runs every minute from the scheduler and delivers due reminders to the bell (only to recipients who can still see the record) and, if asked, by e-mail through the app's own mailer. **My reminders** dashboard widget with a turn-off button. Other modules add their record types with `Reminders::startTimeFor()` |
| Apps/Shoutbox | `modules/Epesi/Shoutbox` | Dashboard widget with public and private messages. Authors can delete a message for 10 minutes; super admins can delete any message |
| Utils/CommonData | `modules/Epesi/CommonData` | Administration → Common Data: the editable lookup lists (countries and states, contact and company groups). CRM/Status, CRM/Access and CRM/Priority stay PHP enums |
| Base/RegionalSettings | `modules/Epesi/RegionalSettings` | Per-user language, timezone, date/time format and location, under Settings in the main panel |
| Base/Lang | `lang/`, each module's `lang/`, `App\Support\Locale\Locales` | English and Polish. Each user picks a language (system default from setup, browser language on the login page). Bell notifications and reminder e-mails go out in the recipient's language. `php artisan lang:import-epesi <code> <epesi path>` starts another language from Epesi's translation files. Base/Lang/Administrator's Translations tab is Administration → Translations: an administrator's own translations, kept apart from the shipped ones and loaded over them; Epesi's sending of translations to a central server became links to GitHub and the forum. See [AI-shared/Epesi-Laravel-Translations.md](AI-shared/Epesi-Laravel-Translations.md) |
| setup.php, FirstRun | `php artisan epesi:install`, `/setup` | `epesi:install` checks the server, writes `.env` (database, app key) and creates the tables (setup.php). The `/setup` wizard asks for the setup type (profiles in `config/setup.php`, Epesi's `distros.ini`), the administrator and system mail, installs the modules in dependency order, then shows each installed module's own page (FirstRun's `post_install`): **Your company** from CRM/Contacts, **Regional settings** defaults. Modules add pages with `SetupSteps::register()`. Optional demo data and `SETUP_TOKEN` are new |
| Legacy data | `php artisan import:legacy` | Modules can add their own tabs through `ImporterRegistry`; CommonData adds `commondata`, Mail adds `mail` |

Enable the new modules on an existing install with `php artisan module:register Epesi/<Name>`
(this runs the module's migrations).

## Not ported yet

| Epesi module | Suggested approach |
|---|---|
| CRM/GoogleCalendarSync | A Calendar provider plus a scheduled sync job |
| CRM/Filters | Saved per-user table filters (Filament persists filters in the session only) |
| CRM/WhoIsOnline, Tools/WhoIsOnline | Session-table query shown as a dashboard widget |
| Apps/ActivityReport | A report page over `activity_log` |
| Applets/Clock, Applets/MonthView, Applets/RssFeed, Applets/Note | Dashboard widgets |
| Base/Search | Filament global search is on; decide which attributes each resource searches |
| Utils/CurrencyField, Data/TaxRates | Money field type for RecordBrowser |
| Utils/ExportXLS, Base/Print | Filament exporters, printable views |
| CRM/Fax | Probably drop |

### Known gaps in the ported modules

- Attachments: no per-note encryption (Epesi's `crypted`), no download history, and no
  UI yet to attach one note to several records (the data model supports it).
- Watchdog: notifications go to the bell only; there is no email digest (Epesi's cron mailer).
- Watchdog: adding a note to a record does not count as a change to that record.
- Watchdog: no dashboard applet. The bell shows each change, and the Watched page lists the
  changed records.
- Mail: no embedded webmail (Epesi bundled Roundcube). Reading mail stays in the user's own
  mail client, and the CRM archives it.
- Mail: no OAuth sign-in (Gmail and Microsoft 365 need an app password).
- Mail: `import:legacy mail` imports accounts, extra addresses and archived mail (with links,
  threads and attachments), but not per-user Watchdog subscriptions to mails or the
  attachment download history. Set `LEGACY_DATA_DIR` to the legacy `data/` directory, or
  attachments and encrypted passwords are skipped (with a warning).
- Reminders: no snooze (Epesi's "Hold on" for 5 minutes to 24 hours), no per-user
  "allow others to set alerts for me" setting, no reminders for recurring meetings'
  later occurrences, and no quick "remind me" field on the create forms (Epesi's
  `messenger_on` / `messenger_before` on a new meeting); it's set from the tab after saving.
  Times are shown in the app timezone and a fixed `Y-m-d H:i` format, not the user's
  Regional Settings.
- Reminders: `reminders:send` needs `php artisan schedule:run` in cron. E-mail copies use the
  application mailer (`MAIL_*` in `.env`), not a user's Mail-module account.
- Mail: `mail:fetch` needs `php artisan schedule:run` in cron, and certificate checks can be
  turned off for a self-signed IMAP server with `MAIL_IMAP_VALIDATE_CERT=false`.
