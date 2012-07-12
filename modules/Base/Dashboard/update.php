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

$default = $_POST['default_dash'];
if($default && !Base_AdminCommon::get_access('Base_Dashboard')) {
	Epesi::alert('Permission denied');
	Epesi::send_output();
	exit();
}

if(!$default)
	$user = Base_AclCommon::get_user();

$tab = json_decode($_POST['tab']);
parse_str($_POST['data'], $x);

for($i=0; $i<3 && !isset($x['dashboard_applets_'.$tab.'_'.$i]); $i++);

if($i<3) {
	if ($default) {
		$table = 'base_dashboard_default_applets';
		$val = null;
	} else {
		$table = 'base_dashboard_applets';
		$val = $user;
	}
	foreach($x['dashboard_applets_'.$tab.'_'.$i] as $pos=>$id) {
		if (is_numeric($id)) {
			$vals = array($pos,$i,$id);
			if ($val) $vals[] = $val;
			DB::Execute('UPDATE '.$table.' SET pos=%d, col=%d WHERE id=%d'.($val?' AND user_login_id=%d':''),$vals);
		} else {
			$cleanId = str_replace('-','_',$id);
			$vals = array($cleanId,$tab,$i,$pos);
			if ($val) $vals[] = $val;
			DB::Execute('INSERT INTO '.$table.'(module_name,tab,col,pos'.($val?',user_login_id':'').') VALUES (%s,%d,%d,%d'.($val?',%d':'').')',$vals);
			$new_id = DB::Insert_ID('base_dashboard_applets', 'id');
			print('if($("copy_ab_item_new_'.$id.'")){'.
				'$("copy_dashboard_remove_applet_'.$id.'").onclick = function(){if(confirm(\''.__('Delete this applet?').'\'))remove_applet('.$new_id.','.($default?1:0).');};'.
				'Effect.Appear("copy_dashboard_remove_applet_'.$id.'", { duration: 0.3 });'.
				'Effect.BlindUp("copy_dashboard_applet_content_'.$id.'", { duration: 0.3 });'.
				'$("copy_dashboard_remove_applet_'.$id.'").id="dashboard_remove_applet_'.$new_id.'";'.
				'$("copy_dashboard_applet_content_'.$id.'").id="dashboard_applet_content_'.$new_id.'";'.
				'$("copy_ab_item_new_'.$id.'").id = "ab_item_'.$new_id.'";'.
				'dashboard_activate('.$tab.','.($default?1:0).');'.
			'}'
			);
		}
	}
} elseif (isset($x['dashboard_applets_new'])) {
	foreach ($x['dashboard_applets_new'] as $pos=>$id) {
		if (is_numeric($id))
			Base_DashboardCommon::remove_applet($id, $default);
	}
}

?>
