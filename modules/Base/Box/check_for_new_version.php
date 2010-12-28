<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage tooltip
 */
if(!isset($_POST['cid']))
	die('Invalid request'.print_r($_POST,true));

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',1); 
require_once('../../../include.php');
ModuleManager::load_modules();

$lastest_version = @file_get_contents('http://www.epesi.org/installer/config.ini');
if (!$lastest_version) {
	print(Utils_TooltipCommon::create(Base_LangCommon::ts('Base_Box','version %s',array(EPESI_VERSION)).'<b>!</b>', Base_LangCommon::ts('Base_Box','Could not retrieve new version information.'), false));
	return;
}

preg_match('/epesi\-([^\-]*)\-rev/', $lastest_version, $matches);
if ($matches[1]==EPESI_VERSION) {
	print(Utils_TooltipCommon::create(Base_LangCommon::ts('Base_Box','version %s',array(EPESI_VERSION)), Base_LangCommon::ts('Base_Box','You are using most up-to-date version of epesi.'), false));
	return;
}

if (Base_AclCommon::i_am_sa()) $tooltip = Base_LangCommon::ts('Base_Box','There\'s a new version of epesi available for download, click to go to download page.');
else $tooltip = Base_LangCommon::ts('Base_Box','There\'s a new version of epesi available for download. Please contact your administrator.');

$message = Utils_TooltipCommon::create(Base_LangCommon::ts('Base_Box','version %s <b>(Update Available!)</b>',array(EPESI_VERSION)), $tooltip, false);

if (Base_AclCommon::i_am_sa()) $message = '<a href="http://sourceforge.net/projects/epesi/files/" target="_blank" class="version">'.$message.'</a>';

print($message);
?>