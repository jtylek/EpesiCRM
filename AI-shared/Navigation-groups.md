# Navigation groups

The sidebar folds related pages under one collapsible heading instead of listing every one
flat, once there's enough of them.

A resource or page joins a named group with Filament's own `$navigationGroup`:

```php
protected static string|UnitEnum|null $navigationGroup = 'CRM';
```

Every CRM recordset (Companies, Contacts, Meetings, Phone Calls, Tasks), the Calendar,
Mailbox, E-mails, Mail accounts, Notes, Shoutbox and Watched all set this to `'CRM'`, so they
collapse under one "CRM" heading instead of each being its own top-level row — `Epesi/Store`'s
`Premium/StoreServer` module already did the same for its own "Store Server" group before this.
Only Dashboard stays outside any group, which is what keeps it pinned above the groups as the
sidebar's first, ungrouped entry (`AlphabeticalNavigationManager` sorts each group's own items
alphabetically, Dashboard's `-2` sort keeps it first within its own bucket).

There is no central place that lists which pages belong to which group — a future ERP,
Premium or custom module just sets `$navigationGroup` to its own group name on its resources
and pages, exactly like the CRM modules or Store Server do, and the sidebar grows a new
heading with no other change. `MainPanelProvider` does not declare `->navigationGroups()`
either: a group left undeclared there still renders (label, collapse chevron), just without an
icon of its own — see the constraint below for why.

**A group can't have its own icon once its items have icons.** Filament throws
("Navigation group [X] has an icon but one or more of its items also have icons") rather than
render both — one or the other, never both, by design. Every CRM item already carries its own
icon (Companies' building, Contacts' person, …), which is more useful for scanning the list
than one shared folder icon would be, so the CRM group (like Store Server) is left with no
icon of its own rather than stripping icons from its items.

**Spacing.** Filament's defaults read as a break between unrelated parts of the app once
there is more than one group: 1.75rem between top-level sections (Dashboard's own bucket,
CRM, Store Server, …), plus another gap between a group's own label and its first item
(the label's `.5rem` padding block, on top of `.25rem` between it and the item list).
The theme's `compact-tables.css` (`resources/css/filament/epesi/`, which covers the sidebar
too, despite the name) overrides all
three down to `.25rem`/`0`/`.375rem` respectively, alongside its existing
`.fi-sidebar-group-items{row-gap:0}` for the items inside a group. The collapse chevron
(`.fi-icon-btn`, nominally 2.25rem square) stays clickable at the tighter padding — it
self-cancels most of its own footprint with a negative margin.
