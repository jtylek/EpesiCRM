# Watchdog and notifications — the `Epesi/Watchdog` module

**Status: built.** Covered by `tests/Feature/Modules/WatchdogTest.php`, plus
`TranslationsTest` for notifications in the recipient's language.

Watchdog tells people when **someone else** changes a record they care about. It is the port
of legacy Epesi's `Utils/Watchdog`. A user watches single records, or whole record types, and
learns about changes in two places:

- **the bell** in the top bar: one notification per change, with who changed what;
- **the Watched page**: one row per watched record, with how many changes are new.

Both places share one read state. A change marked read in either place, or seen by opening the
record, is read in both. Most of this document is about how that works, because Filament does
not provide it.

## What the user sees

- **Watch toggle.** Every watchable record's View page has an eye button in its header: "Watch"
  or "Watching". It's Epesi's ActionBar subscription icon.
- **Automatic watching.** The author of a new record watches it from the start, as
  RecordBrowser's `add_record()` did. A user who watches a record type starts watching a record
  of that type the first time they're told about it.
- **Bell notification.** The title is "Task: Call the bank", with the record type and title. The
  body says who did what: "Ann updated: Title, Status.", "Ann created.", "Ann deleted." or
  "Ann restored.". **Open** goes to the record's View page with its History addon open,
  because History is where the changes are.
- **Watched page** (`/watched`, "Watched" in the menu). It lists the records you watch. By
  default it shows only those with new changes; the "Has new changes" filter shows the rest.
  - Columns: Type, Record, Changed by, Changes, the number of new changes, and Last change.
    The last three describe the latest unseen change.
  - The menu item shows the number of records with new changes as a badge.
  - Row and bulk actions: **Mark as read** and **Stop watching**.
  - The header action **Watch record types** picks the record types to watch whole.
- **No dashboard applet.** Epesi had one. Here it would only repeat the Watched page's default
  view and the bell, so it was removed.

## Epesi's version, and how it maps here

| Epesi | Here |
|---|---|
| `utils_watchdog_event` rows, written by `new_event()` from each module | none: every change is already an `activity_log` row (spatie/laravel-activitylog), which the History addon also reads |
| `utils_watchdog_subscription.last_seen_event` | `epesi_watchdog_subscriptions.last_seen_activity_id` |
| `utils_watchdog_category_subscription` | `epesi_watchdog_category_subscriptions`; a category is the record type's morph alias (`task`) |
| `utils_watchdog_category` + a callback per module | `Watchdog::$recordTypes`, extended by `Watchdog::enableFor()` |
| `notified()` when a record is viewed | `Watchdog::markSeen()` when the View page builds its header actions |
| `has_access_to_record()` | `Watchdog::canSee()` |
| Tray notification (`notification()`), **computed from the subscriptions** | Filament's database notifications (the bell), **stored as rows** |
| Dashboard applet with "Mark all as read" | the Watched page |
| "Send e-mail on new events" user setting, sent by cron | not ported |

The row marked in bold is why the read state needs syncing. Epesi's tray was built from the
subscription table on every request, so it could never disagree with the applet. Filament's
bell reads the `notifications` table, where each row has its own `read_at`. The port keeps the
two in step itself (see [One read state](#one-read-state-in-two-places)).

## Data

| Table | Holds |
|---|---|
| `activity_log` | Every change to every record (`subject_type`/`subject_id`, `causer_*`, `event`, changed `properties`). Watchdog writes nothing here. |
| `epesi_watchdog_subscriptions` | One user watching one record, and `last_seen_activity_id`: the last change they have seen. |
| `epesi_watchdog_category_subscriptions` | One user watching every record of a type. |
| `notifications` | Laravel's own table, read by Filament's bell. Each notification's `data` is its rendered text (title, body, icon, actions) plus a `watchdog` key: `{subject_type, subject_id, activity_id}`. |

**Unseen changes** are the `activity_log` rows of the subscription's record that are:
- newer than `last_seen_activity_id`, and
- made by someone other than the subscriber (the causer is null, not a user, or another user).

Two copies of that rule have to be kept in step:
- `Watchdog::unseenActivities()`, for one subscription;
- `Subscription::scopeWithUnseenChanges()`, the same rule as one query for a whole list (the
  Watched page's filter and menu badge).

## How a change reaches people

`WatchdogServiceProvider` hooks the activity model's `created` event, so
`Listeners\NotifySubscribers` runs for every new `activity_log` row:

1. **Find the record.** It loads the record without global scopes, so it can see trashed
   records and records that are private to someone. It stops if the record type isn't in
   `Watchdog::$recordTypes`.
2. **Author watches.** On `created`, the causer subscribes.
3. **Muted?** Inside `Watchdog::withoutNotifications(fn () => …)`, for bulk work, it stops
   here. Subscriptions still record what was missed.
4. **Recipients.** These are the record's subscribers plus the type's watchers
   (`subscribersOf()`), minus the causer. Anyone who can't see the record is dropped
   (`canSee()`). That check runs the model's own query as that user, so
   `HasOwnershipVisibility` applies, and then asks the policy. Watching a whole type never
   leaks a private record.
5. **Subscribe them.** Each recipient is subscribed with this change still unseen
   (`subscribe(..., firstUnseen: $activity->id)`). A type watcher therefore gets a subscription,
   and the record appears on their Watched page, not only in the bell.
6. **Notify.** Recipients are grouped by language, and each group gets one notification
   rendered in that language. The notification is stored as text, so it must not be in the
   causer's language; see [Epesi-Laravel-Translations.md](Epesi-Laravel-Translations.md). Each
   one carries the `watchdog` key. It is sent with `Notification::sendNow()`, not Filament's
   `sendToDatabase()`. Filament's database notification is `ShouldQueue`, and most installs run
   no queue worker, so a queued one would sit in `jobs`.

The wording comes from `NotifySubscribers::describe()`. It names changed fields by their
recordset labels (`Watchdog::fieldLabel()`), so the text reads "Timeless (no specific deadline
time)", not "timeless". The Watched page's Changed by and Changes columns use the same
activity, split in two (`summarize()`).

Stored text never changes afterwards. Changing a sentence in `describe()` affects only
notifications sent after the change.

## One read state in two places

There are two read markers:
- **the Watched page:** a subscription's `last_seen_activity_id`;
- **the bell:** each notification's `read_at`.

Every way of marking something read updates both:

| The user… | Subscription | Bell notifications |
|---|---|---|
| opens the record (View page) | seen up to the latest change | that record's all read |
| clicks Mark as read on the Watched page (row or bulk) | seen up to the latest change | that record's all read |
| clicks Open, or Mark all as read, in the bell | seen up to the notification's change | read |
| dismisses one (✕), or clicks Clear, in the bell | seen up to the notification's change | deleted |

### Rules

- **Per change, not per record.** A bell notification is about one change. Reading it marks
  the record seen **up to that change**, and marks that record's older notifications read too.
  A newer change stays unread in both places. Opening the record or Mark as read on the Watched
  page covers every change so far.
- **Never backwards.** `last_seen_activity_id` only moves forward. Reading an old notification
  after a newer one is harmless.
- **Only unread notifications count.** A notification that is already read was synced when it
  was read. Dismissing or clearing read ones changes nothing.
- **Opening a record marks its notifications read even if you don't watch it.** That covers a
  record you stopped watching after it notified you.

### Where it happens

- **`Watchdog::see()`** is the one place both markers are written. It updates the
  subscription, then the user's unread notifications for that record up to that change.
  - `markSeen($user, $record)` calls it with the record's latest change.
  - `markNotifiedSeen($user, $notifications)` reads the `watchdog` key and calls it once per
    record, with the newest change among the notifications.
  - Notifications without the key are skipped, such as reminders or ones sent before the key
    existed.
- **`Filament\Livewire\DatabaseNotifications`** (in this module) is the bell. It is a subclass
  of Filament's `Filament\Livewire\DatabaseNotifications`. It overrides:
  - `markNotificationAsRead()`, `markAllNotificationsAsRead()`, `removeNotification()` and
    `clearNotifications()`: each calls `markNotifiedSeen()` on the affected unread
    notifications first, then the parent method.
  - This has to be a subclass. Filament marks notifications read with one bulk `update()` and
    deletes them with a bulk `delete()`, which fire no model events, so there is nothing to
    listen to.
  - The `#[On('markedNotificationAsRead')]` and `#[On('notificationClosed')]` attributes are
    repeated on the overrides, because Livewire reads them from the overriding method.
- **The View page** calls `markSeen()` from `WatchdogServiceProvider::headerActions()`. That
  runs when the page builds its header actions, which is also where the eye toggle comes from.
- **The Watched page** calls `markSeen()` from its Mark as read actions.

### Putting the bell in the panel

`WatchdogPlugin::boot()` sets it with `$panel->databaseNotificationsLivewireComponent(...)`.
It isn't set in `register()`, for this reason:

- Reminders also turns the bell on with `databaseNotifications()`.
- That method resets the component to Filament's own.
- Plugins register in `modules` table order, so whichever module registered last would decide.
- `boot()` runs after every plugin's `register()`.

Filament registers the panel's bell with Livewire in `Panel::register()`, before `boot()`. So
`WatchdogServiceProvider` registers the class with Livewire itself, named by its class as
Filament names its own. Without that, Livewire couldn't resolve the component on its next
request.

With Watchdog disabled, the panel gets Filament's own bell back, and Reminders' notifications
work as before.

### Refreshing the other place at once

The bell polls every 60 seconds and the sidebar doesn't poll. So each side tells the other
through Livewire events, instead of waiting:

| Event | Sent by | Received by |
|---|---|---|
| `refresh-sidebar` | the bell, and the Watched page's Mark as read | Filament's sidebar, which re-renders the Watched badge |
| `watchdog-seen` | the bell | the Watched page, which re-renders its table |
| `databaseNotificationsSent` | the Watched page's Mark as read | the bell, which reloads its list (Filament's own "new notifications" event) |

### The JSON lookup

`see()` finds a record's notifications with JSON paths on `notifications.data`:
`where('data->watchdog->subject_id', …)` and `where('data->watchdog->activity_id', '<=', …)`.

- **MySQL.** This becomes `json_unquote(json_extract(...))`, which returns a string. Compared
  with a bound integer, MySQL compares it as a number (`'345' <= 1000` is true). That was
  checked on MySQL directly, because the tests don't cover it.
- **SQLite** (tests). `json_extract` returns the number itself.
- **Speed.** The lookup is limited to one user's unread notifications, so it stays small
  without an index.

## Making a record type watchable

A module whose records should be watchable needs four things:

1. **A morph alias** for the model; every polymorphically used model needs one anyway.
2. **`Watchdog::enableFor('alias')`**, called from the module's service provider. It is the port
   of `Utils_RecordBrowserCommon::enable_watchdog()`.
3. **Activity logging** on the model, so its changes are `activity_log` rows. RecordBrowser
   models already have it.
4. **A resource in the main panel** with a View page. `Watchdog::label()`, `title()`, `url()`
   and `fieldLabel()` all read the resource: its model label, record title, History tab and
   field labels. Without one, the notification falls back to the morph alias and the record id.

The eye toggle appears through `RecordExtensions::headerActions()` on every RecordBrowser View
page. It shows only for types that `Watchdog::watches()`.

## Other modules and the bell

The bell belongs to no one module. Any module can send to it: build a Filament
`Notification`, call `->toDatabase()`, and send it with `notifyNow()` or `sendNow()`. Reminders
does this.

Those notifications have no `watchdog` key. Watchdog's bell passes them straight to Filament's
behaviour: read is only read, and dismissed is only deleted.

A module that wants the same two-way sync for its own state would need its own key and handler.
Today only Watchdog has one.

## Not covered

- **Notifications sent before the `watchdog` key existed.** They can't be matched to a change.
  Reading them in the bell doesn't clear the Watched page, and opening their record doesn't mark
  them read. Clearing the bell once gets rid of them.
- **Mark as unread** (Filament's `markNotificationAsUnread`) does not un-see the change. No
  notification offers that action today.
- **Stop watching** leaves the record's notifications in the bell as they were.
- **E-mail.** There is no e-mail on new changes; Epesi's cron mailer and its user setting are
  not ported.
- **Notes.** Adding a note to a record does not count as a change to it.

## Testing

`tests/Feature/Modules/WatchdogTest.php` covers:
- subscribing, and the author auto-watching;
- notifying others but never the causer;
- only the changed fields being named;
- delivery without a queue worker;
- links to the History tab in any language;
- type watchers never seeing private records, and getting a subscription;
- the Watched page's columns, filter and badge;
- all the read paths: the View page, the Watched page, and the bell's mark read, mark all,
  dismiss and clear, including per-change behaviour;
- the panel rendering Watchdog's bell and not Filament's.

The suite runs on SQLite. The JSON comparison above is MySQL-specific and was checked there by
hand.
