<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-apps
 * @subpackage shoutbox
 */

ob_start();
define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Base_AclCommon::is_user())
	exit();

$myid = Base_AclCommon::get_user();
$uid = (isset($_GET['uid']) && is_numeric($_GET['uid']))?$_GET['uid']:null;
$shoutbox_admin = Base_AclCommon::check_permission('Shoutbox Admin');

//get last 20 messages
$arr = DB::GetAll('SELECT * FROM apps_shoutbox_messages WHERE '.($uid?'(base_user_login_id='.$myid.' AND to_user_login_id='.$uid.') OR (base_user_login_id='.$uid.' AND to_user_login_id='.$myid.')':'to_user_login_id is null OR to_user_login_id='.$myid.' OR base_user_login_id='.$myid).' ORDER BY posted_on DESC LIMIT 20');
//print it out
foreach($arr as $row) {
	$daydiff = floor((time()-strtotime($row['posted_on']))/86400);
	switch (true) {
		case ($daydiff<1): $fcolor = '#000000'; break;
		case ($daydiff<3): $fcolor = '#444444'; break;
		case ($daydiff<7): $fcolor = '#888888'; break;
		default : $fcolor = '#AAAAAA';
	}
	$user_label = Apps_ShoutboxCommon::create_write_to_link($row['base_user_login_id']);
	if ($row['to_user_login_id'])
		$user_label .= ' -> '.Apps_ShoutboxCommon::create_write_to_link($row['to_user_login_id']);

	$strongify = $row['to_user_login_id'] == $myid && $uid===null;
	$message = Apps_ShoutboxCommon::format_message($row, $strongify, $shoutbox_admin);
	print('<span class="author border_radius_3px dark_blue_gradient">'.$user_label.'</span><span class="time"> ['.Base_RegionalSettingsCommon::time2reg($row['posted_on'],2).']</span><br/><span class="shoutbox_textbox"style="color:'.$fcolor.';">'.$message.'</span><hr/>');
}

$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
if (MINIFY_ENCODE)
	$he->encode();
$he->sendAll();
exit();
?>
