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

//get last 20 messages
$arr = DB::GetAll('SELECT ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id ORDER BY asm.posted_on DESC LIMIT 20');
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
	
	
	print('<span class="author">'.$row['login'].' </span><span style="color:'.$fcolor.';">&nbsp;'.Utils_BBCodeCommon::parse($row['message']).'</span><span class="time"> ['.Base_RegionalSettingsCommon::time2reg($row['posted_on'],2).']</span><hr/>');
}

$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
$he->encode();
$he->sendAll();
exit();
?>
