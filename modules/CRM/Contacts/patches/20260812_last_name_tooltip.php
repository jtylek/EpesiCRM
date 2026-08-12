<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// The Contacts browse table's own "Last Name" column rendered a plain
// linked label with no tooltip at all - every *other* place a contact is
// referenced already shows the full contact-card popup via
// CRM_ContactsCommon::contact_get_tooltip(). Reuse that here too, same as
// 20260812_company_name_tooltip.php did for the Companies browse table's
// "Company Name" column. Guarded so a field a user has since customised
// isn't clobbered.
DB::Execute('UPDATE contact_callback SET callback=%s WHERE field=%s AND freezed=1 AND callback=%s', array(
    'CRM_ContactsCommon::contact_lastname_format_default',
    'Last Name',
    'Utils_RecordBrowserCommon::display_linked_field_label',
));
