<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
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
		$tb->set_tab(__('Chat'), $this->chat(...),true,null);
		$tb->set_tab(__('History'), $this->history(...),null);
		$this->display_module($tb);
    }
	
	public function history() {
		Base_ThemeCommon::load_css($this->get_type());
		$shoutbox_admin = Base_AclCommon::check_permission('Shoutbox Admin');
		$myid = Base_AclCommon::get_user();
		$qf = $this->init_module(Libs_QuickForm::module_name());
		if ($shoutbox_admin) {
			// Rendered as a QuickForm header so it lands inside the filters
			// card (theme_adminltedark/row.tpl's .epesi-qf-header) instead of
			// floating above it; wrapped smaller/muted so it reads as a note,
			// not a heading.
			$qf->addElement('header', null, '<small class="text-muted fw-normal">'.__('You are shoutbox admin. You can see all communication in the company.').'</small>');
		}

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
			if ($qf->elementExists('perspective')) {
				$perspective = $qf->exportValue('perspective');
			}
			$from_date = $qf->exportValue('from_date');
			$to_date = $qf->exportValue('to_date');
			if ($qf->elementExists('user')) {
				$user = $qf->exportValue('user');
			}
			$show = $qf->exportValue('show');
			$search_word = $qf->exportValue('search');
		}

		if (!$perspective || !$shoutbox_admin || $perspective == $myid) {
			$perspective = 'my';
		} elseif ($perspective != 'all') {
			$perspective = is_numeric($perspective) ? $perspective : 'my';
		}

		$qf->setDefaults(array('from_date'=>$from_date,'to_date'=>$to_date,'user'=>$user,'perspective'=>$perspective, 'show'=>$show));

		// One card around the filters toggle/form AND the message list below
		// (closed after display_module($gb) further down) so History reads as
		// a single unit, same as Chat's own compose+board card - GenericBrowser
		// still renders its own nested ".epesi-gb.card" inside here, stripped
		// down to a borderless/shadowless/marginless shell by
		// theme_adminltedark/chat_form.css's own ".epesi-shoutbox-history"
		// rules so it merges into this outer card instead of nesting visibly.
		print '<div class="card epesi-shoutbox-card mb-3"><div class="card-body">';

		// Bootstrap's own collapse component (native to adminlte.min.js, see
		// CLAUDE.md's "prefer native attributes over hand-rolled listeners")
		// toggles the card; the label swap is pure CSS keyed off the
		// aria-expanded attribute Bootstrap already maintains on the button.
		$filters_id = 'shoutbox_filters_'.md5($this->get_path());
		print '<button type="button" class="btn btn-primary btn-sm mb-2 shoutbox-filters-toggle" data-bs-toggle="collapse" data-bs-target="#'.$filters_id.'" aria-expanded="false" aria-controls="'.$filters_id.'">'
			.'<span class="filters-toggle-hide">'.__('Hide filters').'</span>'
			.'<span class="filters-toggle-show">'.__('Show filters').'</span>'
			.'</button>';
		print '<div class="collapse" id="'.$filters_id.'">';
		$qf->display_as_row();
		print '</div>';

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

		// One combined column per row, styled to look like the Chat tab's own
		// #shoutbox_board message list (refresh.php) rather than a data grid -
		// GenericBrowser is kept only for its query/pagination machinery (see
		// theme_adminltedark/chat_form.css's ".epesi-shoutbox-history" rules
		// for the header-hiding/row styling that makes this read as a plain
		// list instead of a table).
		$gb->set_prefix('<div class="epesi-shoutbox-history">');
		$gb->set_postfix('</div>');

		$gb->set_table_columns(array(
						array('name'=>'','width'=>100)
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

				// Same sender/recipient badge shape as refresh.php's Chat feed -
				// one combined pill, "-> recipient" only for a private message,
				// omitted entirely for a public/all one (not a separate "[All]"
				// column like the old table layout showed). nolink=true (unlike
				// the old table layout's plain get_user_label() call) - per
				// request, drops CRM_Contacts' own hover-tooltip+click-through
				// link that get_user_label() bundles together with no way to
				// keep one without the other; Chat's own badge (refresh.php's
				// create_write_to_link()) already passes nolink=true for the
				// same reason, just replacing the dropped link with its own
				// "click name to add as chat recipient" one instead.
				$user_label = Base_UserCommon::get_user_label($row['base_user_login_id'], true);
				if ($row['to_user_login_id']!==null) {
					$user_label .= ' -> '.Base_UserCommon::get_user_label($row['to_user_login_id'], true);
				}

				$action_html = '';
				if (Apps_ShoutboxCommon::can_delete_msg($row)) {
					if (!$row['deleted']) {
						$action_html = '<a class="shoutbox-history-action" '.Utils_TooltipCommon::open_tag_attrs(__('Mark message as deleted')).' '.$this->create_callback_href($this->delete_msg(...),array($row)).'><i class="bi bi-trash"></i></a>';
					} else {
						$action_html = '<a class="shoutbox-history-action" '.Utils_TooltipCommon::open_tag_attrs(__('Restore message')).' '.$this->create_callback_href($this->restore_msg(...),array($row)).'><i class="bi bi-arrow-counterclockwise"></i></a>';
					}
				}

				$gb_row->add_data(array(
					'value'=>'<span class="author border_radius_3px dark_blue_gradient">'.$user_label.'</span>'
						.'<span class="time"> ['.Base_RegionalSettingsCommon::time2reg($row['posted_on']).']</span>'
						.$action_html
						.'<br/><span class="shoutbox_textbox">'.Apps_ShoutboxCommon::format_message($row, false, $shoutbox_admin).'</span>',
					'overflow_box'=>false,
				));
			}

		$gb->set_inline_display(true);
		$this->display_module($gb);

		print '</div></div>';
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
		// The dashboard applet card is the only place Shoutbox actually shows
		// a title bar (the "big" Chat-tab form drops its own header - see
		// chat_form_big.tpl's comment - relying on the tab strip instead), so
		// this is the one spot to add a help icon to it. Bootstrap-modal
		// only (data-bs-toggle, no custom JS) - gated to adminlte like the
		// "send to all" confirm modal in chat(), since that's the only theme
		// bootstrap.js loads for.
		if (Base_ThemeCommon::is_adminlte_family()) {
			$opts['actions'][] = '<a href="#" data-bs-toggle="modal" data-bs-target="#Apps_Shoutbox__bbcode_help_modal" '.Utils_TooltipCommon::open_tag_attrs(__('Formatting help')).'><i class="bi bi-question-circle"></i></a>';
		}
		$this->chat();
    }

	/**
	 * Every tag currently registered in utils_bbcode (Utils_BBCodeCommon::
	 * get_registered_codes(), module-added ones included), paired with
	 * enough presentation metadata for both the help modal and the "/"
	 * autocomplete dropdown below. 'open'/'close' are the literal tags to
	 * insert - for a parameterized tag 'open' already carries a filled-in
	 * placeholder value (e.g. '[color="red"]', not bare '[color]') per
	 * request: pasting the bare form left people hand-typing the
	 * '="..."' themselves, error-prone versus just replacing an
	 * already-correct placeholder. 'placeholder', when set, is that exact
	 * substring of 'open' so the autocomplete can select it (ready to be
	 * typed over) instead of just placing the cursor. A code with no entry
	 * here (some future module's own tag) still gets listed, just
	 * generically, with no placeholder to select.
	 */
	private static function bbcode_reference() {
		$known = array(
			'b'       => array('label'=>__('Bold'),            'open'=>'[b]',                                 'close'=>'[/b]',       'inner'=>'text',                 'placeholder'=>null),
			'i'       => array('label'=>__('Italic'),          'open'=>'[i]',                                 'close'=>'[/i]',       'inner'=>'text',                 'placeholder'=>null),
			'u'       => array('label'=>__('Underline'),       'open'=>'[u]',                                 'close'=>'[/u]',       'inner'=>'text',                 'placeholder'=>null),
			's'       => array('label'=>__('Strikethrough'),   'open'=>'[s]',                                 'close'=>'[/s]',       'inner'=>'text',                 'placeholder'=>null),
			'url'     => array('label'=>__('Link'),            'open'=>'[url]',                               'close'=>'[/url]',     'inner'=>'https://example.com',  'placeholder'=>null),
			'color'   => array('label'=>__('Color'),           'open'=>'[color="red"]',                       'close'=>'[/color]',   'inner'=>'text',                 'placeholder'=>'red'),
			'img'     => array('label'=>__('Image'),           'open'=>'[img="https://example.com/pic.png"]', 'close'=>'[/img]',     'inner'=>'',                     'placeholder'=>'https://example.com/pic.png'),
			'task'    => array('label'=>__('Task link'),       'open'=>'[task="123"]',                        'close'=>'[/task]',    'inner'=>'title',                'placeholder'=>'123'),
			'meeting' => array('label'=>__('Meeting link'),    'open'=>'[meeting="123"]',                     'close'=>'[/meeting]', 'inner'=>'title',                'placeholder'=>'123'),
			'contact' => array('label'=>__('Contact link'),    'open'=>'[contact="123"]',                     'close'=>'[/contact]', 'inner'=>'name',                 'placeholder'=>'123'),
			'company' => array('label'=>__('Company link'),    'open'=>'[company="123"]',                     'close'=>'[/company]', 'inner'=>'name',                 'placeholder'=>'123'),
			'phone'   => array('label'=>__('Phone call link'), 'open'=>'[phone="123"]',                       'close'=>'[/phone]',   'inner'=>'subject',              'placeholder'=>'123'),
			// Both of these are record-link tags exactly like task/meeting/
			// contact/company/phone above (Premium_Projects_TicketsCommon::
			// ticket_bbcode()/Premium_ProjectsCommon::project_bbcode(), both
			// just Utils_RecordBrowserCommon::record_bbcode() under a
			// different table) - omitting them here previously left them
			// falling through to the generic no-param fallback below
			// ('[ticket]text[/ticket]', missing the required ="id"), which
			// is wrong for a tag whose whole point is looking up one
			// specific record by id.
			'ticket'  => array('label'=>__('Ticket link'),     'open'=>'[ticket="123"]',                      'close'=>'[/ticket]',  'inner'=>'title',                'placeholder'=>'123'),
			'project' => array('label'=>__('Project link'),    'open'=>'[project="123"]',                     'close'=>'[/project]', 'inner'=>'name',                 'placeholder'=>'123'),
		);
		$codes = Utils_BBCodeCommon::get_registered_codes();
		sort($codes);
		$ret = array();
		foreach ($codes as $code) {
			$meta = $known[$code] ?? array('label'=>ucfirst($code), 'open'=>'['.$code.']', 'close'=>'[/'.$code.']', 'inner'=>'text', 'placeholder'=>null);
			$meta['example'] = $meta['open'].$meta['inner'].$meta['close'];
			$ret[$code] = $meta;
		}
		return $ret;
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
        	if(Base_User_SettingsCommon::get('Apps_Shoutbox','enable_im')) {
        	    $adm = Base_User_SettingsCommon::get_admin('Apps_Shoutbox','enable_im');
        	    // Online status is now just a "* " decoration on an otherwise-full
        	    // employee list (see loop below), not a filter on who's selectable -
        	    // so this stays optional rather than gating the whole feature.
    		    $online = ModuleManager::is_installed('Tools_WhoIsOnline')>=0 ? Tools_WhoIsOnlineCommon::get_ids() : array();
            	    if(ModuleManager::is_installed('CRM_Contacts')>=0) {
                	    $emps = DB::GetAssoc('SELECT l.id,'.DB::ifelse('cd.f_last_name!=\'\'',DB::concat('cd.f_last_name',DB::qstr(' '),'cd.f_first_name',DB::qstr(' ('),'l.login',DB::qstr(')')),'l.login').' as name FROM user_login l LEFT JOIN contact_data_1 cd ON (cd.f_login=l.id AND cd.active=1) LEFT JOIN base_user_settings us ON (us.user_login_id=l.id AND module=\'Apps_Shoutbox\' AND variable=\'enable_im\') WHERE l.active=1 AND l.id!=%d AND (us.value=%s OR us.value is '.($adm?'':'not ').'null) ORDER BY name',array($myid,serialize(1)));
	            } else
    		            $emps = DB::GetAssoc('SELECT l.id,l.login FROM user_login l LEFT JOIN base_user_settings us ON (us.user_login_id=l.id AND module=\'Apps_Shoutbox\' AND variable=\'enable_im\') WHERE l.active=1 AND l.id!=%d AND (us.value=%s OR us.value is '.($adm?'':'not ').'null) ORDER BY l.login',array($myid,serialize(1)));
    		    foreach($online as $id) if(isset($emps[$id])) $emps[$id] = '* '.$emps[$id];
    		} else $emps = array();
		    // Plain 'select', not 'autoselect': the latter's own JS unconditionally
		    // swaps to a search-only box on any click/tap (meant for huge
		    // record-picker lists where a native dropdown would be unusable - see
		    // FieldTypes/autoselect/autoselect.php's mousedown/touchstart handler
		    // comment), which for a short employee list meant clicking "To" could
		    // never actually open a browsable list - it always jumped straight to
		    // "Start typing to search...". History's own "User" filter below uses
		    // the same plain 'select' for the same reason.
		    // No id suffix here (unlike shoutbox_text_big/shoutbox_button_big below):
		    // ShoutboxCommon::create_write_to_link()'s onclick handler looks this id
		    // up directly (document.getElementById('shoutbox_to')) regardless of
		    // which form (small applet vs. big Chat tab) is on screen, so it must
		    // stay the same plain id in both.
		    $qf->addElement('select','shoutbox_to',__('To'), array('all'=>'['.__('All').']')+$emps, array('id'=>'shoutbox_to','onChange'=>'shoutbox_uid=this.value;shoutbox_refresh'.($big?'_big':'').'()'));
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

		    //confirm when sending messages to all: an AdminLTE-styled Bootstrap
		    //modal, injected once and shared by both the small (applet) and big
		    //(Chat tab) forms. bootstrap is only ever loaded for the adminlte
		    //theme (Base_ThemeCommon::load_theme_assets()), so its absence is
		    //exactly the signal to fall back to plain confirm() for the default
		    //theme.
		    $confirm_modal_html = '<div class="modal fade" id="Apps_Shoutbox__confirm_all_modal" tabindex="-1" aria-hidden="true">'
		    		.'<div class="modal-dialog modal-dialog-centered">'
		    			.'<div class="modal-content">'
		    				.'<div class="modal-header">'
		    					.'<h5 class="modal-title"><i class="bi bi-megaphone-fill me-2"></i>'.__('Shoutbox').'</h5>'
		    					.'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
		    				.'</div>'
		    				.'<div class="modal-body">'
		    					.'<p class="mb-0">'.__('Send message to all?').'</p>'
		    				.'</div>'
		    				.'<div class="modal-footer">'
		    					.'<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">'.__('Cancel').'</button>'
		    					// autofocus (not a custom "shown.bs.modal" focus() call - tried
		    					// and abandoned, see below) so Enter confirm-sends by default,
		    					// matching a native confirm()'s Enter-to-OK behaviour.
		    					.'<button type="button" class="btn btn-primary" id="Apps_Shoutbox__confirm_all_send" autofocus><i class="bi bi-send-fill me-1"></i>'.__('Send').'</button>'
		    				.'</div>'
		    			.'</div>'
		    		.'</div>'
		    	.'</div>';
		    // A custom "shown.bs.modal" listener calling .focus() on Send
		    // directly was tried first and abandoned: adminlte.min.js's own
		    // initModalFocusManagement() adds its own document-level
		    // "shown.bs.modal" listener (independent of, and unaffected by,
		    // Bootstrap's own {focus:true/false} Modal option) that
		    // auto-focuses the modal's [autofocus] element if present, else
		    // falls back to the first focusable element in DOM order - the
		    // header's .btn-close here, since it comes before the footer
		    // buttons. A same-event listener of our own raced that fallback
		    // and lost more often than not when confirmed live with actual
		    // focus()-call tracing. The autofocus attribute above feeds
		    // exactly the input that script already looks for first, so this
		    // needs no JS of our own at all.
		    // Bootstrap-less themes have no CSS to hide a ".modal.fade" element, so
		    // injecting it there would leave the raw markup sitting visibly in the
		    // page instead of hidden until .show()'d - the click handler below
		    // already falls back to plain confirm() when this modal was never
		    // injected, so gating the injection itself is safe.
		    if (Base_ThemeCommon::is_adminlte_family()) {
		    	eval_js_once('if(!document.getElementById(\'Apps_Shoutbox__confirm_all_modal\')){'
		    			.'document.body.insertAdjacentHTML(\'beforeend\','.json_encode($confirm_modal_html).');'
		    			.'document.getElementById(\'Apps_Shoutbox__confirm_all_send\').addEventListener(\'click\',function(){'
		    				.'var m=bootstrap.Modal.getInstance(document.getElementById(\'Apps_Shoutbox__confirm_all_modal\'));'
		    				.'if(m)m.hide();'
		    				.'if(window.Apps_Shoutbox__pendingForm)window.Apps_Shoutbox__pendingForm.onsubmit();'
		    			.'});'
		    		.'}');
		    }
		    //bound with a namespaced handler (rather than the plain .click() the
		    //original used) so re-running this eval_js on a re-render can't stack
		    //duplicate listeners onto elements the DOM replace didn't actually swap.
		    eval_js('jq("#shoutbox_button, #shoutbox_button_big").off("click.Apps_Shoutbox_confirmAll").on("click.Apps_Shoutbox_confirmAll",function(){'
		    		// Looked up by name within this button's own form, not a
		    		// guessed "#shoutbox_to"/"#shoutbox_to_big" id: the autoselect
		    		// field's id is always plain "shoutbox_to" (see the addElement
		    		// call above), so an id-based lookup here silently found
		    		// nothing for the big Chat-tab form and skipped the confirm.
		    		.'var toEl=this.form.querySelector(\'[name="shoutbox_to"]\');'
		    		.'if(!toEl||toEl.value!=="all")return true;'
		    		.'var modalEl=document.getElementById("Apps_Shoutbox__confirm_all_modal");'
		    		.'if(!modalEl||typeof bootstrap==="undefined")return confirm('.json_encode(__('Send message to all?')).');'
		    		.'window.Apps_Shoutbox__pendingForm=this.form;'
		    		.'bootstrap.Modal.getOrCreateInstance(modalEl).show();'
		    		.'return false;'
		    	.'});');

		    //Keep the Send button disabled while the message textarea is empty
		    //(or whitespace-only) - previously it stayed clickable with no text
		    //at all, which for "to=all" fired the confirm-all popup above (or,
		    //for a specific recipient, a round-trip just to hit QuickForm's
		    //server-side "Field required" rule) on a message with nothing to
		    //send. Namespaced off/on on the textarea's own "input" event, same
		    //idiom as the click handler above and for the same reason (re-
		    //running this eval_js on a re-render can't stack duplicate
		    //listeners onto elements the DOM replace didn't actually swap).
		    eval_js('(function(){'
		    		.'var txt=document.getElementById("shoutbox_text'.($big?'_big':'').'");'
		    		.'var btn=document.getElementById("shoutbox_button'.($big?'_big':'').'");'
		    		.'if(!txt||!btn)return;'
		    		.'function sync(){btn.disabled=!txt.value.trim();}'
		    		.'sync();'
		    		.'jq(txt).off("input.Apps_Shoutbox_toggleSend").on("input.Apps_Shoutbox_toggleSend",sync);'
		    	.'})();');

		    //BBCode reference: built once per request from whatever's actually
		    //registered (bbcode_reference(), backed by Utils_BBCodeCommon::
		    //get_registered_codes()) so a module installed later shows up here
		    //without touching this file. Two ways in: the "?" title-bar icon
		    //added in applet() (adminlte only, opens this modal via Bootstrap's
		    //own data-bs-toggle) and, regardless of theme, typing "/" in
		    //either message box.
		    $bbcode_ref = self::bbcode_reference();
		    $bbcode_rows = '';
		    foreach ($bbcode_ref as $code => $meta) {
		    	$bbcode_rows .= '<tr><td><code>['.htmlspecialchars($code).']</code></td><td>'.htmlspecialchars($meta['label']).'</td><td><code>'.htmlspecialchars($meta['example']).'</code></td></tr>';
		    }
		    if (Base_ThemeCommon::is_adminlte_family()) {
			    $help_modal_html = '<div class="modal fade" id="Apps_Shoutbox__bbcode_help_modal" tabindex="-1" aria-hidden="true">'
			    		.'<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">'
			    			.'<div class="modal-content">'
			    				.'<div class="modal-header">'
			    					.'<h5 class="modal-title"><i class="bi bi-question-circle-fill me-2"></i>'.__('Formatting help (BBCode)').'</h5>'
			    					.'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
			    				.'</div>'
			    				.'<div class="modal-body">'
			    					.'<table class="table table-sm table-borderless mb-0"><thead><tr><th>'.__('Tag').'</th><th>'.__('Purpose').'</th><th>'.__('Example').'</th></tr></thead><tbody>'
			    					.$bbcode_rows
			    					.'</tbody></table>'
			    					.'<p class="text-muted small mb-0 mt-2">'.__('Tip: type "/" in the message box to insert a tag automatically.').'</p>'
			    				.'</div>'
			    				.'<div class="modal-footer">'
			    					.'<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">'.__('Close').'</button>'
			    				.'</div>'
			    			.'</div>'
			    		.'</div>'
			    	.'</div>';
			    eval_js_once('if(!document.getElementById(\'Apps_Shoutbox__bbcode_help_modal\')){'
			    		.'document.body.insertAdjacentHTML(\'beforeend\','.json_encode($help_modal_html).');'
			    	.'}');
		    }

		    //"/" autocomplete: typing "/" at the start of a word (start of the
		    //message, or right after whitespace - so it doesn't fire mid-URL,
		    //e.g. "https://") in #shoutbox_text or #shoutbox_text_big shows a
		    //filterable dropdown of the same tags; picking one (click, Enter or
		    //Tab) replaces "/<filter>" with the tag's full open+close pair -
		    //per request, a parameterized tag's opening half already carries
		    //its placeholder value (e.g. [color="red"], not bare [color]),
		    //with that placeholder pre-selected so typing immediately
		    //replaces it instead of the user hand-typing '="..."' themselves.
		    //Plain CSS/DOM, no Bootstrap dependency, so unlike the modal
		    //above this runs on every theme. document-level delegated
		    //listeners (not bound to the textarea itself) so this doesn't need
		    //rebinding when the form re-renders after a validation error, the
		    //way the confirm-all click handler above does.
		    $bbcode_list_json = json_encode(array_map(fn($code, $meta) => array('code'=>$code, 'label'=>$meta['label'], 'open'=>$meta['open'], 'close'=>$meta['close'], 'placeholder'=>$meta['placeholder']), array_keys($bbcode_ref), array_values($bbcode_ref)));
		    // Built as a plain PHP string (hardcoded id, not the JS MENU_ID
		    // variable below) and embedded via json_encode - same safe
		    // string-into-JS approach as $confirm_modal_html/$help_modal_html
		    // above, rather than hand-splicing quotes around a JS "+" concat.
		    $menu_css = '#Apps_Shoutbox__bbcode_menu{display:none;position:fixed;z-index:1060;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #ced4da;border-radius:0.375rem;box-shadow:0 0.25rem 0.75rem rgba(0,0,0,0.25);padding:0.25rem 0;font-size:0.85rem;font-family:inherit;}'
		    		.'#Apps_Shoutbox__bbcode_menu.show{display:block;}'
		    		.'#Apps_Shoutbox__bbcode_menu div{display:flex;gap:0.5rem;padding:0.3rem 0.75rem;cursor:pointer;white-space:nowrap;color:#343a40;}'
		    		.'#Apps_Shoutbox__bbcode_menu div.active,#Apps_Shoutbox__bbcode_menu div:hover{background-color:#e9ecef;}'
		    		.'#Apps_Shoutbox__bbcode_menu div code{color:#0d6efd;}';
		    eval_js_once('(function(){'
		    		// eval_js_once dedups by hash of this exact string, which
		    		// embeds $bbcode_list_json - so it only guards against
		    		// re-sending the IDENTICAL script twice. If that content
		    		// ever changes within one browser session that started
		    		// before the change (a module install/uninstall changing
		    		// get_registered_codes(), or simply this file being edited
		    		// and the page never fully reloaded, as happened live
		    		// while developing this feature), the OLD version's
		    		// document-level "input"/"keydown" listeners are still
		    		// attached - nothing ever removes them - so a NEW version
		    		// sent afterward stacks a second, independent set instead
		    		// of replacing the first. Both then fire on the same
		    		// keydown, each inserting its own copy from its own
		    		// closure state, corrupting the result (confirmed live:
		    		// picking [contact] produced
		    		// [contact="123"][/contact][/contact] - the second
		    		// listener\'s insert landing after the first\'s, using a
		    		// slashPos captured before the first one had mutated
		    		// anything). This flag makes the listener registration
		    		// itself idempotent regardless of how many times - or how
		    		// many different versions of - this IIFE actually runs.
		    		.'if(window.__Apps_Shoutbox_bbcode_ac__)return;'
		    		.'window.__Apps_Shoutbox_bbcode_ac__=true;'
		    		.'var LIST='.$bbcode_list_json.';'
		    		.'var MENU_ID="Apps_Shoutbox__bbcode_menu";'
		    		.'var state=null;'
		    		.'function getMenu(){'
		    			.'var m=document.getElementById(MENU_ID);'
		    			.'if(!m){'
		    				.'m=document.createElement("div");'
		    				.'m.id=MENU_ID;'
		    				.'document.body.appendChild(m);'
		    				.'var css=document.createElement("style");'
		    				.'css.textContent='.json_encode($menu_css).';'
		    				.'document.head.appendChild(css);'
		    			.'}'
		    			.'return m;'
		    		.'}'
		    		.'function closeMenu(){'
		    			.'var m=document.getElementById(MENU_ID);'
		    			.'if(m)m.classList.remove("show");'
		    			.'state=null;'
		    		.'}'
		    		.'function renderMenu(){'
		    			.'var m=getMenu();'
		    			.'m.innerHTML="";'
		    			.'state.items.forEach(function(item,idx){'
		    				.'var row=document.createElement("div");'
		    				.'if(idx===state.active)row.className="active";'
		    				.'var code=document.createElement("code");'
		    				.'code.textContent="["+item.code+"]";'
		    				.'var label=document.createElement("span");'
		    				.'label.textContent=item.label;'
		    				.'row.appendChild(code);'
		    				.'row.appendChild(label);'
		    				.'row.addEventListener("mousedown",function(e){e.preventDefault();selectItem(item);});'
		    				.'m.appendChild(row);'
		    			.'});'
		    			.'var r=state.textarea.getBoundingClientRect();'
		    			.'m.style.left=r.left+"px";'
		    			.'m.style.top=(r.bottom+4)+"px";'
		    			.'m.style.minWidth=Math.min(r.width,260)+"px";'
		    			.'m.classList.add("show");'
		    		.'}'
		    		.'function selectItem(item){'
		    			.'var textarea=state.textarea, slashPos=state.slashPos;'
		    			.'var val=textarea.value, pos=textarea.selectionStart;'
		    			.'var before=val.substring(0,slashPos), after=val.substring(pos);'
		    			.'textarea.value=before+item.open+item.close+after;'
		    			.'if(item.placeholder){'
		    				// Select the placeholder value inside the already-
		    				// filled-in opening tag (e.g. "red" in [color="red"])
		    				// so typing replaces it directly, instead of just
		    				// landing the cursor and making the user type the
		    				// whole '="..."' themselves.
		    				.'var phStart=before.length+item.open.indexOf(item.placeholder);'
		    				.'textarea.setSelectionRange(phStart,phStart+item.placeholder.length);'
		    			.'}else{'
		    				.'var caret=before.length+item.open.length;'
		    				.'textarea.setSelectionRange(caret,caret);'
		    			.'}'
		    			.'textarea.focus();'
		    			.'closeMenu();'
		    		.'}'
		    		.'function updateMenu(textarea){'
		    			.'var val=textarea.value, pos=textarea.selectionStart;'
		    			.'var i=pos-1;'
		    			.'while(i>=0&&!/\\s/.test(val[i])&&val[i]!=="/")i--;'
		    			.'if(i<0||val[i]!=="/"||(i>0&&!/\\s/.test(val[i-1]))){closeMenu();return;}'
		    			.'var slashPos=i;'
		    			.'var filter=val.substring(slashPos+1,pos).toLowerCase();'
		    			.'var items=LIST.filter(function(t){return t.code.indexOf(filter)===0;});'
		    			.'if(!items.length){closeMenu();return;}'
		    			.'state={textarea:textarea,slashPos:slashPos,items:items,active:0};'
		    			.'renderMenu();'
		    		.'}'
		    		.'document.addEventListener("input",function(e){'
		    			.'if(e.target&&(e.target.id==="shoutbox_text"||e.target.id==="shoutbox_text_big"))updateMenu(e.target);'
		    		.'});'
		    		.'document.addEventListener("keydown",function(e){'
		    			.'if(!state||e.target!==state.textarea)return;'
		    			.'if(e.key==="ArrowDown"){e.preventDefault();state.active=(state.active+1)%state.items.length;renderMenu();}'
		    			.'else if(e.key==="ArrowUp"){e.preventDefault();state.active=(state.active-1+state.items.length)%state.items.length;renderMenu();}'
		    			.'else if(e.key==="Enter"||e.key==="Tab"){e.preventDefault();selectItem(state.items[state.active]);}'
		    			.'else if(e.key==="Escape"){e.preventDefault();closeMenu();}'
		    		.'});'
		    		.'document.addEventListener("click",function(e){'
		    			.'var m=document.getElementById(MENU_ID);'
		    			.'if(m&&m.classList.contains("show")&&!m.contains(e.target)&&e.target.id!=="shoutbox_text"&&e.target.id!=="shoutbox_text_big")closeMenu();'
		    		.'});'
		    	.'})();');

			//if submited
			if($qf->validate()) {
				 //get post group
				$msg = $qf->exportValue('post');
				$to = $qf->exportValue('shoutbox_to');
				//get msg from post group
				$msg = Utils_BBCodeCommon::optimize($msg);
				//get logged user id
				$user_id = Base_AclCommon::get_user();
				//clear text box and focus it - value is set programmatically, which
				//doesn't fire the 'input' event the Send-button disable toggle
				//above listens for, so its disabled state is set explicitly here too.
				eval_js('document.getElementById(\'shoutbox_text'.($big?'_big':'').'\').value=\'\';focus_by_id(\'shoutbox_text'.($big?'_big':'').'\');shoutbox_uid="'.$to.'";var b=document.getElementById(\'shoutbox_button'.($big?'_big':'').'\');if(b)b.disabled=true;');

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
		eval_js_once('shoutbox_refresh'.($big?'_big':'').' = function(){if(!document.getElementById(\'shoutbox_board'.($big?'_big':'').'\')) return;'.
			// Shoutbox needs this most: its response is never empty - it re-renders the
			// last 20 messages every time - so unlike Notify and Messenger it has no
			// server-side early-out to fall back on, and at 10-30s it is the fastest
			// timer of the four.
			'if(document.hidden) return;'.
			'jQuery(\'#shoutbox_board'.($big?'_big':'').'\').load(\'modules/Apps/Shoutbox/refresh.php?uid=\'+encodeURIComponent(shoutbox_uid));'.
			'};document.addEventListener(\'visibilitychange\',function(){if(!document.hidden) shoutbox_refresh'.($big?'_big':'').'();});'.
			'setInterval(\'shoutbox_refresh'.($big?'_big':'').'()\','.($big?'10000':'30000').')');
		eval_js('shoutbox_refresh'.($big?'_big':'').'()');
	}

	public function caption() {
		return __('Shoutbox');
	}
}

?>
