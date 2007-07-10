<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Shoutbox extends Module {

	public function body($arg) {
		$l = & $this->init_module('Base/Lang');

		$qf = & $this->init_module('Libs/QuickForm');
		$qf->addElement('text','post',$l->t('Post'));
		$qf->addRule('post',$l->t('Field required'),'required');
		$qf->addElement('submit',null,$l->t('Submit'));
		if($qf->validate()) {
			$msg = $qf->exportValue('post');
			$user_id = Base_UserCommon::get_my_user_id();
			if(isset($user_id))
				DB::Execute('INSERT INTO apps_shoutbox_messages(message,base_user_login_id) VALUES(%s,%d)',array($msg,$user_id));
			else
				DB::Execute('INSERT INTO apps_shoutbox_messages(message) VALUES(%s)',array($msg));
		}

		$arr = DB::GetAll('SELECT asm.id, ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id ORDER BY asm.posted_on DESC LIMIT 50');
		print('<div id=\'shoutbox\'>');
		foreach($arr as $row) {
			if(!$row['login']) $row['login']='Anonymous';
			$msg = $l->t('[%s] %s: %s',array($row['posted_on'], $row['login'], $row['message']));
//			if(Base_AclCommon::i_am_admin())
//				$actions = '<a '.$this->create_callback_href(array($this,'delete_post'),$row['id']).'>[x]</a> ';
			print($msg.'<br>');
		}
		print('</div>');

		eval_js_once('shoutbox_refresh = function(){'.
			$GLOBALS['base']->run('refresh(client_id)->shoutbox:innerHTML','modules/Apps/Shoutbox/refresh.php').
			'};setInterval(\'shoutbox_refresh()\',5000)');
		
		$qf->display();
	}
	
	public function delete_post($id) {
		DB::Execute('DELETE FROM apps_shoutbox_messages WHERE id=%d',array($id));
	}
}

?>