<?php
if (!isset($_GET['cid']) || !isset($_GET['token']))
	die('Invalid request: '.print_r($_GET,true));

define('CID',$_GET['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
if(!isset($_SESSION['indexer_token']) || $_SESSION['indexer_token']!=$_GET['token'])
        die('Invalid token');
ModuleManager::load_modules();

Base_AclCommon::set_sa_user();

Utils_RecordBrowserCommon::indexer(5);