<?php
/**
 *
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage Watchdog
 */
if(!isset($_POST['key']) || !isset($_POST['cid'])  || !is_numeric($_POST['cid']))
	die('alert(\'Invalid request\')');

define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if (!Acl::is_user()) die('Unauthorized access');

list($cat_id, $id) = explode('__',$_POST['key']);
if (!is_numeric($cat_id) || !is_numeric($id)) die('Invalid use');

Utils_WatchdogCommon::notified($cat_id, $id);

?>