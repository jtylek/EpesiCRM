<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license Commercial
 * @version 1.0
 * @package epesi-premium
 * @subpackage timesheet
 */

if(!isset($_POST['id']) || !isset($_POST['cid']) || !isset($_POST['status']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

$id = intVal($_POST['id']);
$status = intVal($_POST['status']);

Develop_TranslationsCommon::set($id, $status, true);

print('if($("translation_actions_'.$id.'"))$("translation_actions_'.$id.'").innerHTML="'.($status?'Approved':'Discarded').'";');

?>
