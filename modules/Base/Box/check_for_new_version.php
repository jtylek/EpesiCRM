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
$ver = __('version %s',array(EPESI_VERSION));
if (!$registered) {
	print($ver);
	return;
}

$updates = Base_EpesiStoreCommon::is_update_available();

if(!$updates) {
	print(Utils_TooltipCommon::create($ver, __('You are using most up-to-date version of EPESI.'), false));
	return;
}

if (Base_AclCommon::i_am_sa()) $tooltip = __('There are updates available for download, click to go to EPESI store.');
else $tooltip = __('There are updates available for download. Please contact your administrator.');

$message = Utils_TooltipCommon::create(__('version %s',array(EPESI_VERSION)).'<br/><b>('.__('Update Available!').')</b>', $tooltip, false);

if (Base_AclCommon::i_am_sa()) $message = '<a '.Module::create_href(array('go_to_epesi_store_for_updates'=>true)).'class="version">'.$message.'</a>';

print($message);
?>