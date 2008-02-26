<?php
if(!isset($_POST['date']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();

print(Base_RegionalSettingsCommon::time2reg($_POST['date'],false,true,false));
?>