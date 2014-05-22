<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

if (ModuleManager::is_installed('CRM_Roundcube') >= 0) {
    Utils_RecordBrowserCommon::delete_record_field('rc_mails','Direction');
}
?>
