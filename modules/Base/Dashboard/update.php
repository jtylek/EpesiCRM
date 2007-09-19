<?php
session_id($_POST['session']);
require_once('../../../include.php');
session_start();

if(!Acl::is_user()) return;
$user = DB::GetOne('SELECT id FROM user_login WHERE login=%s',array(Acl::get_user()));

parse_str($_POST['data'], $x);
for($i=0; $i<3 && !isset($x['dashboard_applets_'.$i]); $i++);

foreach($x['dashboard_applets_'.$i] as $pos=>$id)
	DB::Execute('UPDATE base_dashboard_applets SET pos=%d, col=%d WHERE id=%d AND user_login_id=%d',array($pos,$i,$id,$user));
?>
