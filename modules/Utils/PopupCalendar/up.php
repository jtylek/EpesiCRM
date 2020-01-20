<?php
/**
 * @author Janusz Tylek <j@epe.si> and Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-Utils
 * @subpackage PopupCalendar
 */
if(!isset($_POST['date']))
	die('Invalid request');

define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

print(Base_RegionalSettingsCommon::time2reg($_POST['date'],false,true,false));
?>