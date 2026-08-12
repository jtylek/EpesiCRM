<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// index_record() now skips fields whose id is created_on/created_by/edited_on/
// edited_by (e.g. Utils_Attachment's 'Edited on', which embeds the editor's -
// or, if never edited, the creator's - username via the admin-configurable
// 'edited_on_format' setting). Existing installs already have those values
// baked into recordbrowser_search_index, so a keyword search for someone's
// name still surfaces every record they merely created/edited until the
// index is rebuilt. Clear + let the indexer cron/JS trigger repopulate it.

$checkpoint = Patch::checkpoint('recordset');
$processed = $checkpoint->get('processed', array());
foreach (Utils_RecordBrowserCommon::list_installed_recordsets() as $tab => $caption) {
    if (isset($processed[$tab])) {
        continue;
    }
    Patch::require_time(5);
    Utils_RecordBrowserCommon::clear_search_index($tab);
    $processed[$tab] = true;
    $checkpoint->set('processed', $processed);
}
