<?php
/**
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage dashboard
 */
header("Content-type: text/javascript");

define('JS_OUTPUT',1);
define('CID',false); //don't load user session
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');

ModuleManager::load_modules();

if(!Base_AclCommon::is_user()) {
	Epesi::alert('Session expired, logged out - reloading epesi.');
	Epesi::redirect('');
	Epesi::send_output();
	exit();
}

$default = isset($_POST['default_dash']) && $_POST['default_dash'];
if(($default && !Base_AdminCommon::get_access('Base_Dashboard'))
    || !isset($_POST['col']) || !isset($_POST['data'])) {
	Epesi::alert('Permission denied');
	Epesi::send_output();
	exit();
}

if(!$default)
	$user = Base_AclCommon::get_user();

$tab = json_decode($_POST['tab']);
parse_str($_POST['data'], $x);

if(!isset($x['ab_item'])) exit();

if(is_numeric($_POST['col']) && $_POST['col']<3 && $_POST['col']>=0) {
	if ($default) {
		$table = 'base_dashboard_default_applets';
		$val = null;
	} else {
		$table = 'base_dashboard_applets';
		$val = $user;
	}
	foreach($x['ab_item'] as $pos=>$id) {
		if (is_numeric($id)) {
			$vals = array($pos,$_POST['col'],$id);
			if ($val) $vals[] = $val;
			DB::Execute('UPDATE '.$table.' SET pos=%d, col=%d WHERE id=%d'.($val?' AND user_login_id=%d':''),$vals);
		} elseif(strpos($id,'new_')===0) {
            $id = substr($id,4);
			$cleanId = str_replace('-','_',$id);
			$vals = array($cleanId,$tab,$_POST['col'],$pos);
			if ($val) $vals[] = $val;
			DB::Execute('INSERT INTO '.$table.'(module_name,tab,col,pos'.($val?',user_login_id':'').') VALUES (%s,%d,%d,%d'.($val?',%d':'').')',$vals);
			$new_id = DB::Insert_ID('base_dashboard_applets', 'id');
			print('if(jq("#copy_ab_item_new_'.$id.'").length>0){'.
				'jq("#copy_dashboard_remove_applet_'.$id.'").click(function(){if(confirm(\''.__('Delete this applet?').'\'))remove_applet('.$new_id.','.($default?1:0).');})'.
				'.show("fade",300);'.
				'jq("#copy_dashboard_applet_content_'.$id.'").hide("blind",300);'.
				'jq("#copy_dashboard_remove_applet_'.$id.'").attr("id","dashboard_remove_applet_'.$new_id.'");'.
				'jq("#copy_dashboard_applet_content_'.$id.'").attr("id","dashboard_applet_content_'.$new_id.'");'.
				'jq("#copy_ab_item_new_'.$id.'").attr("id","ab_item_'.$new_id.'");'.
//				'dashboard_activate('.$tab.','.($default?1:0).');'.
			'}'
			);
		}
	}
} elseif ($_POST['col']=='new') {
	foreach ($x['ab_item'] as $pos=>$id) {
		if (is_numeric($id))
			Base_DashboardCommon::remove_applet($id, $default);
	}
}

?>
