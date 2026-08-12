<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// The Companies browse table's own title column ("Company Name") rendered a
// plain linked label with no tooltip at all - every *other* place a company
// is referenced (Contacts' Company Name field, etc.) already shows the full
// company-card popup via CRM_ContactsCommon::company_format_default(). Reuse
// that instead of the generic Utils_RecordBrowserCommon::display_linked_field_label()
// for consistency. Guarded so a field a user has since customised isn't
// clobbered.
DB::Execute('UPDATE company_callback SET callback=%s WHERE field=%s AND freezed=1 AND callback=%s', array(
    'CRM_ContactsCommon::company_format_default',
    'Company Name',
    'Utils_RecordBrowserCommon::display_linked_field_label',
));
