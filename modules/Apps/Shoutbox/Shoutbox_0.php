<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Shoutbox extends Module {

	public function body($arg) {
		//initialize lang module
		$l = & $this->init_module('Base/Lang');

		if(Acl::is_user()) {
			//initialize HTML_QuickForm
			$qf = & $this->init_module('Libs/QuickForm');
			//create text box
			$text = & HTML_QuickForm::createElement('text','post',$l->t('Post'),'id="shoutbox_text"');
			//create submit button
			$submit = & HTML_QuickForm::createElement('submit','button',$l->ht('Submit'));
			//add it
			$qf->addGroup(array($text,$submit),'post');
			$qf->addGroupRule('post',$l->t('Field required'),'required',null,2);

			//if submited
			if($qf->validate()) {
				 //get post group
				$msg = $qf->exportValue('post');
				//get msg from post group
				$msg = $msg['post'];
				//get logged user id
				$user_id = Base_UserCommon::get_my_user_id();
				//clear text box and focus it
				eval_js('document.getElementById(\'shoutbox_text\').value=\'\';focus_by_id(\'shoutbox_text\')');
			
				//insert to db
				DB::Execute('INSERT INTO apps_shoutbox_messages(message,base_user_login_id) VALUES(%s,%d)',array($msg,$user_id));
			}
			//display form
			$qf->display();
		} else {
			print($l->t('Please log in to post message').'<br>');
		}

		//get last 50 messages
		$arr = DB::GetAll('SELECT asm.id, ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id ORDER BY asm.posted_on DESC LIMIT 50');
		print('<div id=\'shoutbox_board\' style="height:200px;overflow:auto;text-align:left;border:1px solid black;">');
		foreach($arr as $row) {
			if(!$row['login']) $row['login']='Anonymous';
			$msg = $l->t('[%s] %s: %s',array($row['posted_on'], $row['login'], $row['message']));
			print($msg.'<br>');
		}
		print('</div>');

		//if i am admin add "clear shoutbox" actionbar button
		if(Base_AclCommon::i_am_admin())
			Base_ActionBarCommon::add('delete',$l->ht('Clear shoutbox'),$this->create_callback_href(array($this,'delete_all')));

		//if there is displayed shoutbox, call myFunctions->refresh from refresh.php file every 5s
		eval_js_once('shoutbox_refresh = function(){if(!document.getElementById(\'shoutbox_board\')) return;saja.updateIndicatorText(\''.$l->ht('Refreshing shoutbox').'\');'.
			$GLOBALS['base']->run('refresh(client_id)->shoutbox:innerHTML','modules/Apps/Shoutbox/refresh.php').
			'};setInterval(\'shoutbox_refresh()\',30000)');
	}
	
	//delete_all callback (on "clear shoutbox" button)
	public function delete_all() {
		DB::Execute('DELETE FROM apps_shoutbox_messages');
	}
}

?>