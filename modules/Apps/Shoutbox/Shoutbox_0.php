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
		// to allow delete by admin uncomment below lines
		// if i am admin add "clear shoutbox" actionbar button
		if(Base_AclCommon::i_am_admin())
			Base_ActionBarCommon::add('delete',__('Clear shoutbox'),$this->create_callback_href(array($this,'delete_all')));
		*/
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		if ($this->is_back()) {
			return Base_BoxCommon::pop_main();
		}
		
		$tb = $this->init_module(Utils_TabbedBrowser::module_name());
		$tb->set_tab(__('Chat'), array($this,'chat'),true,null);
		$tb->set_tab(__('History'), array($this,'history'),null);
		$this->display_module($tb);
    }
	
	public function history() {
		$shoutbox_admin = Base_AclCommon::check_permission('Shoutbox Admin');
		if ($shoutbox_admin) {
			print __('You are shoutbox admin. You can see all communication in the company.');
		}
		$myid = Base_AclCommon::get_user();
		$qf = $this->init_module(Libs_QuickForm::module_name());

		$to_date = & $this->get_module_variable('to_date');
		$from_date = & $this->get_module_variable('from_date');
		$user = & $this->get_module_variable('to',"all");
		$perspective = & $this->get_module_variable('perspective', "my");
		$show = & $this->get_module_variable('show', "all");

		if(ModuleManager::is_installed('CRM_Contacts')>=0) {
       	    $emps = DB::GetAssoc('SELECT l.id,'.DB::ifelse('cd.f_last_name!=\'\'',DB::concat('cd.f_last_name',DB::qstr(' '),'cd.f_first_name'),'l.login').' as name FROM user_login l LEFT JOIN contact_data_1 cd ON (cd.f_login=l.id AND cd.active=1) WHERE l.active=1 ORDER BY name');
	    } else
   		    $emps = DB::GetAssoc('SELECT id,login FROM user_login WHERE active=1 ORDER BY login');
		if ($shoutbox_admin) {
			$values = array('all' => '[' . __('All messages') . ']', 'my'=>'['.__('My view').']')+$emps;
			unset($values[$myid]);
			$qf->addElement('select','perspective',__('Perspective'),$values, array('onchange' => $qf->get_submit_form_js()));
			if ($qf->exportValue('perspective')) {
				$perspective = $qf->exportValue('perspective');
			}
		}
		if ($perspective == 'all') {
			$user = "all";
		} else {
			$qf->addElement('select','user',__('User'),array('all'=>'['.__('All').']')+$emps);
		}
   		$qf->addElement('select','show',__('Show'),array('all'=>__('All'), 'private' => __('Private only'), 'public' => __('Public only')));
   		$qf->addElement('datepicker','from_date',__('From'));
   		$qf->addElement('datepicker','to_date',__('To'));
   		$qf->addElement('text','search',__('Search for'));
		$qf->addElement('submit','submit_button',__('Filter'));

		//if submited
		if($qf->validate()) {
			$perspective = $qf->exportValue('perspective');
			$from_date = $qf->exportValue('from_date');
			$to_date = $qf->exportValue('to_date');
			$user = $qf->exportValue('user');
			$show = $qf->exportValue('show');
			$search_word = $qf->exportValue('search');
		}

		if (!$perspective || !$shoutbox_admin || $perspective == $myid) {
			$perspective = 'my';
		} elseif ($perspective != 'all') {
			$perspective = is_numeric($perspective) ? $perspective : 'my';
		}

		$qf->setDefaults(array('from_date'=>$from_date,'to_date'=>$to_date,'user'=>$user,'perspective'=>$perspective, 'show'=>$show));
		$qf->display_as_row();

		$uid = is_numeric($user)?$user:null;
		$date_where = '';
		if($from_date)
		    $date_where .= 'AND posted_on>='.DB::DBDate($from_date);
		if($to_date)
		    $date_where .= 'AND posted_on<='.DB::DBDate($to_date);
		if (isset($search_word) && $search_word) {
			$search_word = explode(' ',$search_word);
			foreach ($search_word as $word) {
				if ($word) $date_where .= ' AND message '.DB::like().' '.DB::Concat(DB::qstr('%'),DB::qstr(htmlspecialchars($word,ENT_QUOTES,'UTF-8')),DB::qstr('%'));
			}
		}

		$gb = $this->init_module(Utils_GenericBrowser::module_name(),null,'shoutbox_history');

		$gb->set_table_columns(array(
						array('name'=>__('From'),'width'=>10),
						array('name'=>__('To'),'width'=>10),
						array('name'=>__('Message'),'width'=>64),
						array('name'=>__('Date'),'width'=>16)
						));

        // $gb->set_default_order(array(__('Date')=>'DESC'));

		$show_public = ($show == 'all' || $show == 'public');
		$show_private = ($show == 'all' || $show == 'private');
		if ($perspective == 'all') {
			$private_part = $show_private ? '(base_user_login_id is not null AND to_user_login_id is not null)' : 'false';
			$public_part = $show_public ? 'to_user_login_id is null' : 'false';
			$where = "($private_part OR $public_part)";
		} else {
			$perspective_id = $perspective == 'my' ? $myid : $perspective;
			if ($uid) {
				$private_part = $show_private ? '(base_user_login_id='.$perspective_id.' AND to_user_login_id='.$uid.') OR (base_user_login_id='.$uid.' AND to_user_login_id='.$perspective_id.')' : 'false';
				$public_part = $show_public ? '(to_user_login_id is null AND base_user_login_id='.$uid.')' : 'false';
			} else {
				$private_part = $show_private ? '(base_user_login_id='.$perspective_id.' AND to_user_login_id is not null) OR (base_user_login_id is not null AND to_user_login_id='.$perspective_id.')' : 'false';
				$public_part = $show_public ? 'to_user_login_id is null' : 'false';
			}
			$where = "($private_part OR $public_part)";
		}
		$where = "$where $date_where";
		$query = 'SELECT * FROM apps_shoutbox_messages WHERE '.$where.' ORDER BY posted_on DESC';
        $query_qty = 'SELECT count(id) FROM apps_shoutbox_messages WHERE '.$where;

		$ret = $gb->query_order_limit($query, $query_qty);

        if($ret)
			while(($row=$ret->FetchRow())) {
				$gb_row = $gb->get_new_row();
				$ulogin = Base_UserCommon::get_user_label($row['base_user_login_id']);
				if($row['to_user_login_id']!==null)
    				$tologin = Base_UserCommon::get_user_label($row['to_user_login_id']);
    		    else
    		        $tologin = '['.__('All').']';
				$gb_row->add_data(
					'<span class="author">'.$ulogin.'</span>',
					'<span class="author">'.$tologin.'</span>',
					array('value'=>Apps_ShoutboxCommon::format_message($row, false, $shoutbox_admin), 'overflow_box'=>false),
					Base_RegionalSettingsCommon::time2reg($row['posted_on'])
				);
				if (Apps_ShoutboxCommon::can_delete_msg($row)) {
					if (!$row['deleted']) {
						$gb_row->add_action($this->create_callback_href(array($this,'delete_msg'),array($row)), __('Mark message as deleted'), null, 'delete');
					} else {
						$gb_row->add_action($this->create_callback_href(array($this,'restore_msg'),array($row)), __('Restore message'), null, 'active-off');
					}
				}
			}

		$gb->set_inline_display(true);
		$this->display_module($gb);
		return true;
	}

	//delete_all callback (on "clear shoutbox" button)
	public function delete_all() {
		DB::Execute('DELETE FROM apps_shoutbox_messages');
	}

	public function delete_msg($msg)
	{
		if (Apps_ShoutboxCommon::can_delete_msg($msg)) {
			DB::Execute('UPDATE apps_shoutbox_messages SET deleted=1 WHERE id=%d', array($msg['id']));
		} else {
			Base_StatusBarCommon::message(__('You cannot delete this message'));
		}
	}

	public function restore_msg($msg)
	{
		if (Apps_ShoutboxCommon::can_delete_msg($msg)) {
			DB::Execute('UPDATE apps_shoutbox_messages SET deleted=NULL WHERE id=%d', array($msg['id']));
		} else {
			Base_StatusBarCommon::message(__('You cannot restore this message'));
		}
	}

	public function applet($conf, & $opts) {
		$opts['go'] = true;
		$this->chat();
    }
    
    public function chat($big=false,$uid=null) {
		$to = & $this->get_module_variable('to',"all");
		eval_js('shoutbox_uid="'.$to.'"');
		if(Base_AclCommon::is_user()) {
			//initialize HTML_QuickForm
			$qf = $this->init_module(Libs_QuickForm::module_name());

/*            $myid = Base_AclCommon::get_user();
        	if(Base_User_SettingsCommon::get('Apps_Shoutbox','enable_im')) {
        	    $adm = Base_User_SettingsCommon::get_admin('Apps_Shoutbox','enable_im');
        	    if(ModuleManager::is_installed('CRM_Contacts')>=0) {
            	    $emps = DB::GetAssoc('SELECT l.id,IF(cd.f_last_name!=\'\',CONCAT(cd.f_last_name,\' \',cd.f_first_name,\' (\',l.login,\')\'),l.login) as name FROM user_login l LEFT JOIN contact_data_1 cd ON (cd.f_login=l.id AND cd.active=1) LEFT JOIN base_user_settings us ON (us.user_login_id=l.id AND module=\'Apps_Shoutbox\' AND variable=\'enable_im\') WHERE l.active=1 AND l.id!=%d AND (us.value=%s OR us.value is '.($adm?'':'not ').'null) ORDER BY name',array($myid,serialize(1)));			    
		        } else
    		        $emps = DB::GetAssoc('SELECT l.id,l.login FROM user_login l LEFT JOIN base_user_settings us ON (us.user_login_id=l.id AND module=\'Apps_Shoutbox\' AND variable=\'enable_im\') WHERE l.active=1 AND l.id!=%d AND (us.value=%s OR us.value is '.($adm?'':'not ').'null) ORDER BY l.login',array($myid,serialize(1)));
    		} else $emps = array();
    		if(ModuleManager::is_installed('Tools_WhoIsOnline')>=0) {
    		    $online = Tools_WhoIsOnlineCommon::get_ids();
    		    foreach($online as $id) {
    		        if(isset($emps[$id])) 
    		            $emps[$id] = '* '.$emps[$id] ;
    		    }
    		}
       		$qf->addElement('select','to',__('To'),array('all'=>'['.__('All').']')+$emps,array('id'=>'shoutbox_to'.($big?'_big':''),'onChange'=>'shoutbox_uid=this.value;shoutbox_refresh'.($big?'_big':'').'()'));*/
            $myid = Base_AclCommon::get_user();
        	if(Base_User_SettingsCommon::get('Apps_Shoutbox','enable_im') && ModuleManager::is_installed('Tools_WhoIsOnline')>=0) {
        	    $adm = Base_User_SettingsCommon::get_admin('Apps_Shoutbox','enable_im');
    		    $online = Tools_WhoIsOnlineCommon::get_ids();
    		    if($online) {
            	    if(ModuleManager::is_installed('CRM_Contacts')>=0) {
                	    $emps = DB::GetAssoc('SELECT l.id,'.DB::Concat(DB::qstr("* "),DB::ifelse('cd.f_last_name!=\'\'',DB::concat('cd.f_last_name',DB::qstr(' '),'cd.f_first_name',DB::qstr(' ('),'l.login',DB::qstr(')')),'l.login')).' as name FROM user_login l LEFT JOIN contact_data_1 cd ON (cd.f_login=l.id AND cd.active=1) LEFT JOIN base_user_settings us ON (us.user_login_id=l.id AND module=\'Apps_Shoutbox\' AND variable=\'enable_im\') WHERE l.active=1 AND l.id!=%d AND (us.value=%s OR us.value is '.($adm?'':'not ').'null) AND l.id IN ('.implode(',',$online).') ORDER BY name',array($myid,serialize(1)));			    
		            } else
    		            $emps = DB::GetAssoc('SELECT l.id,'.DB::Concat(DB::qstr("* "),'l.login').' FROM user_login l LEFT JOIN base_user_settings us ON (us.user_login_id=l.id AND module=\'Apps_Shoutbox\' AND variable=\'enable_im\') WHERE l.active=1 AND l.id!=%d AND (us.value=%s OR us.value is '.($adm?'':'not ').'null) AND l.id IN ('.implode(',',$online).') ORDER BY l.login',array($myid,serialize(1)));
    		    } else $emps = array();
    		} else $emps = array();
		    $e = $qf->addElement('autoselect','shoutbox_to',__('To'), array('all'=>'['.__('All').']')+$emps, array(array($this->get_type().'Common', 'user_search'),array()),array($this->get_type().'Common', 'user_format'));
		    $e->setAttribute('id','shoutbox_to'.($big?'_big':''));
		    $e->setAttribute('onChange','shoutbox_uid=this.value;shoutbox_refresh'.($big?'_big':'').'()');
        	if(!Base_User_SettingsCommon::get('Apps_Shoutbox','enable_im'))
        	    $qf->freeze(array('shoutbox_to'));
			//create text box
			$qf->addElement($big?'textarea':'textarea','post',__('Message'),'class="border_radius_6px" id="shoutbox_text'.($big?'_big':'').'"');
			$qf->addRule('post',__('Field required'),'required');
			//create submit button
			$qf->addElement('submit','submit_button',__('Send'), 'id="shoutbox_button'.($big?'_big':'').'"');
			//add it
			$qf->setRequiredNote(null);
			$qf->setDefaults(array('shoutbox_to'=>$to));
    		$theme = $this->init_module(Base_Theme::module_name());
		    $qf->assign_theme('form', $theme);

		    //confirm when sending messages to all
		   eval_js("jq('#shoutbox_button, #shoutbox_button_big').click(function() {
      					var submit = true;
		    			if (jq('#shoutbox_to').val() == 'all' && !confirm('".__('Send message to all?')."')) {
         					submit = false;
      					}
		    
		    			return submit;		    			
					});");
		   
			//if submited
			if($qf->validate()) {
				 //get post group
				$msg = $qf->exportValue('post');
				$to = $qf->exportValue('shoutbox_to');
				//get msg from post group
				$msg = Utils_BBCodeCommon::optimize($msg);
				//get logged user id
				$user_id = Base_AclCommon::get_user();
				//clear text box and focus it
				eval_js('$(\'shoutbox_text'.($big?'_big':'').'\').value=\'\';focus_by_id(\'shoutbox_text'.($big?'_big':'').'\');shoutbox_uid="'.$to.'"');

				//insert to db
				DB::Execute('INSERT INTO apps_shoutbox_messages(message,base_user_login_id,to_user_login_id) VALUES(%s,%d,%d)',array(htmlspecialchars($msg,ENT_QUOTES,'UTF-8'),$user_id,is_numeric($to)?$to:null));
			}
		} else {
			print(__('Please log in to post message').'<br>');
			return;
		}

		$theme->assign('board','<div id=\'shoutbox_board'.($big?'_big':'').'\'></div>');
		$theme->assign('header', __('Shoutbox'));
   		$theme->display('chat_form'.($big?'_big':''));

		//if shoutbox is diplayed, call myFunctions->refresh from refresh.php file every 5s
		eval_js_once('shoutbox_refresh'.($big?'_big':'').' = function(){if(!$(\'shoutbox_board'.($big?'_big':'').'\')) return;'.
			'new Ajax.Updater(\'shoutbox_board'.($big?'_big':'').'\',\'modules/Apps/Shoutbox/refresh.php\',{method:\'get\', parameters: { uid: shoutbox_uid }});'.
			'};setInterval(\'shoutbox_refresh'.($big?'_big':'').'()\','.($big?'10000':'30000').')');
		eval_js('shoutbox_refresh'.($big?'_big':'').'()');
	}

	public function caption() {
		return __('Shoutbox');
	}
}

?>
