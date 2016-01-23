<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('UPDATE contact_callback SET callback="Utils_RecordBrowserCommon::display_linked_field_label" WHERE freezed=1 AND (field="First Name" OR field="Last Name")');
DB::Execute('UPDATE company_callback SET callback="Utils_RecordBrowserCommon::display_linked_field_label" WHERE freezed=1 AND field="Company Name"');
