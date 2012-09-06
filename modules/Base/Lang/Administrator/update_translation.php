<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-lang
 * @subpackage timesheet
 */
if(!isset($_POST['original']) || !isset($_POST['new']) || !isset($_POST['cid']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../../include.php');
ModuleManager::load_modules();

if (!Base_AdminCommon::get_access('Base_Lang_Administrator', 'translate'))
	die('Unauthorized access');

$original = $_POST['original'];
$new = $_POST['new'];
$lang = $_SESSION['client']['base_lang_administrator']['currently_translating'];

Base_LangCommon::append_custom($lang, array($original => $new));
Base_Lang_AdministratorCommon::send_translation($lang, $original, $new);

?>