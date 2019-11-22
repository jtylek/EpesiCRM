<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['file']))
	die('Invalid usage');
$cid = $_REQUEST['cid'];
$id = $_REQUEST['file'];
if(isset($_REQUEST['description']))
	$description = $_REQUEST['description'];
else
	$description = '';

define('CID', $cid);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user())
    die('Permission denied');
$file = DB::GetRow('SELECT uaf.attach_id, uaf.original FROM utils_attachment_file uaf WHERE uaf.id=%d',array($id));
$rec = Utils_RecordBrowserCommon::get_record('utils_attachment', $file['attach_id']);
if (!$rec) die('Invalid attachment.');
$access_fields = Utils_RecordBrowserCommon::get_access('utils_attachment', 'view', $rec);
if (!isset($access_fields['note']) || !$access_fields['note'])
    die('Access forbidden');

$t = time()+3600*24*7;
print(Utils_AttachmentCommon::create_remote($id, $description, $t));

?>
