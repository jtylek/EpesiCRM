<?php
if(!isset($_POST['date']))
	die('Invalid request');

require_once('../../../include.php');
session_commit();
Epesi::init();

print(Base_RegionalSettingsCommon::time2reg($_POST['date'],false));
?>