<?php
header("Content-type: text/javascript");

require_once('../../../include.php');

if(!Acl::is_user()) return;
$user = DB::GetOne('SELECT id FROM user_login WHERE login=%s',array(Acl::get_user()));

parse_str($_POST['data'], $x);
for($i=0; $i<3 && !isset($x['dashboard_applets_'.$i]); $i++);

if($i<3)
	foreach($x['dashboard_applets_'.$i] as $pos=>$id)
		DB::Execute('UPDATE base_dashboard_applets SET pos=%d, col=%d WHERE id=%d AND user_login_id=%d',array($pos,$i,$id,$user));
?>
