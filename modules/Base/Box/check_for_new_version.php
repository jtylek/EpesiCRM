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

$registered = Base_EssClientCommon::is_registered();
$ver = Base_LangCommon::ts('Base_Box','version %s',array(EPESI_VERSION));
if (!$registered) {
    print($ver);
    return;
}

$updates = Base_EpesiStoreCommon::is_update_available();

if(!$updates) {
	print(Utils_TooltipCommon::create($ver, Base_LangCommon::ts('Base_Box','You are using most up-to-date version of EPESI.'), false));
	return;
}

if (Base_AclCommon::i_am_sa()) $tooltip = Base_LangCommon::ts('Base_Box','There are updates available for download, click to go to EPESI store.');
else $tooltip = Base_LangCommon::ts('Base_Box','There are updates available for download. Please contact your administrator.');

$message = Utils_TooltipCommon::create(Base_LangCommon::ts('Base_Box','version %s <b>(Update Available!)</b>',array(EPESI_VERSION)), $tooltip, false);

if (Base_AclCommon::i_am_sa()) $message = '<a '.Module::create_href(array('go_to_epesi_store_for_updates'=>true)).'class="version">'.$message.'</a>';

print($message);
?>