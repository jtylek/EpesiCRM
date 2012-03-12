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
ModuleManager::load_modules();

$public = Module::static_get_module_variable($path,'public',false);
$protected = Module::static_get_module_variable($path,'protected',false);
$private = Module::static_get_module_variable($path,'private',false);
if(!Acl::is_user())
	die('Permission denied');

$t = time()+3600*24*7;
print(Utils_AttachmentCommon::create_remote($file_id, $description, $t));

?>
