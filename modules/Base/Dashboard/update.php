<?php
header("Content-type: text/javascript");

define('JS_OUTPUT',1);
require_once('../../../include.php');
session_write_close(); //don't messup session
Epesi::init();

if(!Acl::is_user()) return;

$mod_path = $_POST['mod_path'];
$default = Module::static_get_module_variable($mod_path,'default');
if($default && !Acl::check('Base_Dashboard','set default dashboard')) {
	Epesi::alert('Permission denied');
	Epesi::send_output();
	exit();
}

if(!$default)
	$user = DB::GetOne('SELECT id FROM user_login WHERE login=%s',array(Acl::get_user()));

parse_str($_POST['data'], $x);
for($i=0; $i<3 && !isset($x['dashboard_applets_'.$i]); $i++);

if($i<3) {
	if($default)
		foreach($x['dashboard_applets_'.$i] as $pos=>$id)
			DB::Execute('UPDATE base_dashboard_default_applets SET pos=%d, col=%d WHERE id=%d',array($pos,$i,$id));
	else
		foreach($x['dashboard_applets_'.$i] as $pos=>$id)
			DB::Execute('UPDATE base_dashboard_applets SET pos=%d, col=%d WHERE id=%d AND user_login_id=%d',array($pos,$i,$id,$user));
}
?>
