<?php
/**
 * Use this module if you want to add attachments to some page.
 * Owner of note has always 3x(private,protected,public) write&read.
 * Permission for group is set by methods allow_{private,protected,public}.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Attachment extends Module {
	private $group;
	private $persistent_deletion = false;

	private $private_read = false;
	private $private_write = false;
	private $protected_read = false;
	private $protected_write = true;
	private $public_read = true;
	private $public_write = true;
	private $author = true;

	private $add_header = '';
	private $max_file_size = null;

	private $caption = '';
	
	private $watchdog_category;
	private $watchdog_id;
	
	private $func = null;
	private $args = array();

	private $add_func = null;
	private $add_args = array();

	public function construct($group=null,$pd=null,$priv_r=null,$priv_w=null,$prot_r=null,$prot_w=null,$pub_r=null,$pub_w=null,$header=null,$watchdog_cat=null,$watchdog_id=null,$func=null,$args=null,$add_func=null,$add_args=null,$max_fs=null) {
		$this->group = & $this->get_module_variable('group',isset($group)?$group:null);
		$this->func = & $this->get_module_variable('func',isset($func)?$func:null);
		$this->args = & $this->get_module_variable('args',isset($args)?$args:null);
		
		if(isset($pd)) $this->persistent_deletion = $pd;
		if(isset($priv_r)) $this->private_read = $priv_r;
		if(isset($priv_w)) $this->private_write = $priv_w;
		if(isset($prot_r)) $this->protected_read = $prot_r;
		if(isset($prot_w)) $this->protected_write = $prot_w;
		if(isset($pub_r)) $this->public_read = $pub_r;
		if(isset($pub_w)) $this->public_write = $pub_w;
		$this->add_header = & $this->get_module_variable('header',isset($header)?$header:null);
		if(isset($watchdog_cat)) $this->watchdog_category = $watchdog_cat;
		if(isset($watchdog_id)) $this->watchdog_id = $watchdog_id;
		if(isset($add_func)) $this->add_func = $add_func;
		if(isset($add_args)) $this->add_args = $add_args;
		if(isset($max_fs)) $this->max_file_size = $max_fs;
	}
	
	public function set_max_file_size($s) {
		$this->max_file_size = $s;
	}

	public function additional_header($x) {
		$this->add_header = $x;
	}

	public function set_view_func($x, array $y=array()) {
		$this->func = $x;
		$this->args = $y;
	}

	public function set_add_func($x, array $y=array()) {
		$this->add_func = $x;
		$this->add_args = $y;
	}

	public function set_persistent_delete($x=true) {
		$this->persistent_deletion = $x;
	}

	public function allow_private($read,$write=null) {
		$this->private_read = $read;
		if(!isset($write)) $write=$read;
		$this->private_write = $write;
	}

	public function allow_protected($read,$write=null) {
		$this->protected_read = $read;
		if(!isset($write)) $write=$read;
		$this->protected_write = $write;
	}

	public function allow_public($read,$write=null) {
		$this->public_read = $read;
		if(!isset($write)) $write=$read;
		$this->public_write = $write;
	}

	public function display_author_column($x=true) {
		$this->author = $x;
	}
	
	public function admin() {
		if ($this->is_back()) {
			$this->parent->reset();
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

		$google_login = Variable::get('utils_attachments_google_user', false);
		$google_pass = Variable::get('utils_attachments_google_pass', false);

		$form = $this->init_module('Libs_QuickForm');
		$theme = $this->init_module('Base_Theme');

		$form->addElement('header', 'header', __('Google Username and Password'));

		$form->addElement('text', 'google_user', __('Username'));
		$form->addElement('password', 'google_pass', __('Password'));

		$form->setDefaults(array('google_user'=>$google_login));
		$form->setDefaults(array('google_pass'=>$google_pass));

		if ($form->validate()) {
			$vals = $form->exportValues();

			$ok = true;
			if ($vals['google_user']) {
				$g_auth = Utils_AttachmentCommon::get_google_auth($vals['google_user'], $vals['google_pass']);
				if (!$g_auth) $ok = false;
			}

			if ($ok) {
				Variable::set('utils_attachments_google_user', $vals['google_user']);
				Variable::set('utils_attachments_google_pass', $vals['google_pass']);

				Base_StatusBarCommon::message(__('Settings saved'));
			} else {
				Base_StatusBarCommon::message(__('Unable to authenticate'), 'error');
			}
			location(array());
			return;
		}

		$form->assign_theme('form', $theme);

		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		
		Base_ThemeCommon::load_css('Utils_RecordBrowser','View_entry');
		$theme->display('admin');
	}
	
	public function body($arg=null, $rb=null) {
		if(isset($arg) && isset($rb)) {
			$this->group = $rb->tab.'/'.$arg['id'];
			$this->add_header = $rb->caption();
			if(Utils_WatchdogCommon::get_category_id($rb->tab)!==null) {
				$this->watchdog_category = $rb->tab;
				$this->watchdog_id = $arg['id'];
			}
			$this->set_view_func(array('Utils_RecordBrowserCommon','create_default_linked_label'),array($rb->tab, $arg['id']));
			$this->allow_protected(true, false);
		}
		if(!isset($this->group)) trigger_error('Key not given to attachment module',E_USER_ERROR);
	
		$vd = null;
		if(!$this->persistent_deletion)
			$vd = isset($_SESSION['view_deleted_attachments']) && $_SESSION['view_deleted_attachments'] && Base_AclCommon::i_am_admin();
		
		
		if (!is_array($this->group)) $group = DB::qstr($this->group);
		else {
			if (empty($this->group))
				$group = DB::qstr('');
			else {
				$group = array();
				foreach ($this->group as $k=>$v) $group[$k] = DB::qstr($v);
				$group = implode(' OR ual.local=', $group);
			}
		}
		
		//form filtrow
		$form = $this->init_module('Libs/QuickForm');
		$query = 'SELECT uac.created_by as note_by FROM (utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id) INNER JOIN utils_attachment_file uaf ON uaf.attach_id=ual.id WHERE (false OR ual.local='.$group.') AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id) '.($vd?'':'AND ual.deleted=0 ').'GROUP BY uac.created_by';
		$emp_ids = DB::GetCol($query);
		$emps = array();
		if($emp_ids) {
  	    	if(ModuleManager::is_installed('CRM_Contacts')>=0) {
		   	 	$emps = DB::GetAssoc('SELECT l.id,'.DB::ifelse('cd.f_last_name!=\'\'',DB::concat('cd.f_last_name',DB::qstr(' '),'cd.f_first_name'),'l.login').' as name FROM user_login l LEFT JOIN contact_data_1 cd ON (cd.f_login=l.id AND cd.active=1) WHERE l.active=1 AND l.id IN ('.implode(',',$emp_ids).') ORDER BY name');
			} else{
				$emps = DB::GetAssoc('SELECT id,login FROM user_login WHERE active=1 AND l.id IN ('.implode(',',$emp_ids).') ORDER BY login');
			}
		}	
	    $form->addElement('text', 'filter_text', __('Search'), array('placeholder'=>__('Keyword').'...'));
	    $form->addElement('select', 'filter_user', __('Filter by user'), array(''=>'---')+$emps);
		
		$form->addElement('datepicker', 'filter_start', __('Start Date'));
		$form->addElement('datepicker', 'filter_end', __('End Date'));
		
		$form->addElement('submit', 'submit_button', __('Filter'));
	//	$form->display();
		$filter_user = $form->exportValue('filter_user');
		$filter_text = $form->exportValue('filter_text');
		$filter_start = $form->exportValue('filter_start');
		$filter_end = $form->exportValue('filter_end');

		$where = '';
		if($filter_user && is_numeric($filter_user))
			$where .= ' AND uac.created_by='.$filter_user;
		if($filter_text) 
			$where .= ' AND uac.text '.DB::like().' '.DB::Concat(DB::qstr('%'),DB::qstr($filter_text),DB::qstr('%'));
		if($filter_start)
			$where .= ' AND uac.created_on >= '.DB::qstr($filter_start);
		if($filter_end)
			$where .= ' AND uac.created_on <= '.DB::qstr($filter_end.' 23:59:59');
		if (!$vd)
			$where = ' AND ual.deleted=0'.$where;
		
		
		$gb = $this->init_module('Utils/GenericBrowser',null,md5(serialize($this->group)));
		$cols = array();
		if($vd)
			$cols[] = array('name'=>__('Deleted'),'order'=>'ual.deleted','width'=>5);
		if($this->author)
			$cols[] = array('name'=>__('User'), 'order'=>'note_by','width'=>12, 'wrapmode'=>'nowrap');
		if (is_array($this->group)) $cols[] = array('name'=>__('Source'),'width'=>15, 'wrapmode'=>'nowrap');
		$cols[] = array('name'=>__('Date'), 'order'=>'note_on','width'=>10,'wrapmode'=>'nowrap');
		$cols[] = array('name'=>__('Note'), 'width'=>70);
		$gb->set_table_columns($cols);
		

		$query_from = ' FROM (utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id) INNER JOIN utils_attachment_file uaf ON uaf.attach_id=ual.id WHERE ual.local='.$group.' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id)'.$where;
		$query = 'SELECT ual.sticky,uaf.id as file_id,(SELECT count(*) FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id=ual.id) as downloads,(SELECT l.login FROM user_login l WHERE ual.permission_by=l.id) as permission_owner,ual.permission,ual.permission_by,ual.deleted,ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,uac.created_by as note_by,uac.text,uaf.original,uaf.created_on as upload_on,uaf.created_by as upload_by, ual.func AS search_func, ual.args AS search_func_args'.$query_from;
		$query_lim = 'SELECT count(ual.id)'.$query_from;

		$gb->set_default_order(array(__('Date')=>'DESC'));

		$query_order = $gb->get_query_order('ual.sticky DESC');
		$qty = DB::GetOne($query_lim);
		$query_limits = $gb->get_limit($qty);
		$ret = DB::SelectLimit($query.$query_order,$query_limits['numrows'],$query_limits['offset']);

		Base_ThemeCommon::load_css('Utils_Attachment','browse');
		load_js('modules/Utils/Attachment/js/main.js');
		eval_js('expandable_notes = new Array();');
		eval_js('expandable_notes_amount = 0;');
		eval_js('expanded_notes = 0;');

		eval_js('notes_expand_icon = "'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'expand.gif').'";');
		eval_js('notes_collapse_icon = "'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'collapse.gif').'";');
		eval_js('notes_expand_icon_off = "'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'expand_gray.gif').'";');
		eval_js('notes_collapse_icon_off = "'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'collapse_gray.gif').'";');

		$button_theme = $this->init_module('Base_Theme');
		if($this->public_write && !is_array($this->group)) {
			$button_theme->assign('new_note',array(
				'label'=>__('New Note'),
				'href'=>'href="javascript:void(0);" onclick=\'$("attachments_new_note").style.display="";scrollBy(0, -2000); scrollBy(0, getTotalTopOffet($("attachments_new_note"))-140);\''
			));
				if(ModuleManager::is_installed('Premium/MultipleAttachments')>=0){
				$multipleAttachments = $this->init_module('Premium/MultipleAttachments');				
				$multipleAttachments->set_inline_display(true);
				$button_theme->assign('multiple_attachments',$this->get_html_of_module($multipleAttachments,array($this->group, $this->func, $this->args, $this->add_func, $this->add_args)));
				
			}
			

			$r = $gb->get_new_row();
			$new_note_form = $this->get_edit_form();
			
			$new_note_form->set_submit_callback(array($this, 'submit_attach'));
			if ($new_note_form->validate()) {
				$new_note_form->process(array($new_note_form,'submit_parent'));
				location(array());
				return;
			}
			
			$new_note_form->addElement('button', 'save', __('Save note'), array('class'=>'button', 'onclick'=>$new_note_form->get_submit_form_js()));
			$new_note_form->addElement('button', 'cancel', __('Cancel'), array('class'=>'button', 'onclick'=>'$("attachments_new_note").style.display="none";'));
			
			$renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
			$new_note_form->accept($renderer); 
			$form_data = $renderer->toArray();

			$gb->set_prefix($form_data['javascript'].'<form '.$form_data['attributes'].'>'.$form_data['hidden'].$form_data['upload_iframe']['html']);
			$gb->set_postfix('</form>');

			$inline_form_theme = $this->init_module('Base_Theme');
			$inline_form_theme->assign('form', $form_data);
			ob_start();
			$inline_form_theme->display('inline_form');
			$fields = ob_get_clean();

			$arr = array();
			$count = 2;
			if($vd)
				$count++;
			if($this->author)
				$count++;
			$arr[] = array('value'=>$fields, 'overflow_box'=>false, 'attrs'=>'colspan="'.$count.'"');
			if($vd)
				$arr[] = array('value'=>'', 'overflow_box'=>false, 'style'=>'display:none;');
			if($this->author)
				$arr[] = array('value'=>'', 'overflow_box'=>false, 'style'=>'display:none;');
			$arr[] = array('value'=>'', 'overflow_box'=>false, 'style'=>'display:none;');

			$r->set_attrs('id="attachments_new_note" style="display:none;"');
			
			$r->add_data_array($arr);

			if(isset($_SESSION['attachment_copy'])) {
				$button_theme->assign('paste',array(
					'label'=>__('Paste note'),
					'href'=>Utils_TooltipCommon::open_tag_attrs($_SESSION['attachment_copy']['text']).' '.$this->create_callback_href(array($this,'paste'))
				));
			}
			if(Base_AclCommon::i_am_admin()) {
				$button_theme->assign('show_deleted',array(
					'label'=>__('Show deleted notes'),
					'default'=>($vd?'checked="1"':''),
					'show'=>$this->create_callback_href_js(array($this,'show_deleted'),array(true)),
					'hide'=>$this->create_callback_href_js(array($this,'show_deleted'),array(false))
				));
			}
			$col_span = $vd?5:4;
			eval_js('if($("attachments_new_note").childNodes.length=='.($vd?11:9).'){var n_delete=1;var n_expand=2;if($("attachments_new_note").childNodes[1].getAttribute("colspan")){n_delete=3;n_expand=1;}$("attachments_new_note").removeChild($("attachments_new_note").childNodes[n_delete]);$("attachments_new_note").childNodes[n_expand].setAttribute("colspan", '.$col_span.');}');
		}

		while($row = $ret->FetchRow()) {
			if(!Base_AclCommon::i_am_admin() && $row['permission_by']!=Acl::get_user()) {
				if($row['permission']==0 && !$this->public_read) continue;//protected
				elseif($row['permission']==1 && !$this->protected_read) continue;//protected
				elseif($row['permission']==2 && !$this->private_read) continue;//private
			}
			$r = $gb->get_new_row();


			$inline_img = '';
			$link_href = '';
			$link_img = '';
			if($row['original']!=='') {
				$f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'].'_'.$row['file_revision'];
				if(file_exists($f_filename)) {
					$filetooltip = __('Filename: %s<br>File size: %s',array($row['original'],filesize_hr($f_filename))).'<hr>'.__('Last uploaded by %s<br>on %s<br>Number of uploads: %d<br>Number of downloads: %d',array(Base_UserCommon::get_user_label($row['upload_by']),Base_RegionalSettingsCommon::time2reg($row['upload_on']),$row['file_revision'],$row['downloads']));
					$view_link = '';
					$link_href = Utils_TooltipCommon::open_tag_attrs($filetooltip).' '.$this->get_file($row,$view_link);
					$link_img = Base_ThemeCommon::get_template_file($this->get_type(),'z-attach.png');
					if(Utils_AttachmentCommon::is_image($row) && $view_link)
						$inline_img = '<hr><a href="'.$view_link.'" target="_blank"><img src="'.$view_link.'" style="max-width:700px" /></a><br>';
				} else {
					$link_href = Utils_TooltipCommon::open_tag_attrs(__('Missing file: %s',array($f_filename)));
					$link_img = Base_ThemeCommon::get_template_file($this->get_type(),'z-attach-off.png');
				}
			}
			if ($link_href)
				$icon = '<a style="margin-right: 3px; float:left;" '.$link_href.'><img src="'.$link_img.'"></a>';
			else
				$icon = '';

			$def_permissions = array(__('Public'),__('Protected'),__('Private'));
			$perm = $def_permissions[$row['permission']];
			$created_on = $row['note_on']>$row['upload_on']?$row['note_on']:$row['upload_on'];
			$note_on = Base_RegionalSettingsCommon::time2reg($created_on,0);
			$note_on_time = Base_RegionalSettingsCommon::time2reg($created_on,1);
			$info = __('Owner: %s',array($row['permission_owner'])).'<br>'.
				__('Permission: %s',array($perm)).'<hr>'.
				__('Last edited by %s<br>on %s<br>Number of edits: %d',array(Base_UserCommon::get_user_label($row['note_by']),$note_on_time,$row['note_revision']));
			$r->add_info($info);
			if(Base_AclCommon::i_am_admin() ||
			 	$row['permission_by']==Acl::get_user() ||
			   ($row['permission']==0 && $this->public_write) ||
			   ($row['permission']==1 && $this->protected_write) ||
			   ($row['permission']==2 && $this->private_write)) {
				if(!isset($row['deleted']) || !$row['deleted']) {
    				$r->add_action($this->create_callback_href(array($this,'edit_note_queue'),$row['id']),'edit');
    				$r->add_action($this->create_confirm_callback_href(__('Delete this entry?'),array($this,'delete'),$row['id']),'delete');
    			} else {
    				$r->add_action('','edit',__('You cannot edit deleted notes'),null,0,true);
    			    $r->add_action($this->create_confirm_callback_href(__('Do you want to restore this entry?'),array($this,'restore'),$row['id']),'restore', null,null, -1);
				}
			}
			$r->add_action($this->create_callback_href(array($this,'view_queue'),array($row['id'])),'view');
			$r->add_action($this->create_callback_href(array($this,'edition_history_queue'),$row['id']),'history');

			$text = trim(Utils_BBCodeCommon::parse($row['text']));

			if(!isset($row['deleted']) || !$row['deleted']) {
        		$r->add_action($this->create_callback_href(array($this,'copy'),array($row['id'],$text)),'copy',null,Base_ThemeCommon::get_template_file($this->get_type(),'copy_small.png'), 3);
		    	$r->add_action($this->create_confirm_callback_href(__('Are you sure you want to cut this note?'), array($this,'cut'),array($row['id'],$text)),'cut',null,Base_ThemeCommon::get_template_file($this->get_type(),'cut_small.png'), 4);
		    }
			
			$text = $icon.$text;
			if($row['sticky']) $text = '<img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'sticky.png').'" hspace=3 align="left"> '.$text;

            $r->add_action('style="display:none;" href="javascript:void(0)" onClick="utils_attachment_expand('.$row['id'].')" id="utils_attachment_more_'.$row['id'].'"','Expand', null, Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'plus_gray.png'), 5);
			$r->add_action('style="display:none;" href="javascript:void(0)" onClick="utils_attachment_collapse('.$row['id'].')" id="utils_attachment_less_'.$row['id'].'"','Collapse', null, Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'minus_gray.png'), 5, false, 0);

			$text = '<div style="height:18px;" id="note_'.$row['id'].'" class="note_field">'.$text.$inline_img.'</div>';

			$regional_note_on = $note_on;
			$arr = array();
			if($vd)
				$arr[] = ($row['deleted']?'<a '.$this->create_confirm_callback_href(__('Do you want to restore this entry?'),array($this,'restore'),array($row['id'])).' '.Utils_TooltipCommon::open_tag_attrs(__('Click to restore')).'>'.__('Yes').'</a>':__('No'));
			if($this->author)
				$arr[] = Base_UserCommon::get_user_label($row['note_by']);
			if (is_array($this->group)) {
				$callback = unserialize($row['search_func']);
				if (is_callable($callback)) {
					$args = unserialize($row['search_func_args']);
					$arr[] = call_user_func_array($callback, $args);
				} else {
					$arr[] = $row['local'];
				}
			}
			$arr[] = $regional_note_on;
			$arr[] = array('value'=>$text, 'overflow_box'=>false);
			$r->add_data_array($arr);

			eval_js('init_note_expandable('.$row['id'].');');
		}

		$button_theme->assign('expand_collapse',array(
			'e_label'=>__('Expand All'),
			'e_href'=>'href="javascript:void(0);" onClick=\'utils_attachment_expand_all()\'',
			'e_id'=>'expand_all_button',
			'c_label'=>__('Collapse All'),
			'c_href'=>'href="javascript:void(0);" onClick=\'utils_attachment_collapse_all()\'',
			'c_id'=>'collapse_all_button'
		));
		$form->assign_theme('form', $button_theme);

		$custom_label = $this->get_html_of_module($button_theme, array('browse'), 'display');
		$gb->set_custom_label($custom_label, 'style="width:100%;"');
		
		$this->display_module($gb);
	}
	
	public function show_deleted($val) {
	    $_SESSION['view_deleted_attachments'] = $val;
	}

	public function view_queue($id) {
		$this->push_box0('view',array($id),array($this->group,$this->persistent_deletion,$this->private_read,$this->private_write,$this->protected_read,$this->protected_write,$this->public_read,$this->public_write,$this->add_header,$this->watchdog_category,$this->watchdog_id,$this->func,$this->args,$this->add_func,$this->add_args,$this->max_file_size));
	}
	
	public function copy($id,$text) {
	 	$_SESSION['attachment_copy'] = array('id'=>$id, 'group'=>$this->group,'text'=>$text);
	}

	public function cut($id,$text) {
	 	$_SESSION['attachment_copy'] = array('id'=>$id, 'group'=>$this->group,'text'=>$text);
	 	$_SESSION['attachment_cut'] = 1;
	}

	public function paste() {
		$oryg = DB::GetRow('SELECT ual.sticky,uaf.id as file_id,(SELECT count(*) FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id=ual.id) as downloads,(SELECT l.login FROM user_login l WHERE ual.permission_by=l.id) as permission_owner,ual.permission,ual.permission_by,ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,uac.created_by as note_by,uac.text,uaf.original,uaf.created_on as upload_on,(SELECT l2.login FROM user_login l2 WHERE uaf.created_by=l2.id) as upload_by FROM (utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id) INNER JOIN utils_attachment_file uaf ON ual.id=uaf.attach_id WHERE '.Utils_AttachmentCommon::get_where($_SESSION['attachment_copy']['group'],false).' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id) AND ual.id=%d',array($_SESSION['attachment_copy']['id']));
		$local = $this->get_data_dir().$_SESSION['attachment_copy']['group'];
		$file = $local.'/'.$_SESSION['attachment_copy']['id'].'_'.$oryg['file_revision'];
		if(file_exists($file)) {
    		$file2 = $file.'_tmp';
            copy($file,$file2);
        } else {
            $file2 = null;
        }
		$id = @Utils_AttachmentCommon::add($this->group,$oryg['permission'],Acl::get_user(),$oryg['text'],$oryg['original'],$file2,$this->func,$this->args,$this->add_func,$this->add_args);
		@unlink($file2);
		DB::Execute('UPDATE utils_attachment_link SET sticky=%b WHERE id=%d',array($oryg['sticky'],$id));

		if(isset($_SESSION['attachment_cut']) && $_SESSION['attachment_cut']) {
			DB::Execute('UPDATE utils_attachment_link SET deleted=1 WHERE id=%d',array($_SESSION['attachment_copy']['id']));
//			if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_r_'.$_SESSION['attachment_copy']['id']);				
// In here we should add "cut" event on note instead
		}

		unset($_SESSION['attachment_copy']);
	}

	public function get_file($row, & $view_link = '') {
		static $th;
		if(!isset($th)) $th = $this->init_module('Base/Theme');

		if($row['original']==='') return '';

		//tag for get.php
		if(!$this->isset_module_variable('public')) {
			$this->set_module_variable('public',$this->public_read);
			$this->set_module_variable('protected',$this->protected_read);
			$this->set_module_variable('private',$this->private_read);
		}

		$lid = 'get_file_'.md5($this->get_path().serialize($row));

		$close_leightbox_js = 'leightbox_deactivate(\''.$lid.'\');';
		if (Variable::get('utils_attachments_google_user',false) && (strpos($row['original'], '.doc')!==false || strpos($row['original'], '.csv')!==false)) {
			$script = 'get_google_docs';
			$onclick = '$(\'attachment_save_options_'.$row['file_id'].'\').style.display=\'\';$(\'attachment_download_options_'.$row['file_id'].'\').hide();';
			$th->assign('save_options_id','attachment_save_options_'.$row['file_id']);
			$th->assign('save','<a href="javascript:void(0);" onclick="'.$close_leightbox_js.$this->create_callback_href_js(array($this, 'save_google_docs'), array($row['file_id'])).'">'.__('Save Changes').'</a><br>');
			$th->assign('discard','<a href="javascript:void(0);" onclick="'.$close_leightbox_js.$this->create_callback_href_js(array($this, 'discard_google_docs'), array($row['file_id'])).'">'.__('Discard Changes').'</a><br>');
		} else {
			$th->assign('save_options_id','');
			$script = 'get';
			$onclick = $close_leightbox_js;
		}
		$th->assign('download_options_id','attachment_download_options_'.$row['file_id']);
		$view_link = 'modules/Utils/Attachment/'.$script.'.php?'.http_build_query(array('id'=>$row['file_id'],'path'=>$this->get_path(),'cid'=>CID,'view'=>1));
		
		$th->assign('view','<a href="'.$view_link.'" target="_blank" onClick="'.$onclick.'">'.__('View').'</a><br>');
		$th->assign('download','<a href="modules/Utils/Attachment/get.php?'.http_build_query(array('id'=>$row['file_id'],'path'=>$this->get_path(),'cid'=>CID)).'" onClick="leightbox_deactivate(\''.$lid.'\')">'.__('Download').'</a><br>');
		load_js('modules/Utils/Attachment/remote.js');
		$th->assign('link','<a href="javascript:void(0)" onClick="utils_attachment_get_link('.$row['file_id'].', '.CID.', \''.Epesi::escapeJS($this->get_path(),false).'\',\'get link\');leightbox_deactivate(\''.$lid.'\')">'.__('Get link').'</a><br>');
		$th->assign('filename',$row['original']);
		$f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'].'_'.$row['file_revision'];
		if(!file_exists($f_filename)) return 'missing file: '.$f_filename;
		$th->assign('file_size',__('File size: %s',array(filesize_hr($f_filename))));

		$th->assign('labels',array(
			'filename'=>__('Filename'),
			'file_size'=>__('File size')
		));

		$getters = ModuleManager::call_common_methods('attachment_getters');
		$custom_getters = array();
		foreach($getters as $mod=>$arr) {
			if (is_array($arr))
				foreach($arr as $caption=>$func) {
					$custom_getters[] = array('open'=>'<a href="javascript:void(0)" onClick="'.Epesi::escapeJS($this->create_callback_href_js(array($mod.'Common',$func['func']),array($f_filename,$row['original'],$row['file_id'])),true,false).';leightbox_deactivate(\''.$lid.'\')">','close'=>'</a>','text'=>$caption,'icon'=>$func['icon']);
				}
		}
//		$custom_getters[] = array('open'=>'<a>','close'=>'</a>','text'=>'tekst','icon'=>'Utils/Attachment/download.png');
		$th->assign('custom_getters',$custom_getters);

		ob_start();
		$th->display('download');
		$c = ob_get_clean();

		Libs_LeightboxCommon::display($lid,$c,__('Attachment'));
		return Libs_LeightboxCommon::get_open_href($lid);
	}

	public function view($id) {
		if($this->is_back()) {
			return $this->pop_box0();
		}

		$row = DB::GetRow('SELECT uaf.id as file_id,ual.permission_by,ual.permission,ual.deleted,ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,uac.created_by as note_by,uac.text,uaf.original,uaf.created_on as upload_on,uaf.created_by as upload_by FROM (utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id) INNER JOIN utils_attachment_file uaf ON uaf.attach_id=ual.id WHERE ual.id=%d AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id)',array($id));

		if(Base_AclCommon::i_am_admin() ||
			$row['permission_by']==Acl::get_user() ||
		   ($row['permission']==0 && $this->public_write) ||
		   ($row['permission']==1 && $this->protected_write) ||
		   ($row['permission']==2 && $this->private_write)) {
		    if(!$row['deleted']) {
    			Base_ActionBarCommon::add('edit',__('Edit'),$this->create_callback_href(array($this,'edit_note_queue'),$id));
	    		Base_ActionBarCommon::add('delete',__('Delete'),$this->create_confirm_callback_href(__('Delete this entry?'),array($this,'delete_back'),$id));
	    	} else {
	    		Base_ActionBarCommon::add('restore',__('Restore'),$this->create_confirm_callback_href(__('Do you want to restore this entry?'),array($this,'restore'),$id));	    	
	    	}
		}

		$th = $this->init_module('Base/Theme');
		$th->assign('header',$this->add_header);
		
		// display images inline

		$th->assign('upload_by',Base_UserCommon::get_user_label($row['upload_by']));
		$th->assign('upload_on',Base_RegionalSettingsCommon::time2reg($row['upload_on']));


		if($row['original']) {
			$inline_img = '';
			$view_link = '';

			$file = $this->get_file($row,$view_link);
			if(preg_match('/\.(jpg|jpeg|gif|png|bmp)$/i',$row['original']) && $view_link)
					$inline_img = '<hr><img src="'.$view_link.'" style="max-width:900px" /><br>';

			$f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'].'_'.$row['file_revision'];
			if(file_exists($f_filename)) {
				$th->assign('file_size',filesize_hr($f_filename));
				$th->assign('file','<a '.$file.'>'.$row['original'].'</a>');
			} else {
				$th->assign('file','');
			}
			$text = Utils_BBCodeCommon::parse($row['text']).$inline_img;
		} else {
			$th->assign('file','');
			$text = Utils_BBCodeCommon::parse($row['text']);
		}
		$th->assign('note',$text);

		Base_ActionBarCommon::add('history',__('Edition history'),$this->create_callback_href(array($this,'edition_history_queue'),$id));
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		if(!$row['deleted'])
    		Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file($this->get_type(),'copy.png'),__('Copy'),$this->create_callback_href(array($this,'copy'),array($id,$text)));

		$th->display('view');

		$this->caption = 'View note';

		return true;
	}

	public function delete_back($id) {
		$this->delete($id);
		$this->set_back_location();
		return false;
	}

	public function edition_history_queue($id) {
		$this->push_box0('edition_history',array($id),array($this->group,$this->persistent_deletion,$this->private_read,$this->private_write,$this->protected_read,$this->protected_write,$this->public_read,$this->public_write,$this->add_header,$this->watchdog_category,$this->watchdog_id,$this->func,$this->args,$this->add_func,$this->add_args,$this->max_file_size));
	}

	public function edition_history($id) {
		if($this->is_back()) {
			return $this->pop_box0();
		}

		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());

		$tb = & $this->init_module('Utils/TabbedBrowser');
		$tb->start_tab('Note history');
		$gb = $this->init_module('Utils/GenericBrowser',null,'hn'.md5(serialize($this->group)));
		$gb->set_inline_display();
		$gb->set_table_columns(array(
				array('name'=>__('Revision'), 'order'=>'uac.revision','width'=>10),
				array('name'=>__('Date'), 'order'=>'note_on','width'=>25),
				array('name'=>__('Who'), 'order'=>'note_by','width'=>25, 'wrapmode'=>'nowrap'),
				array('name'=>__('Note'), 'order'=>'uac.text')
			));
		$gb->set_default_order(array(__('Date')=>'DESC'));

		$ret = $gb->query_order_limit('SELECT ual.permission_by,ual.permission,uac.revision,uac.created_on as note_on,uac.created_by as note_by,uac.text FROM utils_attachment_note uac INNER JOIN utils_attachment_link ual ON ual.id=uac.attach_id WHERE uac.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_note uac WHERE uac.attach_id='.$id);
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			if(Base_AclCommon::i_am_admin() ||
				$row['permission_by']==Acl::get_user() ||
			   ($row['permission']==0 && $this->public_write) ||
			   ($row['permission']==1 && $this->protected_write) ||
			   ($row['permission']==2 && $this->private_write))
				$r->add_action($this->create_confirm_callback_href(__('Do you want to restore note to this version?'),array($this,'restore_note'),array($id,$row['revision'])),'restore');
			$r->add_data($row['revision'],Base_RegionalSettingsCommon::time2reg($row['note_on']),Base_UserCommon::get_user_label($row['note_by']),$row['text']);
		}
		$this->display_module($gb);
		$tb->end_tab();
		$tb->start_tab('File history');
		$gb = $this->init_module('Utils/GenericBrowser',null,'hua'.md5(serialize($this->group)));
		$gb->set_inline_display();
		$gb->set_table_columns(array(
				array('name'=>__('Revision'), 'order'=>'file_revision','width'=>10),
				array('name'=>__('Date'), 'order'=>'upload_on','width'=>25),
				array('name'=>__('Who'), 'order'=>'upload_by','width'=>25),
				array('name'=>__('Attachment'), 'order'=>'uaf.original')
			));
		$gb->set_default_order(array(__('Date')=>'DESC'));

		$ret = $gb->query_order_limit('SELECT uaf.id as file_id,ual.local,ual.permission_by,ual.permission,uaf.attach_id as id,uaf.revision as file_revision,uaf.created_on as upload_on,uaf.created_by as upload_by,uaf.original FROM utils_attachment_file uaf INNER JOIN utils_attachment_link ual ON ual.id=uaf.attach_id WHERE uaf.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_file uaf WHERE uaf.attach_id='.$id);
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			if(Base_AclCommon::i_am_admin() ||
				$row['permission_by']==Acl::get_user() ||
			   ($row['permission']==0 && $this->public_write) ||
			   ($row['permission']==1 && $this->protected_write) ||
			   ($row['permission']==2 && $this->private_write))
				$r->add_action($this->create_confirm_callback_href(__('Do you want to restore attached file to this version?'),array($this,'restore_file'),array($id,$row['file_revision'])),'restore');
			$file = '<a '.$this->get_file($row).'>'.$row['original'].'</a>';
			$r->add_data($row['file_revision'],Base_RegionalSettingsCommon::time2reg($row['upload_on']),Base_UserCommon::get_user_label($row['upload_by']),$file);
		}
		$this->display_module($gb);
		$tb->end_tab();
		$tb->start_tab('File access history');
		$gb = $this->init_module('Utils/GenericBrowser',null,'hda'.md5(serialize($this->group)));
		$gb->set_inline_display();
		$gb->set_table_columns(array(
				array('name'=>__('Create date'), 'order'=>'created_on','width'=>15),
				array('name'=>__('Download date'), 'order'=>'download_on','width'=>15),
				array('name'=>__('Who'), 'order'=>'created_by','width'=>15),
				array('name'=>__('IP Address'), 'order'=>'ip_address', 'width'=>15),
				array('name'=>__('Host Name'), 'order'=>'host_name', 'width'=>15),
				array('name'=>__('Method description'), 'order'=>'description', 'width'=>20),
				array('name'=>__('Revision'), 'order'=>'revision', 'width'=>10),
				array('name'=>__('Remote'), 'order'=>'remote', 'width'=>10),
			));
		$gb->set_default_order(array(__('Create date')=>'DESC'));

		$query = 'SELECT uad.created_on,uad.download_on,(SELECT l.login FROM user_login l WHERE uad.created_by=l.id) as created_by,uad.remote,uad.ip_address,uad.host_name,uad.description,uaf.revision FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id='.$id;
		$query_qty = 'SELECT count(*) FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id='.$id;
		if(Base_AclCommon::check_permission('Attachments - view full download history'))
			$ret = $gb->query_order_limit($query, $query_qty);
		else {
			print('You are allowed to see your own downloads only');
			$who = ' AND uad.created_by='.Acl::get_user();
			$ret = $gb->query_order_limit($query.$who, $query_qty.$who);
		}
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			$r->add_data(Base_RegionalSettingsCommon::time2reg($row['created_on']),($row['remote']!=1?Base_RegionalSettingsCommon::time2reg($row['download_on']):''),$row['created_by'], $row['ip_address'], $row['host_name'], $row['description'], $row['revision'], ($row['remote']==0?'no':'yes'));
		}
		$this->display_module($gb);
		$tb->end_tab();
		$this->display_module($tb);

		$this->caption = 'Note history';

		return true;
	}
	
	public function restore($id) {
		DB::Execute('UPDATE utils_attachment_link SET deleted=0 WHERE id=%d',array($id));
		if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_r_'.$id);
	}

	public function restore_note($id,$rev) {
		DB::StartTrans();
		$text = DB::GetOne('SELECT text FROM utils_attachment_note WHERE attach_id=%d AND revision=%d',array($id,$rev));
		$rev2 = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=%d',array($id));
		DB::Execute('INSERT INTO utils_attachment_note(text,attach_id,revision,created_by) VALUES (%s,%d,%d,%d)',array($text,$id,$rev2+1,Acl::get_user()));
		DB::CompleteTrans();
		if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_r_'.$id);
	}

	public function restore_file($id,$rev) {
		DB::StartTrans();
		$original = DB::GetOne('SELECT original FROM utils_attachment_file WHERE attach_id=%d AND revision=%d',array($id,$rev));
		$rev2 = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=%d',array($id));
		$rev2 = $rev2+1;
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,%d)',array($id,$original,Acl::get_user(),$rev2));
		DB::CompleteTrans();
		$local = $this->get_data_dir().$this->group.'/'.$id.'_';
		copy($local.$rev,$local.$rev2);
		if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_r_'.$id);
	}

	public function pop_box0() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();
	}

	public function push_box0($func,$args,$const_args) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Utils/Attachment',$func,$args,$const_args);
	}

	public function edit_note_queue($id=null) {
		$this->push_box0('edit_note',array($id),array($this->group,$this->persistent_deletion,$this->private_read,$this->private_write,$this->protected_read,$this->protected_write,$this->public_read,$this->public_write,$this->add_header,$this->watchdog_category,$this->watchdog_id,$this->func,$this->args,$this->add_func,$this->add_args,$this->max_file_size));
	}
	
	public function get_edit_form($id=null) {
		$form = $this->init_module('Utils/FileUpload',array(false));
		if(isset($id))
			$form->addElement('header', 'upload', __('Edit note').': '.$this->add_header);
		else
			$form->addElement('header', 'upload', __('Attach note').': '.$this->add_header);
		$fck = $form->addElement('ckeditor', 'note', __('Note'));
		$fck->setFCKProps('99%','200');
		
		$form->addRule('note',__('Maximal lenght of note exceeded'),'maxlength',65535);
		$form->set_upload_button_caption(__('Save'));
		if($form->getSubmitValue('note')=='' && !$form->is_file())
			$form->addRule('note',__('Please enter note or choose file'),'required');

		$form->addElement('select','permission',__('Permission'),array(__('Public'),__('Protected'),__('Private')),array('style'=>'width:auto;'));
		$form->addElement('checkbox','sticky',__('Sticky'));

		if(isset($id))
			$form->addElement('header',null,__('Replace attachment with file'));

		$form->add_upload_element(__('Attachment'));
		
		if($this->max_file_size)
			$form->set_max_file_size($this->max_file_size);

		if(isset($id)) {
			$row = DB::GetRow('SELECT l.sticky,x.text,l.permission FROM utils_attachment_note x INNER JOIN utils_attachment_link l ON l.id=x.attach_id WHERE x.attach_id=%d AND x.revision=(SELECT max(z.revision) FROM utils_attachment_note z WHERE z.attach_id=%d)',array($id,$id));
			$form->setDefaults(array('note'=>$row['text'],'permission'=>$row['permission'],'sticky'=>$row['sticky']));
		} else {
			$form->setDefaults(array('permission'=>Base_User_SettingsCommon::get('Utils_Attachment','default_permission')));
		}

		$this->ret_attach = true;
		return $form;
	}

	public function edit_note($id=null) {
		if(!$this->is_back()) {
			$form = $this->get_edit_form($id);

			Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());
			Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());

			Base_ThemeCommon::load_css('Utils/Attachment', 'view');
			print('<div class="attachment_full_note_edit">');
			if(isset($id)) {
				$row = DB::GetRow('SELECT l.sticky,x.text,l.permission FROM utils_attachment_note x INNER JOIN utils_attachment_link l ON l.id=x.attach_id WHERE x.attach_id=%d AND x.revision=(SELECT max(z.revision) FROM utils_attachment_note z WHERE z.attach_id=%d)',array($id,$id));
				$this->display_module($form, array( array($this,'submit_edit'),$id,$row['text']));
			} else
				$this->display_module($form, array( array($this,'submit_attach') ));
			print('</div>');
		} else {
			$this->ret_attach = false;
		}

		$this->caption = 'Edit note';

		if(!$this->ret_attach)
			return $this->pop_box0();
	}

	public function submit_attach($file,$oryg,$data) {		
		DB::Execute('INSERT INTO utils_attachment_link(local,permission,permission_by,sticky,func,args) VALUES(%s,%d,%d,%b,%s,%s)',array($this->group,$data['permission'],Acl::get_user(),isset($data['sticky']) && $data['sticky'],serialize($this->func),serialize($this->args)));
		$id = DB::Insert_ID('utils_attachment_link','id');
		if($file && trim($data['note'])=='')
			$data['note'] = $oryg;
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$oryg,Acl::get_user()));
		DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,revision) VALUES(%d,%s,%d,0)',array($id,Utils_BBCodeCommon::optimize($data['note']),Acl::get_user()));
		if($file) {
			$local = $this->get_data_dir().$this->group;
			@mkdir($local,0777,true);
			$dest_file = $local.'/'.$id.'_0';
			rename($file,$dest_file);
			if ($this->add_func) call_user_func($this->add_func,$id,0,$dest_file,$oryg,$this->add_args);
		}
		$this->ret_attach = false;
		if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_+_'.$id);
	}

	public function submit_edit($file,$oryg,$data,$id,$text) {
		DB::Execute('UPDATE utils_attachment_link SET sticky=%b,permission=%d,permission_by=%d WHERE id=%d',array(isset($data['sticky']) && $data['sticky'],$data['permission'],Acl::get_user(),$id));
		if($data['note']!=$text) {
			if($file && trim($data['note'])=='')
				$data['note'] = $oryg;
			DB::StartTrans();
			$rev = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=%d',array($id));
			DB::Execute('INSERT INTO utils_attachment_note(text,attach_id,revision,created_by) VALUES (%s,%d,%d,%d)',array(Utils_BBCodeCommon::optimize($data['note']),$id,$rev+1,Acl::get_user()));
			DB::CompleteTrans();
		}
		if($file) {
			DB::StartTrans();
			$rev = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=%d',array($id));
			$rev = $rev+1;
			DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,%d)',array($id,$oryg,Acl::get_user(),$rev));
			DB::CompleteTrans();
			$local = $this->get_data_dir().$this->group;
			@mkdir($local,0777,true);
			$dest_file = $local.'/'.$id.'_'.$rev;
			rename($file,$dest_file);
			if ($this->add_func) call_user_func($this->add_func,$id,$rev,$dest_file,$oryg,$this->add_args);
		}
		$this->ret_attach = false;
		if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_~_'.$id);
	}

	public function delete($id) {
		if($this->persistent_deletion) {
			DB::Execute('DELETE FROM utils_attachment_note WHERE attach_id=%d',array($id));
			$rev = DB::GetAssoc('SELECT id,revision FROM utils_attachment_file WHERE attach_id=%d',array($id));
			$file_base = $this->get_data_dir().$this->group.'/'.$id.'_';
			foreach($rev as $mid=>$i) {
			    @unlink($file_base.$i);
			    DB::Execute('DELETE FROM utils_attachment_download WHERE attach_file_id=%d',array($mid));
			}
			DB::Execute('DELETE FROM utils_attachment_file WHERE attach_id=%d',array($id));
			DB::Execute('DELETE FROM utils_attachment_link WHERE id=%d',array($id));
		} else {
			DB::Execute('UPDATE utils_attachment_link SET deleted=1 WHERE id=%d',array($id));
		}
		if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_-_'.$id);
	}

	public function caption() {
		return $this->caption;
	}
	
	public function enable_watchdog($category, $id) {
		$this->watchdog_category = $category;
		$this->watchdog_id = $id;
	}
	
	public function save_google_docs($note_id) {
		$edit_url = DB::GetOne('SELECT doc_id FROM utils_attachment_googledocs WHERE note_id = %d', array($note_id));
		if (!$edit_url) {
			Base_StatusBarCommon::message(__('Document not found'), 'warning');
			return false;
		}
		$doc = (strpos($edit_url, 'document%3A')!==false);
		if ($doc)
			$export_url = 'https://docs.google.com/feeds/download/documents/Export?docID='.str_replace('https://docs.google.com/feeds/default/private/full/document%3A','',$edit_url).'&exportFormat=doc';
		else
			$export_url = 'https://spreadsheets.google.com/feeds/download/spreadsheets/Export?key='.str_replace('https://docs.google.com/feeds/default/private/full/spreadsheet%3A','',$edit_url).'&exportFormat=csv';
		
		DB::Execute('DELETE FROM utils_attachment_googledocs WHERE note_id = %d', array($note_id));
		$g_auth = Utils_AttachmentCommon::get_google_auth(null, null, $doc?'writely':'wise');
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$headers = array(
			"Authorization: GoogleLogin auth=" . $g_auth,
			"If-Match: *",
			"GData-Version: 3.0",
		);
		curl_setopt($curl, CURLOPT_URL, $export_url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, false);
		$response = curl_exec_follow($curl);

		$row = DB::GetRow('SELECT * FROM utils_attachment_file WHERE id=%d',array($note_id));
		$attach_id = $row['attach_id'];
		$original_name = $row['original'];
		$rev = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=%d',array($attach_id));
		$rev = $rev+1;
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,%d)',array($attach_id,$original_name,Acl::get_user(),$rev));
		DB::CompleteTrans();
		$local = $this->get_data_dir().$this->group;
		@mkdir($local,0777,true);
		$dest_file = $local.'/'.$attach_id.'_'.$rev;
		file_put_contents($dest_file, $response);
		if ($this->add_func) call_user_func($this->add_func,$attach_id,$rev,$dest_file,$original_name,$this->add_args);

		$headers = array(
			"Authorization: GoogleLogin auth=" . $g_auth,
			"If-Match: *",
			"GData-Version: 3.0",
		);
		curl_setopt($curl, CURLOPT_URL, $edit_url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, false);
		$response = curl_exec($curl);

		Base_StatusBarCommon::message(__('Changes saved'));
	}

	public function discard_google_docs($note_id) {
		$edit_url = DB::GetOne('SELECT doc_id FROM utils_attachment_googledocs WHERE note_id = %d', array($note_id));
		DB::Execute('DELETE FROM utils_attachment_googledocs WHERE note_id = %d', array($note_id));
		$g_auth = Utils_AttachmentCommon::get_google_auth();
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$headers = array(
			"Authorization: GoogleLogin auth=" . $g_auth,
			"If-Match: *",
			"GData-Version: 3.0",
		);
		curl_setopt($curl, CURLOPT_URL, $edit_url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, false);
		$response = curl_exec($curl);
		Base_StatusBarCommon::message(__('Changes discarded'));
	}

	public function user_addon($uid) {
		$vd = isset($_SESSION['view_deleted_attachments']) && $_SESSION['view_deleted_attachments'] && Base_AclCommon::i_am_admin();
		//form filtrow
		$form = & $this->init_module('Libs/QuickForm');
	    $form->addElement('text', 'filter_text', __('Search'), array('placeholder'=>__('Keyword').'...'));
		
		$form->addElement('datepicker', 'filter_start', __('Start Date'));
		$form->addElement('datepicker', 'filter_end', __('End Date'));
		
		$form->addElement('submit', 'submit_button', __('Filter'));
	//	$form->display();
		$filter_text = $form->exportValue('filter_text');
		$filter_start = $form->exportValue('filter_start');
		$filter_end = $form->exportValue('filter_end');

		$where = '';
		if($filter_text) 
			$where .= ' AND uac.text '.DB::like().' '.DB::Concat(DB::qstr('%'),DB::qstr($filter_text),DB::qstr('%'));
		if($filter_start)
			$where .= ' AND uac.created_on >= '.DB::qstr($filter_start);
		if($filter_end)
			$where .= ' AND uac.created_on <= '.DB::qstr($filter_end.' 23:59:59');
		if (!$vd)
			$where = ' AND ual.deleted=0'.$where;
		
		
		$gb = $this->init_module('Utils/GenericBrowser',null,'my notes');
		$cols = array();
		if($vd)
			$cols[] = array('name'=>__('Deleted'),'order'=>'ual.deleted','width'=>5);
		$cols[] = array('name'=>__('Source'),'width'=>15, 'wrapmode'=>'nowrap');
		$cols[] = array('name'=>__('Date'), 'order'=>'note_on','width'=>10,'wrapmode'=>'nowrap');
		$cols[] = array('name'=>__('Note'), 'width'=>70);
		$gb->set_table_columns($cols);
		

		$query_from = ' FROM (utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id) INNER JOIN utils_attachment_file uaf ON uaf.attach_id=ual.id WHERE (uac.created_by='.$uid.' OR uaf.created_by='.$uid.') AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id)'.$where;
		$query = 'SELECT ual.sticky,uaf.id as file_id,(SELECT count(*) FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id=ual.id) as downloads,(SELECT l.login FROM user_login l WHERE ual.permission_by=l.id) as permission_owner,ual.permission,ual.permission_by,ual.deleted,ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,uac.created_by as note_by,uac.text,uaf.original,uaf.created_on as upload_on,uaf.created_by as upload_by, ual.func AS search_func, ual.args AS search_func_args'.$query_from;
		$query_lim = 'SELECT count(ual.id)'.$query_from;

		$gb->set_default_order(array(__('Date')=>'DESC'));

		$query_order = $gb->get_query_order('ual.sticky DESC');
		$qty = DB::GetOne($query_lim);
		$query_limits = $gb->get_limit($qty);
		$ret = DB::SelectLimit($query.$query_order,$query_limits['numrows'],$query_limits['offset']);

		Base_ThemeCommon::load_css('Utils_Attachment','browse');
		load_js('modules/Utils/Attachment/js/main.js');
		eval_js('expandable_notes = new Array();');
		eval_js('expandable_notes_amount = 0;');
		eval_js('expanded_notes = 0;');

		eval_js('notes_expand_icon = "'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'expand.gif').'";');
		eval_js('notes_collapse_icon = "'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'collapse.gif').'";');
		eval_js('notes_expand_icon_off = "'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'expand_gray.gif').'";');
		eval_js('notes_collapse_icon_off = "'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'collapse_gray.gif').'";');

		$button_theme = $this->init_module('Base_Theme');

		while($row = $ret->FetchRow()) {
			if(!Base_AclCommon::i_am_admin() && $row['permission_by']!=Acl::get_user()) {
				if($row['permission']==0 && !$this->public_read) continue;//protected
				elseif($row['permission']==1 && !$this->protected_read) continue;//protected
				elseif($row['permission']==2 && !$this->private_read) continue;//private
			}
			$r = $gb->get_new_row();

			$inline_img = '';
			$link_href = '';
			$link_img = '';
			if($row['original']!=='') {
				$f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'].'_'.$row['file_revision'];
				if(file_exists($f_filename)) {
					$filetooltip = __('Filename: %s<br>File size: %s',array($row['original'],filesize_hr($f_filename))).'<hr>'.__('Last uploaded by %s<br>on %s<br>Number of uploads: %d<br>Number of downloads: %d',array(Base_UserCommon::get_user_label($row['upload_by']),Base_RegionalSettingsCommon::time2reg($row['upload_on']),$row['file_revision'],$row['downloads']));
					$view_link = '';
					$link_href = Utils_TooltipCommon::open_tag_attrs($filetooltip).' '.$this->get_file($row,$view_link);
					$link_img = Base_ThemeCommon::get_template_file($this->get_type(),'z-attach.png');
					if(Utils_AttachmentCommon::is_image($row) && $view_link)
						$inline_img = '<hr><a href="'.$view_link.'" target="_blank"><img src="'.$view_link.'" style="max-width:700px" /></a><br>';
				} else {
					$link_href = Utils_TooltipCommon::open_tag_attrs(__('Missing file: %s',array($f_filename)));
					$link_img = Base_ThemeCommon::get_template_file($this->get_type(),'z-attach-off.png');
				}
			}
			if ($link_href)
				$icon = '<a style="margin-right: 3px; float:left;" '.$link_href.'><img src="'.$link_img.'"></a>';
			else
				$icon = '';

			$def_permissions = array(__('Public'),__('Protected'),__('Private'));
			$perm = $def_permissions[$row['permission']];
			$created_on = $row['note_on']>$row['upload_on']?$row['note_on']:$row['upload_on'];
			$note_on = Base_RegionalSettingsCommon::time2reg($created_on,0);
			$note_on_time = Base_RegionalSettingsCommon::time2reg($created_on,1);
			$info = __('Owner: %s',array($row['permission_owner'])).'<br>'.
				__('Permission: %s',array($perm)).'<hr>'.
				__('Last edited by %s<br>on %s<br>Number of edits: %d',array(Base_UserCommon::get_user_label($row['note_by']),$note_on_time,$row['note_revision']));
			$r->add_info($info);
			$r->add_action($this->create_callback_href(array($this,'view_queue'),array($row['id'])),'view');
			$r->add_action($this->create_callback_href(array($this,'edition_history_queue'),$row['id']),'history');

			$text = trim(Utils_BBCodeCommon::parse($row['text']));

			if(!isset($row['deleted']) || !$row['deleted']) {
        		$r->add_action($this->create_callback_href(array($this,'copy'),array($row['id'],$text)),'copy',null,Base_ThemeCommon::get_template_file($this->get_type(),'copy_small.png'), 3);
		    	$r->add_action($this->create_confirm_callback_href(__('Are you sure you want to cut this note?'), array($this,'cut'),array($row['id'],$text)),'cut',null,Base_ThemeCommon::get_template_file($this->get_type(),'cut_small.png'), 4);
		    }
			
			$text = $icon.$text;
			if($row['sticky']) $text = '<img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'sticky.png').'" hspace=3 align="left"> '.$text;

            $r->add_action('style="display:none;" href="javascript:void(0)" onClick="utils_attachment_expand('.$row['id'].')" id="utils_attachment_more_'.$row['id'].'"','Expand', null, Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'plus_gray.png'), 5);
			$r->add_action('style="display:none;" href="javascript:void(0)" onClick="utils_attachment_collapse('.$row['id'].')" id="utils_attachment_less_'.$row['id'].'"','Collapse', null, Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'minus_gray.png'), 5, false, 0);

			$text = '<div style="height:18px;" id="note_'.$row['id'].'" class="note_field">'.$text.$inline_img.'</div>';

			$regional_note_on = $note_on;
			$arr = array();
			if($vd)
				$arr[] = ($row['deleted']?'<a '.$this->create_confirm_callback_href(__('Do you want to restore this entry?'),array($this,'restore'),array($row['id'])).' '.Utils_TooltipCommon::open_tag_attrs(__('Click to restore')).'>'.__('Yes').'</a>':__('No'));
			$callback = unserialize($row['search_func']);
			if (is_callable($callback)) {
				$args = unserialize($row['search_func_args']);
				$arr[] = call_user_func_array($callback, $args);
			} else {
				$arr[] = $row['local'];
			}
			$arr[] = $regional_note_on;
			$arr[] = array('value'=>$text, 'overflow_box'=>false);
			$r->add_data_array($arr);

			eval_js('init_note_expandable('.$row['id'].');');
		}

		$button_theme->assign('expand_collapse',array(
			'e_label'=>__('Expand All'),
			'e_href'=>'href="javascript:void(0);" onClick=\'utils_attachment_expand_all()\'',
			'e_id'=>'expand_all_button',
			'c_label'=>__('Collapse All'),
			'c_href'=>'href="javascript:void(0);" onClick=\'utils_attachment_collapse_all()\'',
			'c_id'=>'collapse_all_button'
		));
		$form->assign_theme('form', $button_theme);

		$custom_label = $this->get_html_of_module($button_theme, array('browse'), 'display');
		$gb->set_custom_label($custom_label, 'style="width:100%;"');
		
		$this->display_module($gb);
	
	}
}

?>
