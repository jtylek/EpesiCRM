<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Shoutbox extends Module {

	public function body($arg) {
		$l = & $this->init_module('Base/Lang');

		$qf = & $this->init_module('Libs/QuickForm');
		$text = & HTML_QuickForm::createElement('text','post',$l->t('Post'),'id="shoutbox_text"');
		$submit = & HTML_QuickForm::createElement('submit','button',$l->t('Submit'));
		$qf->addGroup(array($text,$submit),'post');
		$qf->addGroupRule('post',$l->t('Field required'),'required',null,2);
		if($qf->validate()) {
			$msg = $qf->exportValue('post');
			$msg = $msg['post'];
			$user_id = Base_UserCommon::get_my_user_id();
			eval_js('document.getElementById(\'shoutbox_text\').value=\'\';focus_by_id(\'shoutbox_text\')');
			if(isset($user_id))
				DB::Execute('INSERT INTO apps_shoutbox_messages(message,base_user_login_id) VALUES(%s,%d)',array($msg,$user_id));
			else
				DB::Execute('INSERT INTO apps_shoutbox_messages(message) VALUES(%s)',array($msg));
		}
		$qf->display();

		$arr = DB::GetAll('SELECT asm.id, ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id ORDER BY asm.posted_on DESC LIMIT 50');
		print('<div id=\'shoutbox\' style="height:200px;overflow:auto;text-align:left;border:1px solid black;">');
		foreach($arr as $row) {
			if(!$row['login']) $row['login']='Anonymous';
			$msg = $l->t('[%s] %s: %s',array($row['posted_on'], $row['login'], $row['message']));
			print($msg.'<br>');
		}
		print('</div>');

		if(Base_AclCommon::i_am_admin())
			Base_ActionBarCommon::add('delete',$l->ht('Clear shoutbox'),$this->create_callback_href(array($this,'delete_all')));

		eval_js_once('shoutbox_refresh = function(){'.
			$GLOBALS['base']->run('refresh(client_id)->shoutbox:innerHTML','modules/Apps/Shoutbox/refresh.php').
			'};setInterval(\'shoutbox_refresh()\',5000)');
		
	}
	
	public function delete_all() {
		DB::Execute('DELETE FROM apps_shoutbox_messages');
	}
}

?>