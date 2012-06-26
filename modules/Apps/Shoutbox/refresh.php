<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage shoutbox
 */

ob_start();
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Base_AclCommon::is_user())
	exit();

$myid = Base_AclCommon::get_user();
$uid = (isset($_GET['uid']) && is_numeric($_GET['uid']))?$_GET['uid']:null;

//get last 20 messages
$arr = DB::GetAll('SELECT ul.login,ul2.login as to_login, asm.to_user_login_id as to_login_id,asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id LEFT JOIN user_login ul2 ON ul2.id=asm.to_user_login_id WHERE '.($uid?'(base_user_login_id='.$myid.' AND to_user_login_id='.$uid.') OR (base_user_login_id='.$uid.' AND to_user_login_id='.$myid.')':'to_user_login_id is null OR to_user_login_id='.$myid.' OR base_user_login_id='.$myid).' ORDER BY asm.posted_on DESC LIMIT 20');
//print it out
foreach($arr as $row) {
	if(!$row['login']) $row['login']='Anonymous';
	$daydiff = floor((time()-strtotime($row['posted_on']))/86400);
	switch (true) {
		case ($daydiff<1): $fcolor = '#000000'; break;
		case ($daydiff<3): $fcolor = '#444444'; break;
		case ($daydiff<7): $fcolor = '#888888'; break;
		default : $fcolor = '#AAAAAA';
	}
	
	print('<span class="author border_radius_3px dark_blue_gradient">'.$row['login'].($row['to_login']?'&nbsp;->&nbsp;'.$row['to_login']:'').' </span><span class="shoutbox_textbox"style="color:'.$fcolor.';">&nbsp;'.(($row['to_login_id']==$myid && $uid===null)?'<b>':'').Utils_BBCodeCommon::parse($row['message']).(($row['to_login_id']==$myid && $uid===null)?'</b>':'').'</span><span class="time"> ['.Base_RegionalSettingsCommon::time2reg($row['posted_on'],2).']</span><hr/>');
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
