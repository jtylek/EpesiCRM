<?php
if(!isset($_POST['values']) || !isset($_POST['dest_id']) || !isset($_POST['cid']))
	die('alert(\'Invalid request\')');
	
define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
require_once('../../../include.php');
ModuleManager::load_modules();

$_POST['values'] = json_decode($_POST['values']);
$_POST['parameters'] = json_decode($_POST['parameters']);
if (isset($_POST['defaults'])) $_POST['defaults'] = json_decode($_POST['defaults']);
else $_POST['defaults'] = null;
$_REQUEST['values'] = $_GET['values'] = $_POST['values'];
$_REQUEST['defaults'] = $_GET['defaults'] = $_POST['defaults'];
$_REQUEST['parameters'] = $_GET['parameters'] = $_POST['parameters'];

require_once($_SESSION['client']['utils_chainedselect'][$_POST['dest_id']]);
?>