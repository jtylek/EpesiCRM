{{--
    Ctrl/Cmd+S already saves on every Create and Edit page — Filament sets
    that as the Save action's default keyBindings itself
    (vendor/filament/filament/src/Resources/Pages/{Create,Edit}Record.php).
    This adds Ctrl/Cmd+E for Cancel, which Filament leaves with no default:
    clicking the header action right after Save, which both pages already
    render as Cancel (CreateRecord.php, EditRecord.php's getHeaderActions()),
    sends it exactly where that button already goes — back, or the record's
    View page — without this needing to know which, or care that Edit's
    Cancel is a plain link while Create's is a click handler. Plain Escape
    was the first attempt, but it's already claimed: the browser itself
    exits full screen (fullscreen-toggle.blade.php) on a bare Escape and
    won't let a page preventDefault that away. Ctrl/Cmd+Escape was the
    second, but Ctrl+Escape opens the Windows Start menu and Alt+Escape
    cycles open windows — both handled by the OS before a page ever sees
    them. Ctrl/Cmd+E has no such reservation: Chrome's own Ctrl+E just
    focuses the address bar, which (unlike closing a tab or opening a
    window) a page is free to preventDefault away.

    A Create page also focuses its first field on load, so typing can start
    right away with no click first; Edit doesn't, since there's usually a
    specific field to change rather than one to start from.

    Unlike every other shortcut here, this one fires even while typing into
    a field: the point is to leave the form from wherever you're typing in
    it, the same way Ctrl/Cmd+S saves from wherever you're typing. It still
    won't fire while a modal (a confirmation, a relation picker…) is open.
--}}
<div
    x-data="{}"
    @if ($focusFirstField)
        x-init="document.getElementById('form')?.querySelector('input:not([type=hidden]), textarea, select')?.focus()"
    @endif
    x-on:keydown.window="
        if (! ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'e')) return;
        if (document.querySelector('.fi-modal.fi-modal-open')) return;

        event.preventDefault();
        document.querySelector('button[type=submit][form=form]')?.nextElementSibling?.click();
    "
></div>
