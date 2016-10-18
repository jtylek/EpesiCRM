<?php
if (!isset($_POST['acc_id']) || !is_numeric($_POST['acc_id']) || !isset($_POST['p']) || !is_numeric($_POST['p'])|| !isset($_POST['ipath']) || !isset($_POST['l'])) {
    die('Invalid request');
}

define('CID', false);
define('READ_ONLY_SESSION', true);
require_once('../../../include.php');
ModuleManager::load_modules();
if (!Acl::is_user()) {
    die('not logged');
}

function sort_mails($a,$b) {
    return $b->getDate()-$a->getDate();
}

$account_id = $_POST['acc_id'];
$ipath = is_string($_POST['ipath'])?array($_POST['ipath']):$_POST['ipath'];
$period = $_POST['p'];
$link = $_POST['l'];
$cache_validity_in_minutes = 3;

try {
    $return = null;
    $rec = Utils_RecordBrowserCommon::get_record('rc_accounts', $account_id);
    if ($rec['epesi_user'] != Acl::get_user()) {
        throw new Exception('Invalid account id');
    }

    $folders = CRM_MailCommon::get_folders($rec);
    $folders_map = array();
    foreach($folders as $folder) $folders_map[md5($folder)] = $folder;

    $mailbox = CRM_MailCommon::get_connection($rec);
    $server_string = $mailbox->getServerString();
    $mailbox->setOptions(OP_READONLY);
    $return = array();
    foreach($ipath as $path) {
        if(!isset($folders_map[$path])) continue;
        $cache_key = 'crm_mailarchive_'.md5($rec['server'] . ' # ' . $rec['login'] . ' # ' . $rec['password'].'#'.$path);
        $archive_messages = Cache::get($cache_key);
        if ($archive_messages) {
            $return[$folders_map[$path]] = $archive_messages;
            continue;
        }
        if(!$mailbox->setMailbox(mb_convert_encoding($folders_map[$path], "UTF7-IMAP","UTF-8"))) continue;
        $return[$folders_map[$path]] = $mailbox->search('SINCE "'.date('d M Y',strtotime('-'.$period.' days')).'"',10);
        if($return[$folders_map[$path]]) usort($return[$folders_map[$path]],'sort_mails');
        Cache::set($cache_key, $return[$folders_map[$path]],$cache_validity_in_minutes*60);
    }

    if($return) {
        //$id = md5($account_id.serialize($ipath));
        //print('<div id="mail_archive_accordion_'.$id.'">');
        foreach($return as $folder=>$mails) {
            if(!$mails) continue;
            print('<h3>'.$rec['account_name'].': '.$folder.'</h3><div><ul>');
            foreach($mails as $mail) {
                print('<li>'.Base_RegionalSettingsCommon::time2reg($mail->getDate()).'<br/><strong><a '.str_replace(array('__MESSAGE_ID__','__ACCOUNT_ID__','__FOLDER__'),array($mail->getUid(),$account_id,$folder),$link).'>'.CRM_MailCommon::decode_mime_header($mail->getSubject()).'</a></strong><br/>'.__('From').': '.htmlentities(imap_utf8($mail->getAddresses('from',1))).'<br/>'.__('To').': '.htmlentities(imap_utf8($mail->getAddresses('to',1))).'</li>');
            }
            print('</ul></div>');
        }
        //print('</div><script>jq("#mail_archive_accordion_'.$id.'").accordion();</script>');
    } else {
        print('<h3>'.$rec['account_name'].': '.$folder.'</h3><div><ul>'.__('No messages to display.'));
    }
} catch (Exception $ex) {
    $message = $ex->getMessage();
    print($message);
}
