# Translations: the interface in several languages

The Laravel port of Epesi's `Base/Lang`. Each user sees the interface in their own
language. English and Polish ship. Any of the 36 languages Epesi had can be started from
Epesi's own translation files. An administrator can change any translation, or fill in a
missing one, on Administration → Translations.

This document covers how it works, how to add strings and languages, how translations are
checked, the Translations page, and the problems found and fixed while building it.

## At a glance

| What | Where |
|---|---|
| Core strings (Polish) | `lang/pl.json` |
| A module's strings | `modules/<Vendor>/<Name>/lang/pl.json` (16 modules have one) |
| Laravel's own messages | `lang/pl/validation.php`, `auth.php`, `passwords.php`, `pagination.php` |
| Words needing their own key | `lang/en/record_labels.php`, `lang/pl/record_labels.php` |
| Languages on offer | `config('app.available_locales')`: `['en' => 'English', 'pl' => 'Polski']` |
| Choosing a language | `App\Support\Locale\Locales`, `App\Http\Middleware\SetLocale` |
| Storing the choice | RegionalSettings module: the `language` column of `epesi_regional_settings` |
| Filament label hooks | `AppServiceProvider::translateAllLabels()`, `App\Filament\Concerns\Translates*Labels` |
| Importing from Epesi | `php artisan lang:import-epesi <code> <epesi path>` (`ImportEpesiTranslations`) |
| The Translations page | Administration → Translations: `App\Filament\Administration\Pages\Translations`, `App\Support\Translations\TranslationCatalog` |
| Custom translations | `storage/app/private/translations/<code>.json` (the `translations` disk), `App\Support\Translations\CustomTranslations` |
| Loading them last | `App\Support\Translations\CustomTranslationLoader`, wrapped around `translation.loader` in `AppServiceProvider::register()` |
| Tests | `tests/Feature/TranslationsTest.php`, `tests/Feature/CustomTranslationsTest.php` |

**Upgrading an existing install:** run `php artisan migrate`. It adds the `language`
column. Until then, the language resolvers fail quietly and everyone gets `APP_LOCALE`.

## Who translates what

- **Developers ship the translations.** Every string in the code has a translation in every
  language on offer, and `TranslationsTest` fails on one that has none (see
  [Checking translations](#checking-translations)). A new string is translated when it is
  added, with an AI assistant, and the result is reviewed like any other change (see
  [Adding a string](#adding-a-string)).
- **Users send in better translations**, as a GitHub pull request against the
  `lang/<code>.json` the string is in, or as a post on the forum. The Translations page links
  to both, and downloads an installation's custom translations as a file to attach.
- **Administrators make their own** on Administration → Translations. They are kept on the
  installation, apart from the shipped files, and loaded last (see
  [Custom translations](#custom-translations-administration--translations)).

## How a string gets translated

The strings are Laravel JSON translations keyed by the English text, the same way Epesi
keyed `$translations['English'] = 'Translated'` in `modules/Base/Lang/lang/<code>.php`.
English needs no file, because a key is its own English text.

- `lang/pl.json`: strings from the core app (`app/`, `config/setup.php`, panel names).
- `modules/<Vendor>/<Name>/lang/pl.json`: strings from a module's own code.
  `ModuleServiceProvider::registerTranslations()` adds each enabled module's `lang/`
  directory with `addJsonPath()`, like Epesi's `Base_LangCommon::build_merge()`.
  `module:package` zips the whole module directory, so a module carries its translations.
- A string used by several modules lives in `lang/pl.json`.
- Filament, Shield and the other packages bring their own Polish.
  The framework's `lang/en` files are always loaded
  (`TranslationServiceProvider` reads both the framework's lang path and `lang/`), so
  creating `lang/` didn't lose them.

### Translated without a `__()` in the code

Filament takes labels as plain strings. These hooks translate them on the way out:

- **`translateLabel()` everywhere.** `AppServiceProvider::translateAllLabels()` registers
  `configureUsing()` on the base classes of schema components, table columns, filters and
  actions. Filament's `configureUsing` also applies to subclasses, so every form field,
  infolist entry, tab, step, fieldset, column, filter and action gets it. A label written
  as `->label('Due date')`, or one Filament derives from the field name, is therefore
  looked up automatically. A schema component without `translateLabel()` is skipped (a
  `method_exists` guard). A section heading is not its label: headings go through `__()`
  by hand.
- **Table model labels.** A table with no label of its own, such as an addon without
  `$modelLabel`, is named after its model, in the current language. A table with no model
  (Store's catalog) is left alone.
- **Static labels.** Filament reads resource, page and relation-manager labels from static
  properties, which can't call a function. Three traits translate them on the way out:
  - `TranslatesResourceLabels` covers the model label, its plural, the title-case forms
    and the navigation label.
  - `TranslatesPageLabels` covers the navigation label and the title.
  - `TranslatesRelationManagerLabels` covers the tab title and the model labels.

  Every resource, page and relation manager here uses one of them; add it to new ones. A
  class that overrides `getTitle()` or `getNavigationLabel()` itself must use `__()` there.
- **Plurals.** The plural of a model label is formed in English and then translated whole:
  "phone call" → "phone calls" → "rozmowy telefoniczne". Pluralising a translation only
  works in English. Title case ("Phone Calls") is applied only in English. Other languages
  capitalise the first letter only.
- **A title-case label of its own wins.** Where "Companies" has a translation (shipped or
  custom), the sidebar, titles and breadcrumbs use it instead of "companies" capitalised.
  That is the string an administrator renames on the Translations page, and the rename
  showed nowhere until this was added.
- **Model labels in lower case.** `lang/pl.json` has "contact": "kontakt", "phone calls":
  "rozmowy telefoniczne" and so on, because Filament puts them inside sentences.
- **Panel names.** `brandName(fn () => __('epesi administration'))`: a closure, because the
  panel is built before the language is known.

### Translated by hand

Everything else goes through `__()` or `trans_choice()`. That means helper texts,
placeholders, tooltips, section headings, notification titles and bodies, modal headings
and descriptions, options and descriptions, validation messages, page titles, the setup
wizard, setup-step labels, and Blade views (the Shoutbox, Mail and Regional settings views,
the message-body iframe title). Rules:

- **The whole sentence is the key, with placeholders.** Write
  `__('Installed :module :version', ['module' => …, 'version' => …])`, never
  `'Installed '.$name`, because word order differs between languages. Other examples:
  `__(':field from', …)` for range filters, `__('Linked to :record', …)`,
  `__('Uninstall :module?', …)` and `__('On :date, :sender wrote:', …)`.
- **Counts use `trans_choice()`.** For example:
  `trans_choice('{0} No messages archived|{1} :count message archived|[2,*] :count messages archived', $n, ['count' => $n])`.
  Polish supplies its own ranges (`{1}`, `[2,4]`, `[5,*]`).
- **HTML is built around the translation.** Keep markup out of the key, or pass it as a
  placeholder: `__('Run :command on the server…', ['command' => '<code>…</code>'])`. Escape
  a translated sentence with `e()` when it goes into an `HtmlString`.
- **Blade inside a PHP string.** The setup wizard's submit buttons are
  `Blade::render('…{{ __(\'Install\') }}…')`. The inner quotes must be escaped.
- **Data is never translated.** That covers record titles, user names, CommonData entries,
  e-mail subjects and bodies.
- **Technical values stay plain strings.** Examples: `STARTTLS`, `SSL/TLS`, `993 / 143`,
  `SHA-256`.
- **Labels an administrator typed** (custom fields, their section names) still go through
  `__()`. They stay as typed unless someone adds a translation.
- Config text such as the setup profiles' `label` and `description` stays English in the
  config file and is translated where it is displayed.

### Ambiguous words

A JSON key has one translation. Where one English word needs two words in another language,
it gets a key of its own in a PHP group file. "Open" is both a record status ("Otwarte") and
a button ("Otwórz"). `RecordStatus::getLabel()` therefore uses
`__('record_labels.status.open')` and its siblings, from `lang/en/record_labels.php` and
`lang/pl/record_labels.php`. `RecordPriority` and `RecordPermission` use plain JSON keys,
because none of their words clash.

Never name a group file after a word that could be a label. `__('Record')` with no JSON
entry for the locale falls back to the group `Record`. Windows file names ignore case, so
that loads `record.php` and returns its whole array, and the label crashes Filament.

## Which language a user gets

`App\Support\Locale\Locales` makes the choice, as Epesi's
`Base_LangCommon::get_lang_code()` and `detect_and_load_language()` did:

1. A signed-in user gets their own language, set under **Settings → Regional settings →
   Language**. The placeholder reads "Same as the system (Polski)".
2. Without one, they get the **system default**. It is set in the setup wizard's Regional
   settings step and stored in the `user_id IS NULL` row of `epesi_regional_settings`.
3. A **guest** (the login and password-reset pages) gets the browser's language when it is
   on offer. It is matched on the primary subtag of `Accept-Language`, so `pl-PL` → `pl`.
4. Otherwise, **`APP_LOCALE`**.

Any value not in `available_locales` is ignored, and the next rule applies.

**Where the choice is stored.** The RegionalSettings module owns the storage. Its service
provider plugs into `Locales::resolveUserLocaleUsing()` and `resolveDefaultLocaleUsing()`.
The resolvers are wrapped in a try/catch, so an install that hasn't run the migration yet
still works. Without the module, everyone gets `APP_LOCALE`.

**Applying it.** `App\Http\Middleware\SetLocale` runs on all four panels (main,
administration, user settings, setup). It is registered with
`->middleware([SetLocale::class], isPersistent: true)`, so Livewire's own update requests
get the language too, not just the first page load. The Regional settings page reloads
itself after the language changes.

**What follows the locale.**
- Carbon's month and day names and `diffForHumans()` are translated.
- The Calendar passes `app()->getLocale()` to FullCalendar through a `data-locale` attribute.
  `resources/js/calendar.js` imports FullCalendar's Polish locale; import another
  language's there when adding one.

**`APP_LOCALE` as configured.** `app()->setLocale()` overwrites `config('app.locale')`. In a
long-running process (a queue worker, the test suite) that value holds whatever the last
request set, so the fallback reads `config('app.configured_locale')`, which is `APP_LOCALE`
and never changed at runtime.

## Text written for someone else

`User` implements `HasLocalePreference` (`preferredLocale()` returns `Locales::forUser()`),
so Laravel renders mail notifications in the recipient's language.

A bell (database) notification is stored as rendered text. It must be rendered in the
recipient's language, not in the language of whoever caused it.
`Locales::using($locale, fn () => …)` switches the locale for the callback and back.
Laravel 12 has no `App::withLocale()`.

- **Watchdog** (`NotifySubscribers`) groups a change's recipients by language and builds
  one notification per language. Its sentences are whole-sentence keys, for example
  `:who updated: :fields.`. Changed fields are named by their recordset labels
  through `Watchdog::fieldLabel()`, so "timeless" becomes "Timeless (no specific deadline
  time)", then "Bez godziny (…)". The record-type prefix uses the resource's translated
  model label ("Zadanie: …").
- **Reminders** render each delivery in its recipient's language: the bell notification,
  the "Starts :date (:relative)" line, and the e-mail's subject and button.

Text stored on a record stays in the language of the user who wrote it, as in Epesi. That
covers Follow-up's tracing notes ("Follow-up after: :record") and the quoted header of a
reply.

## Custom translations: Administration → Translations

The port of the Translations tab of Epesi's `Base/Lang/Administrator`. Like the rest of the
Administration panel, it is for super_admin only.

**The page.**
- One tab per language on offer (`available_locales`), labelled in its own language. A
  badge counts the strings that have no translation in it.
- The table lists every string with its English text, its translation, a status (Not
  translated, Custom, Translated) and its source: `epesi` for the core's `lang/`, otherwise
  the module's name. Not translated comes first, then Custom, then the rest, alphabetically.
  The Status filter shows one kind only, as Epesi's filter did.
- Clicking a row opens Translate: the English text, the shipped translation, and the
  translation to save. Clearing the field removes the custom translation, and so does typing
  the shipped one back. The row's undo button removes it too.
- Translate also lists the word's other forms, which are strings of their own: "Companies"
  names "companies", "Company" and "company". Renaming only "Companies" changes the sidebar
  but leaves "New company" and "No companies" as they were.
- **Add a translation** takes a text the list doesn't have.
- **Download custom translations** saves the language's custom translations as
  `<code>.json`, formatted like `lang/<code>.json`, to attach to a pull request or a forum
  post.
- Epesi sent translations to a central server. That became the GitHub and forum links.
  Importing Epesi's translation files stays a command (`lang:import-epesi`) for developers,
  not a button here.

**What it lists** (`TranslationCatalog`):
- Every key of `lang/*.json` and of each enabled module's `lang/*.json`. A string counts as
  known when any language has it, as for `lang:import-epesi`, so a language without a file
  lists all of them as not translated.
- The lines of the core's own group files, those with an English file in `lang/en/`
  (`record_labels`). They are listed by their English text with the key under it, and the
  key is what gets the custom translation. Laravel's own messages (`validation`, `auth` and
  the rest) are not listed.
- The labels and section names of custom fields (source "Fields"). An administrator types
  them in one language, and `__()` looks them up.
- Every string that has a custom translation.

Filament's own strings are not listed. Adding a translation with their key as the English
text (`filament-actions::edit.single.label`) still replaces one.

**Where they are kept.** `CustomTranslations` writes one `<code>.json` per language to the
`translations` disk (`storage/app/private/translations`), sorted and formatted like
`lang/<code>.json`. A release zip holds only the files git tracks (`epesi:package`), so
unpacking an update leaves `storage/` alone; Epesi kept its custom translations in
`data/Base_Lang/custom/` for the same reason. Only a language code names a file, because the
page's language arrives in the request.

**Loaded last.** `CustomTranslationLoader` wraps Laravel's `translation.loader` and merges
the custom file over the JSON translations once everything else is loaded. Adding the
directory with `addJsonPath()` would not work: `FileLoader` reads the JSON paths (the
modules') before `lang/`, so `lang/<code>.json` would win. `__()` looks every key up in the
JSON translations before any group file, so a custom entry also replaces a group line
(`record_labels.status.open`) or a package's line (`filament-actions::…`). Saving calls
`Translator::setLoaded([])`, so the rest of that request already sees the change.

**Tests.** `TestCase::setUp()` fakes the `translations` disk. Otherwise a developer's own
custom translations would hide a missing translation from `TranslationsTest`.
`CustomTranslationsTest` covers:
- the loading order: over the core's file, a module's file, a group line, and English;
- the file-name guard;
- the list;
- the page's actions.

## Polish

Polish covers every string in the app: about 500 keys across `lang/pl.json` and the module
files. Laravel's validation, auth, password and pagination messages are also translated.
It was built as follows:

1. The crawl test (see [Checking translations](#checking-translations)) listed the strings
   a page load renders. A scan of the source added the ones only modals and notifications
   show.
2. 156 of them matched Epesi's `pl.php`. Each match was reviewed, because Epesi's word was
   sometimes right for its own screen and wrong here:

   | English | Epesi's Polish | Used here |
   |---|---|---|
   | Mail | Ustawienie poczty ("mail settings") | Poczta |
   | Login | Zaloguj (the verb) | Login |
   | Open | Otwarte (status) | Otwórz (button); status via `record_labels.status.open` |
   | View | Widok | Pokaż |
   | Recordset | Related Recordset (untranslated) | Zbiór rekordów |
   | Deactivate | Wyłacz (typo) | Wyłącz |
   | Home City | Miasto zamiszkania (typo) | Miasto (dom) |
   | Notes | Notki | Notatki |

3. The rest was translated by hand.

Each string was then put where it is used: into the module's `lang/pl.json` when all its
uses are in one module, otherwise into `lang/pl.json`.

## Adding a language

1. **Import what Epesi had:**

   ```bash
   php artisan lang:import-epesi de /path/to/epesi          # or the path of de.php itself
   php artisan lang:import-epesi de /path/to/epesi --dry-run
   ```

   The command reads `modules/Base/Lang/lang/de.php`, then the administrator's edits in
   `data/Base_Lang/custom/de.php`, which take precedence.
   - The strings to look up are the ones some language here already has: every key in
     `lang/*.json` and in each module's `lang/*.json`.
   - Each translation is written into the same directory as the string's other languages,
     so a module's strings stay in the module.
   - Existing translations are never overwritten.
   - Exact matches come first, then case-insensitive ones. A case-insensitive match takes
     the case of the string it stands in for ("contacts" from Epesi's "Contacts").
   - For German it finds about 190 of roughly 490 strings.
   - Review the result (see the table above).
2. **Find the gaps** with `TRANSLATIONS_MISSING` (below). Translate them with an AI
   assistant, giving it the file and the strings around each one, and review the result. To
   make the tests check the new language as well, add its code next to `'pl'` in
   `TranslationsTest`.
3. **Add Laravel's messages** if needed: `lang/de/validation.php` and the other framework
   files.
4. **Handle the words that clash.** Add `lang/de/record_labels.php`.
5. **Offer it.** Add the language to `available_locales` in `config/app.php`. The command
   warns if it's missing.
6. **Calendar.** Import FullCalendar's locale in `resources/js/calendar.js` and add it to
   `locales`. Rebuild with `npm run build`.
7. **Filament** ships most languages; check `vendor/filament/*/resources/lang/<code>`.

## Adding a string

- In PHP, use `__('Whole English sentence with :placeholders')`, or just `->label('…')`
  on a component, which is picked up automatically.
- Add the Polish to the module's `lang/pl.json`, or to `lang/pl.json` for core code. Keep
  the files sorted by key, case-insensitively. Translate it with an AI assistant, and review
  the result: Epesi's own wrong words in the table under [Polish](#polish) are the kind of
  mistake to look for.
- A new resource, page or relation manager uses the matching `Translates*Labels` trait.
- Run `php artisan test --filter=TranslationsTest`. It fails on any string without Polish.

## Checking translations

`tests/Feature/TranslationsTest.php`:

- **Every page in Polish.** The test signs in a super admin whose language is Polish and
  loads the demo data. It then opens every page and resource page of the main,
  administration and user-settings panels: list, create, view and edit for the first
  record. It also renders every relation manager and dashboard widget with Livewire. Any
  lookup that misses (`Lang::handleMissingKeysUsing`) fails the test. It ignores:
  - lookups of values that are already Polish, whether Filament's own or ours.
    `translateLabel()` looks those up a second time, and the second lookup is not a gap;
  - the signed-in user's name (the user menu is labelled with it) and a Spatie exception
    message that Shield catches internally;
  - namespaced keys (`filament-panels::…`) and keys without letters.
- **Every string in the code.** This covers what a page load never renders: modals,
  notifications, the setup wizard. The test reads every `__('…')`, `trans_choice('…')`,
  `->label('…')`, `->modalHeading('…')`, `->modalSubmitActionLabel('…')` and
  `Step/Tab/Fieldset::make('…')` literal in `app/`, `modules/` and the Blade views, plus
  the setup profiles in `config/setup.php`. Each must have a Polish entry.
- **Language resolution:** the user's own language, the system default, a language not on
  offer, and the browser language on the login page.
- **Notifications in the recipient's language:** a change made in English reaches a Polish
  user as "Zadanie: …" / "Ann zmienił(a) ten rekord: Tytuł.", and the request keeps its own
  language afterwards.

To write out what is missing as a starting JSON file:

```bash
TRANSLATIONS_MISSING=/tmp/missing.json php artisan test --filter=test_every_page_is_translated
```

**Test-only trap:** Filament's `NavigationManager` is a scoped singleton. A real request
starts without one, but test requests share one application, so the first panel's
navigation would be reused for the rest of the test. That hides untranslated navigation
labels in later panels. The crawl calls `app()->forgetInstance(NavigationManager::class)`
before each panel. Don't call `forgetScopedInstances()` there: it also drops Filament's
plugin registrations.

## Problems found and fixed along the way

- **Tables without a model crashed.** The global table model label called
  `get_model_label(null)` on Store's catalog table. It now returns `null` when a table has
  no model.
- **The default language leaked between requests.** `setLocale()` writes
  `config('app.locale')`, so falling back to it returned the previous request's language.
  The fix is `app.configured_locale` (see above).
- **Wrong words from Epesi.** See the table under [Polish](#polish).
- **Setup with demo data failed on a real install** ("No morph map defined for model
  Company"). The installer registers modules into a process that started with none
  installed. The CRM morph aliases come from those modules' service providers, which
  hadn't run. `Installer::load()` now registers the installed modules' PSR-4 namespaces
  and service providers right after installing them. The test suite can't catch this,
  because it boots every module from its manifest. It turned up in the browser check of
  this work.
- **Unchanged fields reported as changed.** A title-only edit made straight after
  `create()` logged "Title, Status, Priority, Timeless" in the History tab and in the
  Watchdog notification.
  - A record created without `status`, `priority`, `timeless` or `permission` held null
    in memory while the table filled in its defaults.
  - On the next update, the activity log (`logOnlyDirty`) compared null with 0, 1 or false
    and counted them as changed.
  - Task, PhoneCall, Meeting, Company and Contact now declare their columns' defaults in
    `$attributes`.
  - Records loaded from the database were never affected. The case happens when code
    creates and then updates a record in one go, as Follow-up and the demo data do.
    `WatchdogTest::test_an_update_logs_and_reports_only_what_changed` covers both a fresh
    and a re-fetched record.
- **Every "Record" label crashed in English on Windows** (the Watched page, the Notes form).
  The group file was `record.php`, so `__('Record')` returned that file's array (see
  [Ambiguous words](#ambiguous-words)). It was built on Linux, where file names are
  case-sensitive, and first failed on the Windows install. The file is now
  `record_labels.php`.

## Known limits

- **Polish noun endings.** Filament builds some sentences from the model label ("Utwórz
  :label"), which gives "Utwórz firma" instead of "Utwórz firmę". Filament has no case
  forms. Where it matters, a resource can set its own action labels.
- **Shield's navigation group** ("Filament Shield") comes from Shield's configuration.
- **CommonData entries** (countries, groups) are data and stay as entered. Epesi translated
  its CommonData lists; that would need a translation column on `common_data`.
- **Stored text keeps its language.** Follow-up notes and reply quotes stay in the language
  of the user who wrote them.
- **Custom translations live on one server.** They are files under `storage/`. An
  installation on several web servers needs that directory shared between them.
- **A queue worker keeps the translations it has loaded** until it restarts (`php artisan
  queue:restart`). Until then, text it renders doesn't include a custom translation made
  since.
