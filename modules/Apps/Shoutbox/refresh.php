<?php
define('SET_SESSION',0);
require_once('../../../include.php');
ModuleManager::load_modules();

//get last 50 messages
$arr = DB::GetAll('SELECT ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id ORDER BY asm.posted_on DESC LIMIT 50');
//print it out
foreach($arr as $row) {
	if(!$row['login']) $row['login']='Anonymous';
	print(Base_LangCommon::ts('Apps_Shoutbox','<span class="time">[%s]</span> <span class="author">%s</span>: %s',array(date('j M H:i',strtotime(Base_RegionalSettingsCommon::time2reg(DB::UnixTimeStamp($row['posted_on']),true,true,true,false))), $row['login'], $row['message'])).'<br>');
}
?>
