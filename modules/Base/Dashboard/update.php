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

if(!Acl::is_user()) {
	Epesi::alert('Session expired, logged out - reloading epesi.');
	Epesi::redirect('');
	Epesi::send_output();
	exit();
}

$default = $_POST['default_dash'];
if($default && !Acl::check('Base_Dashboard','set default dashboard')) {
	Epesi::alert('Permission denied');
	Epesi::send_output();
	exit();
}

if(!$default)
	$user = Acl::get_user();

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
exit();
?>
