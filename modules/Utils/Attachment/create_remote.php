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
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['file']) || !isset($_REQUEST['path']))
	die('Invalid usage');
$cid = $_REQUEST['cid'];
$path = $_REQUEST['path'];
$file_id = $_REQUEST['file'];
if(isset($_REQUEST['description']))
	$description = $_REQUEST['description'];
else
	$description = '';

define('CID', $cid);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');

$public = Module::static_get_module_variable($path,'public',false);
$protected = Module::static_get_module_variable($path,'protected',false);
$private = Module::static_get_module_variable($path,'private',false);
if(!Acl::is_user())
	die('Permission denied');

$t = time();
$token = md5(Acl::get_user().$t);
DB::Execute('INSERT INTO utils_attachment_download(remote,attach_file_id,created_by,created_on,description,token) VALUES (1,%d,%d,%T,%s,%s)',array($file_id,Acl::get_user(),$t,$description,$token));
$url = get_epesi_url().'modules/Utils/Attachment/get_remote.php?'.http_build_query(array('id'=>DB::Insert_ID('utils_attachment_download','id'),'token'=>$token));
print($url);
?>