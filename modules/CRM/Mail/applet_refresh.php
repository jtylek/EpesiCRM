<?php
if (!isset($_POST['acc_id']) || !is_numeric($_POST['acc_id'])) {
    die('Invalid request');
}

define('CID', false);
define('READ_ONLY_SESSION', true);
require_once('../../../include.php');
ModuleManager::load_modules();
if (!Acl::is_user()) {
    die('not logged');
}

try {
    $unseen_data = CRM_MailCommon::get_unread_messages($_POST['acc_id']);
    $unseen = array();
    foreach ($unseen_data as $u) {
        $unseen[] = htmlspecialchars($u['from']) . ': <i>' . htmlspecialchars($u['subject']) . '</i>';
    }
    $ret = Utils_TooltipCommon::create(count($unseen), implode('<br />', $unseen));
    print($ret);} catch (Exception $ex) {
    $message = $ex->getMessage();
    print($message);
}
