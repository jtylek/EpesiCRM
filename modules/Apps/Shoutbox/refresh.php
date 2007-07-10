<?php
class myFunctions extends Epesi {
	public function refresh($cl_id) {
		$this->init($cl_id);
		$arr = DB::GetAll('SELECT asm.id, ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id ORDER BY asm.posted_on DESC LIMIT 50');
		foreach($arr as $row) {
			if(!$row['login']) $row['login']='Anonymous';
			print(Base_LangCommon::ts('Apps_Shoutbox','[%s] %s: %s',array($row['posted_on'], $row['login'], $row['message'])).'<br>');
		}
	}
}
?>
