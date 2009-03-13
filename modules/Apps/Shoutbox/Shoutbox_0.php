<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage shoutbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Shoutbox extends Module {

	public function body() {
		/* Do not delete anything - all messages are kept in history
		// to allow delete by sdmin uncomment below lines
		// if i am admin add "clear shoutbox" actionbar button
		if(Base_AclCommon::i_am_admin())
			Base_ActionBarCommon::add('delete','Clear shoutbox',$this->create_callback_href(array($this,'delete_all')));
		*/
		$th = $this->init_module('Base/Theme');
		$th->assign('header', $this->t('Shoutbox History'));

		$gb = & $this->init_module('Utils/GenericBrowser',null,'shoutbox_history');

		$gb->set_table_columns(array(
						array('name'=>$this->t('User'),'width'=>10),
						array('name'=>$this->t('Message'),'width'=>74),
						array('name'=>$this->t('Date'),'width'=>16)
						));

        // $gb->set_default_order(array($this->t('Date')=>'DESC'));

		$query = 'SELECT base_user_login_id, message, posted_on FROM apps_shoutbox_messages ORDER BY posted_on DESC';
        $query_qty = 'SELECT count(id) FROM apps_shoutbox_messages';

		$ret = $gb->query_order_limit($query, $query_qty);

        if($ret)
			while(($row=$ret->FetchRow())) {
				$ulogin = Base_UserCommon::get_user_login($row['base_user_login_id']);
                $gb->add_row('<span class="author">'.$ulogin.'</span>',Utils_BBCodeCommon::parse($row['message']),Base_RegionalSettingsCommon::time2reg($row['posted_on']));
			}

		$th->assign('messages',$this->get_html_of_module($gb));
		$th->display();
            return true;
	}

	//delete_all callback (on "clear shoutbox" button)
	public function delete_all() {
		DB::Execute('DELETE FROM apps_shoutbox_messages');
	}

	public function applet($conf,$opts) {
		$opts['go'] = true;

		Base_ThemeCommon::load_css($this->get_type()); // added by MS
		if(Acl::is_user()) {
			//initialize HTML_QuickForm
			$qf = & $this->init_module('Libs/QuickForm');
			//create text box
			$text = & HTML_QuickForm::createElement('text','post',$this->t('Post'),'id="shoutbox_text"');
			//create submit button
			$submit = & HTML_QuickForm::createElement('submit','button',$this->ht('Submit'), 'id="shoutbox_button"');
			//add it
			$qf->addGroup(array($text,$submit),'post');
			$qf->addGroupRule('post',$this->t('Field required'),'required',null,2);
			$qf->setRequiredNote(null);

			//if submited
			if($qf->validate()) {
				 //get post group
				$msg = $qf->exportValue('post');
				//get msg from post group
				$msg = Utils_BBCodeCommon::optimize($msg['post']);
				//get logged user id
				$user_id = Acl::get_user();
				//clear text box and focus it
				eval_js('$(\'shoutbox_text\').value=\'\';focus_by_id(\'shoutbox_text\')');

				//insert to db
				DB::Execute('INSERT INTO apps_shoutbox_messages(message,base_user_login_id) VALUES(%s,%d)',array(htmlspecialchars($msg,ENT_QUOTES,'UTF-8'),$user_id));
			}
			//display form
			$qf->display();
		} else {
			print($this->t('Please log in to post message').'<br>');
		}

		print('<div id=\'shoutbox_board\'></div>');
		Base_ThemeCommon::load_css($this->get_type());

		//if shoutbox is diplayed, call myFunctions->refresh from refresh.php file every 5s
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
