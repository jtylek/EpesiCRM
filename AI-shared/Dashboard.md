# Dashboard and applets

The port of Epesi's `Base/Dashboard`. Each user has their own dashboard: tabs, each holding
applets in three columns. Epesi called these widgets applets, and this port keeps the name.

## What a user can do

- **Drag an applet** by its title bar, within a column or into another one.
- **Add applet** (a header button) lists every applet the user may see, by name, with a
  description line under each. Epesi called that line `applet_info()`. The same applet can be
  added more than once, for example two Tasks applets with different statuses. An applet
  with settings opens them as soon as it is added, as in Epesi.
- **The gear** on an applet's title bar opens its settings: the applet's own, and which tab it
  is on. The applet is removed from there too.
- **Tabs** (a header button) opens one form to add, rename, reorder and delete tabs.
  Deleting a tab deletes its applets. The tab switcher sits on the header line, between the
  breadcrumb and the buttons (`Dashboard::getHeader()`, `filament.pages.dashboard-header`), and
  shows only when there is more than one tab. The page remembers the last tab for the session,
  as Epesi did.

The first visit creates the default dashboard from `config('dashboard.default')`
(`config/dashboard.php`), which stands in for Epesi's administrator-made default dashboard:

| Tab | Left | Middle | Right |
|---|---|---|---|
| Main | Shoutbox | Priority list | Mail, My reminders |
| Agenda | Agenda | Tasks | Phone Calls |

An applet whose module is disabled or not installed is left out, and a tab left with no
applets is skipped. If nothing is left at all, the user gets an empty "Dashboard" tab. An
applet the user may not see yet (Mail, before the user has a mail account) is placed all the
same and stays hidden until they can see it. An applet that isn't in the config is only added
with **Add applet**. Changing the config doesn't touch dashboards that already exist.

Everything is per user: the `dashboard_tabs` and `dashboard_applets` tables (one row per
applet, with its column, position and settings as JSON). An applet whose widget class is gone
(its module disabled or removed) is skipped, not deleted. In demo mode everyone on a demo
account shares the same dashboard, and the nightly reset restores it, as it does other
per-user settings.

## Writing an applet

An applet is a Filament widget that implements `App\Filament\Dashboard\Applet` and uses
`App\Filament\Dashboard\IsApplet`. A panel widget without them never shows on the Dashboard.
A module registers its widget in its plugin (`$panel->widgets([...])`), as the Tasks, Phone
Calls, Reminders, Shoutbox and Mail modules do. The Agenda is core
(`app/Filament/Widgets/AgendaWidget.php`).

| Method | Epesi | What |
|---|---|---|
| `getAppletCaption()` | `applet_caption()` | its name in Add applet and on its settings form |
| `getAppletDescription()` | `applet_info()` | the line under the name in Add applet |
| `getAppletSettingsSchema()` | `applet_settings()` | form fields, each named after the setting it holds |
| `getAppletSettingsDefaults()` | the fields' `default` | each setting's value until the user saves one |
| `canView()` | `applet_caption()` returning nothing | whether this user may add it and see it |

`IsApplet` provides the rest:

- `$appletId` and `$appletSettings`: the Dashboard mounts the widget with them. Both are
  `#[Locked]`. They are `null` and `[]` anywhere else, for example in a test.
- `appletSetting($name)`: the saved value, or the default.
- The gear. A table applet adds `$this->configureAppletAction()` to its `headerActions()`.
  A section applet includes `filament.dashboard.configure-applet` in the section's
  `afterHeader` slot. Either one sends a `configure-applet` event, and the Dashboard page
  opens the settings form (`Dashboard::configureAppletAction()`).

Saving settings changes the applet's Livewire key (it carries a hash of the settings), so the
widget is mounted again with the new settings. It doesn't need to watch for changes.

A section applet with Filament actions of its own (HasActions, as the Agenda's **+**) must
put `<x-filament-actions::modals />` in its view. A table applet gets that from its table and
a page from its layout, but a plain widget has neither: without it the action mounts and no
modal opens.

The drag handle is the title bar: `.fi-section-header` of a section widget, or
`.fi-ta-header` of a table widget. An applet should have one of the two.

## The applets

| Applet | Epesi | Settings |
|---|---|---|
| Agenda | `CRM_Calendar` applet | how many days from today; which Calendar providers to show. It lists only your own events (you are an employee or customer on them) that aren't closed or canceled. For that it calls each `CalendarEventProvider::calendarEvents()` with `mine: true` and skips a `CalendarEvent` marked `finished` |
| Tasks | `CRM_Tasks` applet | an additional title ("Tasks - Sales"); which statuses (Open, In progress, On hold by default); assigned to me / where I am a contact / either / all I can see |
| Phone Calls | `CRM_PhoneCall` applet | missed calls, today's calls, and how far ahead (no, tomorrow, 2 days, a week, all). It lists only calls you are assigned to, and leaves out On hold, Closed and Canceled |
| My reminders, Shoutbox, Mail | Messenger, Shoutbox, CRM_Mail applets | none |

The Tasks and Phone Calls applets tint a high-priority row, as Epesi did. Each also has a
**+** button that opens the record's create page and a **Fullscreen** button that opens its
list. The Agenda's **+** is the Calendar's "New event" type picker
(`App\Filament\Actions\NewCalendarEventAction`, shared with the Calendar page), opening the
chosen type's create page at the next full hour. Its **Fullscreen** opens the Calendar.

## Not ported

- Applet colours (Epesi's per-applet colour and the user's default colour).
- An administrator's default dashboard (Administration → Default dashboard, and the
  "Dashboard - manage applets" permission, without which a user got that fixed dashboard).
- The collapse toggle on each applet.
- The Tasks applet's "Advanced Filter" (a crits builder), and the RecordBrowser applet action
  checkboxes (Info / View / Edit / Delete / View edit history). A row opens the record's View
  page.

## Tests

- `tests/Feature/DashboardTest.php`: the first visit, dragging, per-user dashboards, adding an
  applet twice, saving settings and reaching the widget, removing an applet, and tabs (add,
  rename, reorder, move an applet between tabs, delete with its applets).
- `tests/Feature/DashboardAppletsTest.php`: what the Tasks, Phone Calls and Agenda applets
  list under each setting.
