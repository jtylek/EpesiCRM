<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */

if(!isset($_REQUEST['cid']) || !isset($_REQUEST['data']))
	die('Invalid usage');

define('CID', $_REQUEST['cid']);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user())
	die('Permission denied');

$targetDir = Utils_AttachmentCommon::get_temp_dir();
Utils_AttachmentCommon::cleanup_paste_temp();

DB::Execute('INSERT INTO utils_attachment_clipboard (created_by) VALUES (%d)', array(Acl::get_user()));
$id = DB::Insert_ID('utils_attachment_clipboard', 'id');

$filename = 'clipboard'.'_'.$id;
$f_filename = $targetDir.'/'.$filename;

DB::Execute('UPDATE utils_attachment_clipboard SET filename=%s WHERE id=%d', array($f_filename, $id));

$data = explode(',', $_REQUEST['data']);
if (!isset($data[1])) die('Invalid file');

file_put_contents($f_filename, base64_decode($data[1]));

die(json_encode(array('id'=>$id, 'name'=>__('clipboard').'.png')));

?>
