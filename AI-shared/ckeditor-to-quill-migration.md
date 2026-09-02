# Rich-text fields: Quill, not CKEditor

> **Status:** REFERENCE - Quill is the rich-text editor for every field in the app. The full
> migration narrative (scope sweep, plan, deviations, verification) is archived at
> `AI-private/archive/ckeditor-to-quill-migration.md`.

## What to use

`modules/Libs/Quill/` provides the `quill` QuickForm element type. Declare
`Libs_QuillInstall` in your module's `requires()`. The toolbar preset (Basic vs. Advanced)
comes from the user's own `Base_User_SettingsCommon::get(..., 'editor')` setting, passed as
`setQuillProps()`'s third argument — don't hardcode a preset.

**Nothing registers the `'ckeditor'` QuickForm type any more.** `modules/Libs/CKEditor/`
still exists, but only as `CKEditorInstall.php` (unchanged) plus an empty documented
`CKEditorCommon_0.php` shell — deliberately left installed-and-harmless rather than
uninstalled, because `ModuleManager::uninstall()` needs the target's `*Install.php` loadable
and refuses while anything still `requires()` it. See
[deliberate-removals.md](deliberate-removals.md).

`Libs/Quill/frontend.css` is a straight port of CKEditor's, kept so old stored HTML using
CKEditor's Styles classes (`class="Bold"`, `"Title"`, `"Code"`) still renders.

## Three gotchas worth carrying forward

**Quill's format matching is type-strict.** `indent` takes numeric `-1`/`1`; a string
silently no-ops and logs *"quill:toolbar ignoring attaching to nonexistent format"* rather
than erroring.

**Dark is the unscoped default; `[data-bs-theme="light"]` is the override — never the
reverse.** `.app-wrapper` always carries `data-bs-theme="dark"` as a fixed baseline,
independent of `<html>`, which is what the light/dark toggle actually flips. So
`[data-bs-theme="dark"] X` matches **unconditionally** (there is always a dark ancestor) while
`[data-bs-theme="light"] X` matches only in light mode. Gating dark colours behind
`[data-bs-theme="dark"]` looks intuitive and produces a solid-black editor in light mode too.
Every `theme_adminltedark/*.css` file follows the correct convention; grep for the inverted
form if a new dark-mode rule looks backwards.

**Put `load_css()`/`load_js()` in the element class's constructor, not at the top level of a
`Common_0.php`.** A `Common` file's top-level code is not reliably re-run on every request,
so CSS loaded there fires intermittently. The constructor path is reliable, and is how the
JS side always worked.

## Not ported

CKEditor's live in-session "Switch toolbar" button. The Basic/Full *choice* is fully
preserved through the user setting; only the mid-session toggle convenience went away. It was
later restored for Notes specifically.
