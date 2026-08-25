<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// Utils_AttachmentCommon::new_addon()/delete_addon() (called directly by many
// other modules' own Install.php - CRM_Tasks/CRM_PhoneCall/CRM_Contacts/
// CRM_Meeting/Premium_ListManager/Premium_SalesOpportunity, plus
// modules/Base/patches/notes_addons.php) only ever write to the real addon
// registry, recordbrowser_addon - never to utils_attachment_related, the
// table the "Administration: Attachments" grid (added by the
// 20260808_features_configuration.php patch) actually displays. That left the
// grid showing only recordsets an admin explicitly added through the grid
// itself - empty on every install, even though Notes is active on several
// recordsets already. See AI-shared/bug-patterns.md's "An addon *_related
// admin grid only shows..." entry for the full investigation.
//
// Backfill: give every recordset already wired to Utils_Attachment's 'body'
// addon (per recordbrowser_addon) a matching utils_attachment_related row, so
// the admin grid reflects live state. new_record() runs the normal
// RecordBrowser insert path, which re-registers the addon via
// processing_related()/new_addon() - a harmless no-op re-wiring (same table,
// module, func, label) since it's already wired; only its 'pos' among that
// recordset's other addons gets refreshed to the end, same as if an admin had
// just added the row by hand through the grid.
$wired = DB::GetCol('SELECT tab FROM recordbrowser_addon WHERE module=%s AND func=%s', array('Utils_Attachment', 'body'));
$known = DB::GetCol('SELECT f_recordset FROM utils_attachment_related_data_1 WHERE active=1');
foreach (array_diff($wired, $known) as $tab) {
    Utils_RecordBrowserCommon::new_record('utils_attachment_related', array('recordset' => $tab));
}
