<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-shoutbox
 * @license SPL
 */
class myFunctions extends Epesi {
	public function refresh($cl_id) {
		//initialize Epesi
		$this->init($cl_id);
		//get last 50 messages
		$arr = DB::GetAll('SELECT ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id ORDER BY asm.posted_on DESC LIMIT 50');
		//print it out
		foreach($arr as $row) {
			if(!$row['login']) $row['login']='Anonymous';
			print(Base_LangCommon::ts('Apps_Shoutbox','[%s] %s: %s',array($row['posted_on'], $row['login'], $row['message'])).'<br>');
		}
	}
}
?>
