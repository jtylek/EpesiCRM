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

//get last 50 messages
$arr = DB::GetAll('SELECT ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id ORDER BY asm.posted_on DESC LIMIT 50');
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
	print(Base_LangCommon::ts('Apps_Shoutbox','<span class="time">[%s]</span> <span class="author">%s</span>: %s',array(Base_RegionalSettingsCommon::time2reg($row['posted_on']), $row['login'], '<span style="color:'.$fcolor.';">'.Utils_BBCodeCommon::parse($row['message']).'</span>')).'<br>');
}

$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
$he->encode();
$he->sendAll();
exit();
?>
