<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-shoutbox
 * @license SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Shoutbox extends Module {
	private $lang;

	public function construct() {
		//initialize lang module
		$this->lang = & $this->init_module('Base/Lang');
	}

	public function body() {
		//if i am admin add "clear shoutbox" actionbar button
		if(Base_AclCommon::i_am_admin())
			Base_ActionBarCommon::add('delete',$this->lang->ht('Clear shoutbox'),$this->create_callback_href(array($this,'delete_all')));

		$this->applet();
	}

	//delete_all callback (on "clear shoutbox" button)
	public function delete_all() {
		DB::Execute('DELETE FROM apps_shoutbox_messages');
	}

	public function applet() {
		Base_ThemeCommon::load_css($this->get_type()); // added by MS
		if(Acl::is_user()) {
			//initialize HTML_QuickForm
			$qf = & $this->init_module('Libs/QuickForm');
			//create text box
			$text = & HTML_QuickForm::createElement('text','post',$this->lang->t('Post'),'id="shoutbox_text"');
			//create submit button
			$submit = & HTML_QuickForm::createElement('submit','button',$this->lang->ht('Submit'), 'id="shoutbox_button"');
			//add it
			$qf->addGroup(array($text,$submit),'post');
			$qf->addGroupRule('post',$this->lang->t('Field required'),'required',null,2);
			$qf->setRequiredNote(null);

			//if submited
			if($qf->validate()) {
				 //get post group
				$msg = $qf->exportValue('post');
				//get msg from post group
				$msg = $msg['post'];
				//get logged user id
				$user_id = Base_UserCommon::get_my_user_id();
				//clear text box and focus it
				eval_js('$(\'shoutbox_text\').value=\'\';focus_by_id(\'shoutbox_text\')');

				//insert to db
				DB::Execute('INSERT INTO apps_shoutbox_messages(message,base_user_login_id) VALUES(%s,%d)',array(htmlspecialchars($msg,ENT_QUOTES,'UTF-8'),$user_id));
			}
			//display form
			$qf->display();
		} else {
			print($this->lang->t('Please log in to post message').'<br>');
		}

		//get last 50 messages
		$arr = DB::GetAll('SELECT asm.id, ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id ORDER BY asm.posted_on DESC LIMIT 50');
		print('<div id=\'shoutbox_board\'></div>');
		Base_ThemeCommon::load_css($this->get_type());

		//if there is displayed shoutbox, call myFunctions->refresh from refresh.php file every 5s
		eval_js_once('shoutbox_refresh = function(){if(!$(\'shoutbox_board\')) return;'.
			'new Ajax.Updater(\'shoutbox_board\',\'modules/Apps/Shoutbox/refresh.php\',{method:\'get\'});'.
			'};setInterval(\'shoutbox_refresh()\',30000)');
		eval_js('shoutbox_refresh()');
	}

	public function caption() {
		return "Shoutbox";
	}
}

?>
