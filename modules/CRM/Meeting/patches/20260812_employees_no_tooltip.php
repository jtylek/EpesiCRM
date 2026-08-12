<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Employees is a staff-assignment picker (crits already restrict it to this
// company's own employees) - the full contact-card popup (phones/email/
// address) is noise here, unlike a customer-facing contact reference.
// Matches the old param exactly so a field a user has since customised isn't
// clobbered.
DB::Execute('UPDATE crm_meeting_field SET param=%s WHERE field=%s AND param=%s', array(
    'contact::Last Name|First Name;CRM_ContactsCommon::contact_format_no_company_no_tooltip;CRM_MeetingCommon::employees_crits',
    'Employees',
    'contact::Last Name|First Name;CRM_ContactsCommon::contact_format_no_company;CRM_MeetingCommon::employees_crits',
));
