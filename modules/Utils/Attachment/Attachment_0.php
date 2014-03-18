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

	private $author = true;

	private $add_header = '';
	private $max_file_size = null;

	private $caption = '';
	
	private $watchdog_category;
	private $watchdog_id;
	
	private $func = null;
	private $args = array();


	public function construct($group=null,$pd=null,$header=null,$watchdog_cat=null,$watchdog_id=null,$func=null,$args=null,$max_fs=null) {
		$this->group = & $this->get_module_variable('group',isset($group)?$group:null);
		$this->func = & $this->get_module_variable('func',isset($func)?$func:null);
		$this->args = & $this->get_module_variable('args',isset($args)?$args:null);
		
		if(isset($pd)) $this->persistent_deletion = $pd;
		$this->add_header = & $this->get_module_variable('header',isset($header)?$header:null);
		if(isset($watchdog_cat)) $this->watchdog_category = $watchdog_cat;
		if(isset($watchdog_id)) $this->watchdog_id = $watchdog_id;
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

	public function set_persistent_delete($x=true) {
		$this->persistent_deletion = $x;
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
	
	public function body($arg=null, $rb=null, $uid=null) {
		if(isset($arg) && isset($rb)) {
			$this->group = $rb->tab.'/'.$arg['id'];
			$this->add_header = $rb->caption();
			if(Utils_WatchdogCommon::get_category_id($rb->tab)!==null) {
				$this->watchdog_category = $rb->tab;
				$this->watchdog_id = $arg['id'];
			}
			$this->set_view_func(array('Utils_RecordBrowserCommon','create_default_linked_label'),array($rb->tab, $arg['id']));
		}
		if (!isset($this->group) && !$uid) trigger_error('Key not given to attachment module',E_USER_ERROR);

        load_js('modules/Utils/Attachment/attachments.js');
        Base_ThemeCommon::load_css('Utils_Attachment','browse');

        $this->rb = $this->init_module('Utils/RecordBrowser','utils_attachment','utils_attachment');
        $this->rb->set_defaults(array('permission'=>'0','local'=>$this->group,'func'=>serialize($this->func),'args'=>serialize($this->args),'date'=>time()));
        $this->rb->set_additional_actions_method(array($this,'add_actions'));
        $this->rb->set_header_properties(array(
            'sticky'=>array('width'=>1,'display'=>false),
            'date'=>array('width'=>1),
            'title'=>array('width'=>1),
        ));

        if($uid) {
            $this->display_module($this->rb, array(array(':Created_by'=>$uid), array(), array('sticky'=>'DESC', 'date'=>'DESC')), 'show_data');
        } else {
            $crits = array();
            if(!is_array($this->group)) $this->group = array($this->group);

            if(isset($_SESSION['attachment_copy']) && count($this->group)==1 && $_SESSION['attachment_copy']['group']!=$this->group) {
                $this->rb->new_button(Base_ThemeCommon::get_template_file('Utils/Attachment', 'link.png'),__('Paste'),
                    Utils_TooltipCommon::open_tag_attrs($_SESSION['attachment_copy']['text']).' '.$this->create_callback_href(array($this,'paste'))
                );
            }

            $g = array_map(array('DB','qstr'),$this->group);
            $crits['id'] = DB::GetCol('SELECT attachment FROM utils_attachment_local WHERE local IN ('.implode(',',$g).')');
            $this->display_module($this->rb, array($crits, array(), array('sticky'=>'DESC', 'date'=>'DESC')), 'show_data');
        }

        return;
        //TODO: delete all below after rewrite
		$vd = null;

		if ($uid) {
			$this->group = array();
			$group = 'ual.local AND uac.created_by='.intVal($uid);
			$this->author = false;
		} else {
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
		}
		
		//form filtrow
		$form = $this->init_module('Libs/QuickForm');
		$query = 'SELECT uac.created_by as note_by FROM (utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id) INNER JOIN utils_attachment_file uaf ON uaf.attach_id=ual.id WHERE (false OR ual.local='.$group.') AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.deleted=0 '.($vd?'':'AND ual.deleted=0 ').'GROUP BY uac.created_by';
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
		

		$query_from = ' FROM utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id WHERE (false OR ual.local='.$group.') AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id)'.$where;
		$query = 'SELECT ual.title, ual.crypted, ual.sticky,(SELECT l.login FROM user_login l WHERE ual.permission_by=l.id) as permission_owner,ual.permission,ual.permission_by,ual.deleted,ual.local,uac.revision as note_revision,ual.id,uac.created_on as note_on,uac.created_by as note_by,uac.text,ual.func AS search_func, ual.args AS search_func_args'.$query_from;
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
		if(Utils_RecordBrowserCommon::get_access('utils_attachment','add')) {
			load_js('modules/Utils/Attachment/js/lib/plupload.js');
			load_js('modules/Utils/Attachment/js/lib/plupload.flash.js');
			load_js('modules/Utils/Attachment/js/lib/plupload.browserplus.js');
			load_js('modules/Utils/Attachment/js/lib/plupload.html4.js');
			load_js('modules/Utils/Attachment/js/lib/plupload.html5.js');
			load_js('modules/Utils/Attachment/attachments.js');
			if (!isset($_SESSION['client']['utils_attachment'][CID])) $_SESSION['client']['utils_attachment'][CID] = array('files'=>array());
			eval_js('Utils_Attachment__init_uploader()');
			eval_js_once('var Utils_Attachment__delete_button = "'.Base_ThemeCommon::get_template_file('Utils_Attachment', 'delete.png').'";');
			eval_js_once('var Utils_Attachment__restore_button = "'.Base_ThemeCommon::get_template_file('Utils_Attachment', 'restore.png').'";');
			
			$attachButtons = '<div id="multiple_attachments"><a class="button" id="pickfiles" href="javascript:void(0);">'.__('Select files').'</a>'.'<div id="filelist"></div></div>';

			if (!is_array($this->group))
				$button_theme->assign('new_note',array(
					'label'=>__('Attach'),
					'href'=>'href="javascript:void(0);" onclick=\'utils_attachment_add_note();\''
				));

			$r = $gb->get_new_row();
			$new_note_form = $this->get_edit_form();
			
			eval_js('Utils_Attachment__submit_note = function() {'.$new_note_form->get_submit_form_js().'}');
			$new_note_form->addElement('hidden', 'note_id', null, array('id'=>'note_id'));
			$new_note_form->addElement('hidden', 'delete_files', null, array('id'=>'delete_files'));
			$new_note_form->addElement('hidden', 'clipboard_files', null, array('id'=>'clipboard_files'));
			$new_note_form->addElement('button', 'save', __('Save note'), array('class'=>'button', 'onclick'=>'if(uploader.files.length)uploader.start();else Utils_Attachment__submit_note();'));
			$new_note_form->addElement('button', 'cancel', __('Cancel'), array('class'=>'button', 'onclick'=>'utils_attachments_cancel_note_edit();'));

            $form_error = false;
			if ($new_note_form->validate()) {
				$new_note_form->process(array($this, 'submit_attach'));
				location(array());
				return;
			} elseif($new_note_form->isSubmitted()) {
                $form_error = true;
            }

			$renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
			$new_note_form->accept($renderer); 
			$form_data = $renderer->toArray();

			//$gb->set_prefix($form_data['javascript'].'<form '.$form_data['attributes'].'>'.$form_data['hidden']);
			//$gb->set_postfix('</form>');

			$inline_form_theme = $this->init_module('Base_Theme');
			$inline_form_theme->assign('form', $form_data);
            $inline_form_theme->assign('form_open',$form_data['javascript'].'<form '.$form_data['attributes'].'>'.$form_data['hidden']);
            $inline_form_theme->assign('form_close','</form>');
			ob_start();
			$inline_form_theme->display('inline_form');
			$fields = ob_get_clean();

			$arr = array();
			$arr[] = array('value'=>$attachButtons, 'overflow_box'=>false, 'attrs'=>'colspan="2"');
			$arr[] = array('value'=>$fields, 'overflow_box'=>false, 'attrs'=>'colspan="1" notearea="1"');
			if ($vd)
				$arr[] = array('value'=>'', 'overflow_box'=>false, 'style'=>'display:none;');
			if ($this->author)
				$arr[] = array('value'=>'', 'overflow_box'=>false, 'style'=>'display:none;');
			if (is_array($this->group))
				$arr[] = array('value'=>'', 'overflow_box'=>false, 'style'=>'display:none;');

			$r->set_attrs('id="attachments_new_note" style="display:none;"');
			
			$r->add_data_array($arr);

			if(Base_AclCommon::i_am_admin()) {
				$button_theme->assign('show_deleted',array(
					'label'=>__('Show deleted notes'),
					'default'=>($vd?'checked="1"':''),
					'show'=>$this->create_callback_href_js(array($this,'show_deleted'),array(true)),
					'hide'=>$this->create_callback_href_js(array($this,'show_deleted'),array(false))
				));
			}
			$col_span = $vd?5:4;
			eval_js('init_expand_note_space();');
            if($form_error)
                eval_js('setTimeout(function(){utils_attachment_add_note(0);},100);');
        }

		while($row = $ret->FetchRow()) {
			//TODO: delete/replace??
            /*
            if(!Base_AclCommon::i_am_admin() && $row['permission_by']!=Acl::get_user()) {
				if($row['permission']==0 && !$this->public_read) continue;//protected
				elseif($row['permission']==1 && !$this->protected_read) continue;//protected
				elseif($row['permission']==2 && !$this->private_read) continue;//private
			}
            */
			$r = $gb->get_new_row();
			$r->set_attrs('id="attachments_note_'.$row['id'].'"');

			/*$inline_img = '';
			$link_href = '';
			$link_img = '';
			$icon = '';
			$files = DB::GetAll('SELECT id, created_by, created_on, original, (SELECT count(*) FROM utils_attachment_download uad WHERE uaf.id=uad.attach_file_id) as downloads FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($row['id']));
			foreach ($files as $f) {
				$f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$f['id'];
				if(file_exists($f_filename)) {
					$filename = $f['original'];
					$filetooltip = __('Filename: %s',array($filename)).'<br>'.__('File size: %s',array(filesize_hr($f_filename))).'<hr>'.
						__('Last uploaded by %s', array(Base_UserCommon::get_user_label($f['created_by'], true))).'<br/>'.
						__('On: %s',array(Base_RegionalSettingsCommon::time2reg($f['created_on']))).'<br/>'.
						__('Number of downloads: %d',array($f['downloads']));
					$view_link = '';
					$f['local'] = $row['local'];
                    $f['crypted'] = $row['crypted'];
					$link_href = Utils_TooltipCommon::open_tag_attrs($filetooltip).' '.$this->get_file($f,$view_link);
					$link_img = Base_ThemeCommon::get_template_file($this->get_type(),'z-attach.png');
					if(Utils_AttachmentCommon::is_image($filename) && $view_link)
						$inline_img .= '<hr><a href="'.$view_link.'" target="_blank"><img src="'.$view_link.'" style="max-width:700px" /></a><br>';
				} else {
					$filename = __('Missing file: %s',array($f_filename));
					$link_href = Utils_TooltipCommon::open_tag_attrs($filename);
					$link_img = Base_ThemeCommon::get_template_file($this->get_type(),'z-attach-off.png');
				}
				if ($link_href)
					$icon .= '<span class="file_link"><a '.$link_href.'><img src="'.$link_img.'"><span class="file_name">'.$filename.'</span></a></span>';
			}*/

			$def_permissions = array(__('Public'),__('Protected'),__('Private'));
			$perm = $def_permissions[$row['permission']];
			$created_on = $row['note_on'];
			$note_on = Base_RegionalSettingsCommon::time2reg($created_on,0);
			$note_on_time = Base_RegionalSettingsCommon::time2reg($created_on,1);
			$info = __('Owner: %s',array($row['permission_owner'])).'<br>'.
				__('Permission: %s',array($perm)).'<hr>'.
				__('Last edited by: %s',array(Base_UserCommon::get_user_label($row['note_by']))).'<br/>'.
				__('On: %s',array($note_on_time)).'<br/>'.
				__('Number of edits: %d',array($row['note_revision']));
			$r->add_info($info);

            if($row['crypted']) {
                $text = false;
                if($this->isset_module_variable('cp'.$row['id'])) {
                    $note_pass = $this->get_module_variable('cp'.$row['id']);
                    $decoded = Utils_AttachmentCommon::decrypt($row['text'],$note_pass);
                    if($decoded!==false) $text = $decoded;
                }
                if($text===false) {
                    $cf = $this->init_module('Libs_QuickForm',null,'crypted_pass_'.$row['id']);
                    $cf->set_inline_display();
                    //$cf->addElement('header',null,__('Note encrypted'));
                    $cf->addElement('password','note_password_decode',__('Password'));
                    $cf->addElement('submit','submit',__('Submit'));
                    if($cf->validate()) {
                        $note_pass = $cf->exportValue('note_password_decode');
                        $decoded = Utils_AttachmentCommon::decrypt($row['text'],$note_pass);
                        if($decoded!==false) {
                            $text = $decoded;
                            $this->set_module_variable('cp'.$row['id'],$note_pass);
                        } else Epesi::alert(__('Invalid password'));
                    }
                    if($text===false) {
                        $text = '<div style="color:red">'.__('Note encrypted').'</div>'.$this->get_html_of_module($cf);
                        $icon = '';
                        $files = array();
                    }
                }
            } else {
                $text = $row['text'];
            }

            if(!$row['crypted'] || $this->isset_module_variable('cp'.$row['id'])) {
                if(Base_AclCommon::i_am_admin() ||
                    $row['created_by']==Acl::get_user() /*||
                    ($row['permission']==0 && $this->public_write) ||
                    ($row['permission']==1 && $this->protected_write) ||
                    ($row['permission']==2 && $this->private_write)*/) {
                    if(!isset($row['deleted']) || !$row['deleted']) {
                        $r->add_action('href="javascript:void(0);" onclick="utils_attachment_edit_note('.$row['id'].',\''.Epesi::escapeJS($this->get_path()).'\')"','edit');
                        $r->add_action($this->create_confirm_callback_href(__('Delete this entry?'),array($this,'delete'),$row['id']),'delete');
                    } else {
                        $r->add_action('','edit',__('You cannot edit deleted notes'),null,0,true);
                        $r->add_action($this->create_confirm_callback_href(__('Do you want to restore this entry?'),array($this,'restore'),$row['id']),'restore', null,null, -1);
                    }
                }
                $r->add_action($this->create_callback_href(array($this,'edition_history_queue'),$row['id']),'history');

                if(!isset($row['deleted']) || !$row['deleted']) {
                    $r->add_action($this->create_callback_href(array($this,'copy'),array($row['id'],$text, $row['local'])),'copy',null,Base_ThemeCommon::get_template_file($this->get_type(),'copy_small.png'), 3);
                    $r->add_action($this->create_confirm_callback_href(__('Are you sure you want to cut this note?'), array($this,'cut'),array($row['id'],$text, $row['local'])),'cut',null,Base_ThemeCommon::get_template_file($this->get_type(),'cut_small.png'), 4);
                }
            }

            if($row['title']) $text = '<strong>'.$row['title'].'</strong><hr/>'.$text;
			/*$text = trim(Utils_BBCodeCommon::parse($text));
			if (!$text && $inline_img) $text = '<br/>';

			$text = $icon.$text;
			if($row['sticky']) $text = '<img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'sticky.png').'" hspace=3 align="left"> '.$text;
			*/

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

			eval_js('init_note_expandable('.$row['id'].', false, '.(empty($files)?0:1).');');
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
		
		print('<div class="Utils_Attachment__table">');
		$this->display_module($gb);
		print('</div>');
	}

    public function add_actions($row,$r,$rb) {
        if(($row['crypted'] && !isset($_SESSION['client']['cp'.$row['id']])) || count($this->group)!=1) return;
        $text = $row['note'];
        if($row['crypted'])
            $text = Utils_AttachmentCommon::decrypt($text,$_SESSION['client']['cp'.$row['id']]);
        $r->add_action($this->create_callback_href(array($this,'copy'),array($row['id'],$text, $this->group)),'copy',null,Base_ThemeCommon::get_template_file($this->get_type(),'copy_small.png'), 3);
        $r->add_action($this->create_confirm_callback_href(__('Are you sure you want to cut this note?'), array($this,'cut'),array($row['id'],$text, $this->group)),'cut',null,Base_ThemeCommon::get_template_file($this->get_type(),'cut_small.png'), 4);
    }

	public function copy($id, $text, $group) {
	 	$_SESSION['attachment_copy'] = array('id'=>$id, 'group'=>$group,'text'=>$text);
        $_SESSION['attachment_cut'] = 0;
	}

	public function cut($id, $text, $group) {
	 	$_SESSION['attachment_copy'] = array('id'=>$id, 'group'=>$group,'text'=>$text);
	 	$_SESSION['attachment_cut'] = 1;
	}

	public function paste() {
        $group = array_shift($this->group);
        reset($this->group);
        if(DB::GetOne('SELECT 1 FROM utils_attachment_local WHERE attachment=%d AND local=%s',array($_SESSION['attachment_copy']['id'],$group))) return;
		if(isset($_SESSION['attachment_cut']) && $_SESSION['attachment_cut']) {
            DB::Execute('UPDATE utils_attachment_local SET local=%s WHERE attachment=%d AND local=%s',array($group, $_SESSION['attachment_copy']['id'],array_shift($_SESSION['attachment_copy']['group'])));
            unset($_SESSION['attachment_cut']);
            unset($_SESSION['attachment_copy']);
		} else {
            DB::Execute('INSERT INTO utils_attachment_local(local,attachment) VALUES(%s,%d)',array($group,$_SESSION['attachment_copy']['id']));
		}
	}

/*
	public function delete_back($id) {
		$this->delete($id);
		$this->set_back_location();
		return false;
	}
*/
/*	public function edition_history_queue($id) {
		$this->push_box0('edition_history',array($id,$this->get_module_variable('cp'.$id)),array($this->group,$this->persistent_deletion,$this->add_header,$this->watchdog_category,$this->watchdog_id,$this->func,$this->args,$this->max_file_size));
	}

	public function edition_history($id,$pass) {
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

		$ret = $gb->query_order_limit('SELECT ual.crypted,ual.permission_by,ual.permission,uac.revision,uac.created_on as note_on,uac.created_by as note_by,uac.text FROM utils_attachment_note uac INNER JOIN utils_attachment_link ual ON ual.id=uac.attach_id WHERE uac.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_note uac WHERE uac.attach_id='.$id);
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			if(Base_AclCommon::i_am_admin() ||
				$row['created_by']==Acl::get_user())
				$r->add_action($this->create_confirm_callback_href(__('Do you want to restore note to this version?'),array($this,'restore_note'),array($id,$row['revision'])),'restore',__('Restore'));
			$r->add_data($row['revision'],Base_RegionalSettingsCommon::time2reg($row['note_on']),Base_UserCommon::get_user_label($row['note_by']),$row['crypted']?Utils_AttachmentCommon::decrypt($row['text'],$pass):$row['text']);
		}
		$this->display_module($gb);
		$tb->end_tab();
		$tb->start_tab('File history');
		$gb = $this->init_module('Utils/GenericBrowser',null,'hua'.md5(serialize($this->group)));
		$gb->set_inline_display();
		$gb->set_table_columns(array(
				array('name'=>__('Deleted'), 'order'=>'deleted','width'=>10),
				array('name'=>__('Date'), 'order'=>'upload_on','width'=>25),
				array('name'=>__('Who'), 'order'=>'upload_by','width'=>25),
				array('name'=>__('Attachment'), 'order'=>'uaf.original')
			));
		$gb->set_default_order(array(__('Date')=>'DESC'));

		$ret = $gb->query_order_limit('SELECT uaf.id as file_id,ual.local,ual.permission_by,ual.permission,ual.crypted,uaf.attach_id as id,uaf.deleted,uaf.created_on as upload_on,uaf.created_by as upload_by,uaf.original FROM utils_attachment_file uaf INNER JOIN utils_attachment_link ual ON ual.id=uaf.attach_id WHERE uaf.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_file uaf WHERE uaf.attach_id='.$id);
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			if(Base_AclCommon::i_am_admin() ||
				$row['permission_by']==Acl::get_user())
				if ($row['deleted']) $r->add_action($this->create_confirm_callback_href(__('Are you sure you want to restore attached file?'),array($this,'restore_file'),array($row['file_id'])),'restore',__('Restore'));
			$file = '<a '.$this->get_file($row).'>'.$row['original'].'</a>';
			$r->add_data($row['deleted']?__('Yes'):__('No'),Base_RegionalSettingsCommon::time2reg($row['upload_on']),Base_UserCommon::get_user_label($row['upload_by']),$file);
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
				array('name'=>__('Remote'), 'order'=>'remote', 'width'=>10),
			));
		$gb->set_default_order(array(__('Create date')=>'DESC'));

		$query = 'SELECT uad.created_on,uad.download_on,(SELECT l.login FROM user_login l WHERE uad.created_by=l.id) as created_by,uad.remote,uad.ip_address,uad.host_name,uad.description FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id='.$id;
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
			$r->add_data(Base_RegionalSettingsCommon::time2reg($row['created_on']),($row['remote']!=1?Base_RegionalSettingsCommon::time2reg($row['download_on']):''),$row['created_by'], $row['ip_address'], $row['host_name'], $row['description'], ($row['remote']==0?'no':'yes'));
		}
		$this->display_module($gb);
		$tb->end_tab();
		$this->display_module($tb);

		$this->caption = 'Note history';

		return true;
	}
*/
    public function file_history($attachment) {
        if($this->is_back()) {
            $x = ModuleManager::get_instance('/Base_Box|0');
            if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
            return $x->pop_main();
        }

        Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());

        $id = $attachment['id'];

        $tb = & $this->init_module('Utils/TabbedBrowser');
        $tb->start_tab('File history');
        $gb = $this->init_module('Utils/GenericBrowser',null,'hua'.$id);
        $gb->set_inline_display();
        $gb->set_table_columns(array(
            array('name'=>__('Deleted'), 'order'=>'deleted','width'=>10),
            array('name'=>__('Date'), 'order'=>'upload_on','width'=>25),
            array('name'=>__('Who'), 'order'=>'upload_by','width'=>25),
            array('name'=>__('Attachment'), 'order'=>'uaf.original')
        ));
        $gb->set_default_order(array(__('Date')=>'DESC'));

        $ret = $gb->query_order_limit('SELECT uaf.id,uaf.deleted,uaf.created_on as upload_on,uaf.created_by as upload_by,uaf.original FROM utils_attachment_file uaf WHERE uaf.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_file uaf WHERE uaf.attach_id='.$id);
        while($row = $ret->FetchRow()) {
            $r = $gb->get_new_row();
            if ($row['deleted']) $r->add_action($this->create_confirm_callback_href(__('Are you sure you want to restore attached file?'),array($this,'restore_file'),array($row['id'])),'restore',__('Restore'));
            $view_link = '';
            $lb = array();
            $lb['aid'] = $id;
            $lb['crypted'] = $attachment['crypted'];
            $lb['original'] = $row['original'];
            $lb['id'] = $row['id'];
            $file = '<a '.Utils_AttachmentCommon::get_file_leightbox($lb,$view_link).'>'.$row['original'].'</a>';
            $r->add_data($row['deleted']?__('Yes'):__('No'),Base_RegionalSettingsCommon::time2reg($row['upload_on']),Base_UserCommon::get_user_label($row['upload_by']),$file);
        }
        $this->display_module($gb);
        $tb->end_tab();
        $tb->start_tab('File access history');
        $gb = $this->init_module('Utils/GenericBrowser',null,'hda'.$id);
        $gb->set_inline_display();
        $gb->set_table_columns(array(
            array('name'=>__('Create date'), 'order'=>'created_on','width'=>15),
            array('name'=>__('Download date'), 'order'=>'download_on','width'=>15),
            array('name'=>__('Who'), 'order'=>'created_by','width'=>15),
            array('name'=>__('IP Address'), 'order'=>'ip_address', 'width'=>15),
            array('name'=>__('Host Name'), 'order'=>'host_name', 'width'=>15),
            array('name'=>__('Method description'), 'order'=>'description', 'width'=>20),
            array('name'=>__('Remote'), 'order'=>'remote', 'width'=>10),
        ));
        $gb->set_default_order(array(__('Create date')=>'DESC'));

        $query = 'SELECT uad.created_on,uad.download_on,(SELECT l.login FROM user_login l WHERE uad.created_by=l.id) as created_by,uad.remote,uad.ip_address,uad.host_name,uad.description FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id='.$id;
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
            $r->add_data(Base_RegionalSettingsCommon::time2reg($row['created_on']),($row['remote']!=1?Base_RegionalSettingsCommon::time2reg($row['download_on']):''),$row['created_by'], $row['ip_address'], $row['host_name'], $row['description'], ($row['remote']==0?'no':'yes'));
        }
        $this->display_module($gb);
        $tb->end_tab();
        $this->display_module($tb);

        $this->caption = 'Note history';

        return true;
    }
	
/*	public function restore($id) {
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
*/
	public function restore_file($id) {
		DB::Execute('UPDATE utils_attachment_file SET deleted=0 WHERE id=%d',array($id));
		return false;
	}

/*	public function pop_box0() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();
	}

	public function push_box0($func,$args,$const_args) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Utils/Attachment',$func,$args,$const_args);
	}

	public function get_edit_form($id=null) {
		$form = $this->init_module('Libs/QuickForm',array(false),'note_edit_form');

        if(isset($id)) {
			$form->addElement('header', 'upload', __('Edit note').': '.$this->add_header);
            $row = DB::GetRow('SELECT l.title,l.sticky,l.crypt,x.text,l.permission FROM utils_attachment_note x INNER JOIN utils_attachment_link l ON l.id=x.attach_id WHERE x.attach_id=%d AND x.revision=(SELECT max(z.revision) FROM utils_attachment_note z WHERE z.attach_id=%d)',array($id,$id));
        } else {
			$form->addElement('header', 'upload', __('Attach note').': '.$this->add_header);
            $row = array();
        }

        if(isset($id)) {
            $form->setDefaults(array('note'=>$row['text'],'permission'=>$row['permission'],'sticky'=>$row['sticky'],'crypted'=>$row['crypted'],'note_title'=>$row['title']));
        } else {
            $form->setDefaults(array('permission'=>Base_User_SettingsCommon::get('Utils_Attachment','default_permission')));
        }

        $form->addElement('text','note_title',__('Title'), array('id'=>'note_title'));
        $form->addRule('note_title',__('Maximal lenght of title exceeded'),'maxlength',255);

		$fck = $form->addElement('ckeditor', 'note', __('Note'));
		$fck->setFCKProps('99%','200');

		$form->addRule('note',__('Maximal lenght of note exceeded'),'maxlength',65535);

		$form->addElement('select','permission',__('Permission'),array(__('Public'),__('Protected'),__('Private')),array('style'=>'width:auto;', 'id'=>'note_permission'));
		$form->addElement('checkbox','sticky',__('Sticky'), null, array('id'=>'note_sticky'));
        if(extension_loaded('mcrypt')) {
            $form->addElement('checkbox','crypted',__('Encrypt'), null, array('id'=>'note_crypted','onChange'=>'this.form.elements["note_password"].disabled=this.form.elements["note_password2"].disabled=!this.checked;'));
            $form->addElement('password','note_password',__('Password'), array('id'=>'note_password'));
            $form->addElement('password','note_password2',__('Confirm Password'), array('id'=>'note_password2'));
            $crypted = $form->exportValue('crypted');
            if(!$crypted) eval_js('$("note_password").disabled=1;$("note_password2").disabled=1;');
            $form->addRule(array('note_password', 'note_password2'), __('Your passwords don\'t match'), 'compare');
        }

		if(isset($id))
			$form->addElement('header',null,__('Replace attachment with file'));

		$this->ret_attach = true;
		return $form;
	}

	public function submit_attach($data) {		
		$files = $_SESSION['client']['utils_attachment'][CID]['files'];
		$_SESSION['client']['utils_attachment'][CID]['files'] = array();
		$clipboard_files = trim($data['clipboard_files'], ';');
		if ($clipboard_files) $clipboard_files = explode(';', $clipboard_files);
		else $clipboard_files = array();

        $note = $data['note'] = Utils_BBCodeCommon::optimize($data['note']);

        if(!isset($data['note_password'])) $data['note_password']='';

        $old_pass = $this->get_module_variable('cp'.$data['note_id'],false);
        if($data['note_password']=='*@#old@#*')
            $data['note_password'] = $old_pass;
        if($data['note_password']===false) $data['note_password'] = '';

        $crypted = isset($data['crypted']) && $data['crypted'];

        //no password? no encryption!
        if($crypted && $data['note_password']==='') {
            $crypted=0;
            Epesi::alert(__('Note cannot be encrypted with empty password. Saving unencrypted.'));
        }

        if($old_pass!=$data['note_password'] && is_numeric($data['note_id'])) {
            //reencrypt old revisions
            $old_notes = DB::GetAssoc('SELECT id,text FROM utils_attachment_note WHERE attach_id=%d', array($data['note_id']));
            foreach($old_notes as $old_id=>$old_note) {
                if($old_pass!==false) $old_note = Utils_AttachmentCommon::decrypt($old_note,$old_pass);
                if($old_note===false) continue;
                if($crypted && $data['note_password']) $old_note = Utils_AttachmentCommon::encrypt($old_note,$data['note_password']);
                if($old_note===false) continue;
                DB::Execute('UPDATE utils_attachment_note SET text=%s WHERE id=%d',array($old_note,$old_id));
            }
            //file reencryption
            $old_files = DB::GetCol('SELECT uaf.id as id FROM utils_attachment_file uaf WHERE uaf.attach_id=%d',array($data['note_id']));
            foreach($old_files as $old_id) {
                $filename = DATA_DIR.'/Utils_Attachment/'.$this->group.'/'.$old_id;
                $content = @file_get_contents($filename);
                if($content===false) continue;
                if($old_pass!==false) $content = Utils_AttachmentCommon::decrypt($content,$old_pass);
                if($content===false) continue;
                if($crypted && $data['note_password']) $content = Utils_AttachmentCommon::encrypt($content,$data['note_password']);
                if($content===false) continue;
                file_put_contents($filename,$content);
            }
        }

        if($crypted) {
            $note = Utils_AttachmentCommon::encrypt($note,$data['note_password']);
        }

        if (is_numeric($data['note_id'])) {
			$current_files = DB::GetAssoc('SELECT id, id FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($data['note_id']));
			$remaining_files = $current_files;
			$deleted_files = trim($data['delete_files'], ';');
			if ($deleted_files) $deleted_files = explode(';', $deleted_files);
			else $deleted_files = array();
			foreach ($deleted_files as $k=>$v) {
				$deleted_files[$k] = intVal($v);
				if (!isset($remaining_files[$v])) unset($deleted_files[$k]);
				else unset($remaining_files[$v]);
			}
			if (empty($clipboard_files) && empty($remaining_files) && empty($files) && !$data['note']) {
				Base_StatusBarCommon::message(__('Unable to create empty note'), 'warning');
				return;
			}
			$note_id = $data['note_id'];
			$old = DB::GetRow('SELECT text,ual.crypted FROM utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id WHERE uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND ual.id=%d', array($note_id));
            if($old['crypted']) $old['text'] = Utils_AttachmentCommon::decrypt($old['text'],$this->get_module_variable('cp'.$note_id));
			DB::Execute('UPDATE utils_attachment_link SET title=%s,sticky=%b,crypted=%b,permission=%d,permission_by=%d WHERE id=%d',array($data['note_title'],isset($data['sticky']) && $data['sticky'],$crypted,$data['permission'],Acl::get_user(),$note_id));
			if($data['note']!=$old['text'] || $data['note_password'] != $this->get_module_variable('cp'.$note_id)) {
				DB::StartTrans();
				$rev = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=%d',array($note_id));
				DB::Execute('INSERT INTO utils_attachment_note(text,attach_id,revision,created_by) VALUES (%s,%d,%d,%d)',array($note,$note_id,$rev+1,Acl::get_user()));
				DB::CompleteTrans();
			}
			foreach ($deleted_files as $v)
				DB::Execute('UPDATE utils_attachment_file SET deleted=1 WHERE id=%d', array($v));
		} else {
			if (empty($clipboard_files) && empty($files) && !$data['note']) {
				Base_StatusBarCommon::message(__('Unable to create empty note'), 'warning');
				return;
			}
			$note_id = Utils_AttachmentCommon::add($this->group,$data['permission'],Acl::get_user(),$note,null,null,$this->func,$this->args,null,array(),isset($data['sticky']) && $data['sticky'],$data['note_title'],$crypted);
		}
		foreach ($clipboard_files as $cf_id) {
			$cf = DB::GetOne('SELECT filename FROM utils_attachment_clipboard WHERE id=%d', array($cf_id));
			Utils_AttachmentCommon::add_file($note_id, Acl::get_user(), __('clipboard').'.png', $cf);
		}
		foreach ($files as $f) {
            if($crypted)
                file_put_contents($f,Utils_AttachmentCommon::encrypt(file_get_contents($f),$data['note_password']));
			Utils_AttachmentCommon::add_file($note_id, Acl::get_user(), basename($f), $f);
        }
        if($crypted) {
            $this->set_module_variable('cp'.$note_id,$data['note_password']);
        } else {
            $this->unset_module_variable('cp'.$note_id);
        }
		$this->ret_attach = false;
	}
*/
    public function caption() {
		return $this->caption;
	}

    public function enable_watchdog($category, $id) {
		$this->watchdog_category = $category;
		$this->watchdog_id = $id;
	}


/*    public function delete($id) {
        if($this->persistent_deletion) {
            DB::Execute('DELETE FROM utils_attachment_note WHERE attach_id=%d',array($id));
            $mids = DB::GetCol('SELECT id FROM utils_attachment_file WHERE attach_id=%d',array($id));
            foreach($mids as $mid) {
                $file_base = $this->get_data_dir().$this->group.'/'.$mid;
                @unlink($file_base);
                DB::Execute('DELETE FROM utils_attachment_download WHERE attach_file_id=%d',array($mid));
            }
            DB::Execute('DELETE FROM utils_attachment_file WHERE attach_id=%d',array($id));
            DB::Execute('DELETE FROM utils_attachment_link WHERE id=%d',array($id));
        } else {
            DB::Execute('UPDATE utils_attachment_link SET deleted=1 WHERE id=%d',array($id));
        }
        if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_-_'.$id);
    }
*/
	public function user_addon($uid) {
		$this->body(null, null, $uid);
	}
	
/*	public function add_note() {
		if(!$this->is_back()) {
			load_js('modules/Utils/Attachment/js/lib/plupload.js');
			load_js('modules/Utils/Attachment/js/lib/plupload.flash.js');
			load_js('modules/Utils/Attachment/js/lib/plupload.browserplus.js');
			load_js('modules/Utils/Attachment/js/lib/plupload.html4.js');
			load_js('modules/Utils/Attachment/js/lib/plupload.html5.js');
			load_js('modules/Utils/Attachment/attachments.js');
			if (!isset($_SESSION['client']['utils_attachment'][CID])) $_SESSION['client']['utils_attachment'][CID] = array('files'=>array());
			eval_js('Utils_Attachment__init_uploader()');
			eval_js_once('var Utils_Attachment__delete_button = "'.Base_ThemeCommon::get_template_file('Utils_Attachment', 'delete.png').'";');
			eval_js_once('var Utils_Attachment__restore_button = "'.Base_ThemeCommon::get_template_file('Utils_Attachment', 'restore.png').'";');
			
			$attachButtons = '<div id="multiple_attachments"><div id="filelist"></div></div>';

			if (!is_array($this->group))
    			Base_ActionBarCommon::add('add',__('Select files'),'href="javascript:void(0);" id="pickfiles"');

			$new_note_form = $this->get_edit_form();
			
			eval_js('Utils_Attachment__submit_note = function() {'.$new_note_form->get_submit_form_js().'}');
			$new_note_form->addElement('hidden', 'note_id', null, array('id'=>'note_id'));
			$new_note_form->addElement('hidden', 'delete_files', null, array('id'=>'delete_files'));
			$new_note_form->addElement('hidden', 'clipboard_files', null, array('id'=>'clipboard_files'));
			
			if ($new_note_form->validate()) {
				$new_note_form->process(array($this, 'submit_attach'));
				$this->ret_attach = false;
				return $this->pop_box0();
			}
			
			$renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
			$new_note_form->accept($renderer); 
			$form_data = $renderer->toArray();

			print($form_data['javascript'].'<form '.$form_data['attributes'].'>'.$form_data['hidden']);

			$inline_form_theme = $this->init_module('Base_Theme');
			$inline_form_theme->assign('form', $form_data);
			$inline_form_theme->display('inline_form');
			
			print($attachButtons);
			print('</form>');

			Base_ActionBarCommon::add('save',__('Save'),'onclick="if(uploader.files.length)uploader.start();else Utils_Attachment__submit_note();"');
			Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		} else {
			$this->ret_attach = false;
		}

		$this->caption = __('Add note');

		if(!$this->ret_attach)
			return $this->pop_box0();
	}


	public function add_note_queue() {
		$this->push_box0('add_note',array(),array($this->group,$this->persistent_deletion,$this->add_header,$this->watchdog_category,$this->watchdog_id,$this->func,$this->args,$this->max_file_size));
	}*/
}

?>
