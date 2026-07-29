<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// edited_on_format predates the %U (user) placeholder and the single-<br>
// default; drop any saved per-user overrides so everyone falls back to the
// admin default, and pin the admin default itself to the new template
// (rather than just deleting it) so it's explicit in the DB, not dependent
// on Base_User_SettingsCommon::get_default() re-parsing user_settings() on
// every request.
DB::Execute('DELETE FROM base_user_settings WHERE module=%s AND variable=%s', array('Utils_Attachment', 'edited_on_format'));

$value = serialize('%D<br>%T<br>%U');
$exists = DB::GetOne('SELECT 1 FROM base_user_settings_admin_defaults WHERE module=%s AND variable=%s', array('Utils_Attachment', 'edited_on_format'));
if ($exists) {
    DB::Execute('UPDATE base_user_settings_admin_defaults SET value=%s WHERE module=%s AND variable=%s', array($value, 'Utils_Attachment', 'edited_on_format'));
} else {
    DB::Execute('INSERT INTO base_user_settings_admin_defaults VALUES (%s,%s,%s)', array('Utils_Attachment', 'edited_on_format', $value));
}
