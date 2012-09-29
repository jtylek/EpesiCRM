<?php
/**
 * RecordBrowserCommon class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

defined("_VALID_ACCESS") || die();

class Utils_RecordBrowser extends Module {
    private $table_rows = array();
    private $browse_mode;
    private $display_callback_table = array();
    private $QFfield_callback_table = array();
    private $requires = array();
    private $recent = 0;
    private $caption = '';
    private $icon = '';
    private $favorites = false;
    private $full_history = true;
    private $crits = array();
    private $noneditable_fields = array();
    private $add_button = null;
    private $more_add_button_stuff = '';
    private $changed_view = false;
    private $is_on_main_page = false;
    private $multiple_defaults = false;
    private $add_in_table = false;
    private $custom_filters = array();
    private $default_order = array();
    private $more_table_properties = array();
    private $fullscreen_table = false;
    private $amount_of_records = 0;
    private $switch_to_addon = 0;
    private $additional_caption = '';
    private $enable_export = false;
	private $search_calculated_callback = false;
	private $fields_in_tabs = array();
	private $hide_tab = array();
    public $action = 'Browsing';
    public $custom_defaults = array();
    public static $admin_filter = '';
    public static $tab_param = '';
    public static $clone_result = null;
    public static $clone_tab = null;
    public static $last_record = null;
    public static $rb_obj = null;
    public $record;
    public $adv_search = false;
    private $col_order = array();
    private $advanced = array();
    public static $browsed_records = null;
    public static $access_override = array('tab'=>'', 'id'=>'');
    public static $mode = 'view';
    private $navigation_executed = false;
    private $current_field = null;
    private $additional_actions_method = null;
    private $filter_crits = array();
    private $disabled = array('search'=>false, 'browse_mode'=>false, 'watchdog'=>false, 'quickjump'=>false, 'filters'=>false, 'headline'=>false, 'actions'=>false, 'fav'=>false, 'pdf'=>false, 'export'=>false);
    private $force_order;
    private $clipboard_pattern = false;
    private $show_add_in_table = false;
    private $data_gb = null;
    public $view_fields_permission;
    public $form = null;
    public $tab;
    public $grid = null;
	
	public function new_button($type, $label, $href) {
		if ($this->fullscreen_table)
			Base_ActionBarCommon::add($type, $label, $href);
		else {
			if (!file_exists($type))
				$type = Base_ThemeCommon::get_template_file('Base/ActionBar', 'icons/'.$type.'.png');
			$this->more_add_button_stuff .= '<a class="record_browser_button" id="Base_ActionBar" '.$href.'>'.'<img src="'.$type.'">'.$label.'</a>';
		}
	}
	
    public function enable_grid($arg) {
        $this->grid = $arg;
    }

    public function set_filter_crits($field, $crits) {
        $this->filter_crits[$field] = $crits;
    }

    public function switch_to_addon($arg) {
        $this->switch_to_addon = $arg;
    }

    public function hide_tab($tab) {
        $this->hide_tab[$tab] = true;
    }

    public function get_custom_defaults(){
        return $this->custom_defaults;
    }

    public function get_final_crits() {
        if (!$this->displayed()) trigger_error('You need to call display_module() before calling get_final_crits() method.', E_USER_ERROR);
        return $this->get_module_variable('crits_stuff');
    }

    public function enable_export($arg) {
        $this->enable_export = $arg;
    }

    public function set_additional_caption($arg) {
        $this->additional_caption = $arg;
    }

    public function get_display_method($ar) {
        return isset($this->display_callback_table[$ar])?$this->display_callback_table[$ar]:null;
    }
    
    public function get_qffield_method($field) {
        return isset($this->QFfield_callback_table[$field]) ? $this->QFfield_callback_table[$field] : null;
    }

    public function set_additional_actions_method($callback) {
        $this->additional_actions_method = $callback;
    }

    public function set_table_column_order($arg) {
        $this->col_order = $arg;
    }
	
	public function set_search_calculated_callback($callback) {
		$this->search_calculated_callback = $callback;
	}

    public function get_val($field, $record, $links_not_recommended = false, $args = null) {
        return Utils_RecordBrowserCommon::get_val($this->tab, $field, $record, $links_not_recommended, $args);
    }

    public function disable_search(){$this->disabled['search'] = true;}
    public function disable_browse_mode_switch(){$this->disabled['browse_mode'] = true;}
    public function disable_watchdog(){$this->disabled['watchdog'] = true;}
    public function disable_fav(){$this->disabled['fav'] = true;}
    public function disable_filters(){$this->disabled['filters'] = true;}
    public function disable_quickjump(){$this->disabled['quickjump'] = true;}
    public function disable_headline() {$this->disabled['headline'] = true;}
    public function disable_pdf() {$this->disabled['pdf'] = true;}
    public function disable_export() {$this->disabled['export'] = true;}
    public function disable_actions($arg=true) {$this->disabled['actions'] = $arg;}

    public function set_button($arg, $arg2=''){
        $this->add_button = $arg;
        $this->more_add_button_stuff = $arg2;
    }

    public function set_header_properties($ar) {
        $this->more_table_properties = $ar;
    }

    public function get_access($action, $param=null){
        return Utils_RecordBrowserCommon::get_access($this->tab, $action, $param);
    }

    public function construct($tab = null) {
		Utils_RecordBrowserCommon::$options_limit = Base_User_SettingsCommon::get('Utils_RecordBrowser','enable_autocomplete');
        self::$rb_obj = $this;
        $this->tab = & $this->get_module_variable('tab', $tab);
        if ($this->tab!==null) Utils_RecordBrowserCommon::check_table_name($this->tab);
		load_js('modules/Utils/RecordBrowser/main.js');
    }

    public function init($admin=false, $force=false) {
        $params = DB::GetRow('SELECT caption, icon, recent, favorites, full_history FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
        if ($params==false) trigger_error('There is no such recordSet as '.$this->tab.'.', E_USER_ERROR);
        list($this->caption,$this->icon,$this->recent,$this->favorites,$this->full_history) = $params;
        $this->favorites &= !$this->disabled['fav'];
        $this->watchdog = Utils_WatchdogCommon::category_exists($this->tab) && !$this->disabled['watchdog'];
        $this->clipboard_pattern = Utils_RecordBrowserCommon::get_clipboard_pattern($this->tab);

        //If Caption or icon not specified assign default values
        if ($this->caption=='') $this->caption='Record Browser';
        if ($this->icon=='') $this->icon = Base_ThemeCommon::get_template_filename('Base_ActionBar','icons/settings.png');
        $this->icon = Base_ThemeCommon::get_template_dir().$this->icon;

        $this->table_rows = Utils_RecordBrowserCommon::init($this->tab, $admin, $force);
        $this->requires = array();
        $this->display_callback_table = array();
        $this->QFfield_callback_table = array();
        $ret = DB::Execute('SELECT * FROM '.$this->tab.'_callback');
        while ($row = $ret->FetchRow())
            if ($row['freezed']==1) $this->display_callback_table[$row['field']] = explode('::',$row['callback']);
            else $this->QFfield_callback_table[$row['field']] = explode('::',$row['callback']);
    }

    public function check_for_jump() {
        $x = Utils_RecordBrowserCommon::check_for_jump();
        if($x)
            self::$browsed_records = $this->get_module_variable('set_browsed_records',null);
        return $x;
    }
	
	public function jump_new_note($key=null) {
		if ($key==null) $key = $this->tab.'/'.$this->record['id'];
		$a = $this->init_module('Utils_Attachment',array($key));
		$a->set_view_func(array('Utils_RecordBrowserCommon','create_default_linked_label'),explode('/',$key));
		$a->edit_note_queue();
	}
	
	public function add_note_button_href($key=null) {
		return $this->create_callback_href(array($this, 'jump_new_note'), array($key));
	}
	
	public function add_note_button($key=null) {
		$href = $this->add_note_button_href($key);
		return '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Note')).' '.$href.'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_Attachment','icon_small.png').'"></a>';
	}
    // BODY //////////////////////////////////////////////////////////////////////////////////////////////////////
    public function body($def_order=array(), $crits=array(), $cols=array(), $filters_set=array()) {
        unset($_SESSION['client']['recordbrowser']['admin_access']);
        if ($this->check_for_jump()) return;
        $this->fullscreen_table=true;
        $this->init();
        if (self::$clone_result!==null) {
            if (is_numeric(self::$clone_result)) $this->navigate('view_entry', 'view', self::$clone_result);
            $clone_result = self::$clone_result;
            self::$clone_result = null;
            if ($clone_result!='canceled') return;
        }
        if ($this->get_access('browse')===false) {
            print(__('You are not authorised to browse this data.'));
            return;
        }
//        if ($this->watchdog) Utils_WatchdogCommon::add_actionbar_change_subscription_button($this->tab); // Leave it out for now
        $this->is_on_main_page = true;

        $this->data_gb = $this->init_module('Utils/GenericBrowser', null, $this->tab);

        if (!$this->disabled['filters']) $filters = $this->show_filters($filters_set);
        else $filters = '';

        if ($this->get_access('add',$this->custom_defaults)!==false && $this->add_button!==false) {
            if (!$this->multiple_defaults) {
                if ($this->add_button===null) {
                    Base_ActionBarCommon::add('add',__('New'), $this->create_callback_href(array($this,'navigate'),array('view_entry', 'add', null, $this->custom_defaults)));
                    Utils_ShortcutCommon::add(array('Ctrl','N'), 'function(){'.$this->create_callback_href_js(array($this,'navigate'),array('view_entry', 'add', null, $this->custom_defaults)).'}');
                } elseif($this->add_button!=='') {
                    Base_ActionBarCommon::add('add',__('New'), $this->add_button);
                }
            } else {
                Base_ActionBarCommon::add('add',__('New'), Utils_RecordBrowserCommon::create_new_record_href($this->tab,$this->custom_defaults,'multi',true,true));
            }
        }

        $this->crits = $this->crits+$crits;

        $theme = $this->init_module('Base/Theme');
        $theme->assign('filters', $filters);
        $opts = array('all'=>__('All'));
        if ($this->recent>0) $opts['recent'] = __('Recent');
        if ($this->favorites) $opts['favorites'] = __('Favorites');
        if ($this->watchdog) $opts['watchdog'] = __('Watched');
		
		if ($this->data_gb->show_all()) {
			$this->set_module_variable('browse_mode', 'all');
		}

        if (count($opts)>1) {
            if ($this->disabled['browse_mode'])
                $this->browse_mode='all';
            else {
                $this->browse_mode = $this->get_module_variable('browse_mode', Base_User_SettingsCommon::get('Utils/RecordBrowser',$this->tab.'_default_view'));
                if (!$this->browse_mode) $this->browse_mode='all';
                if (($this->browse_mode=='recent' && $this->recent==0) || ($this->browse_mode=='favorites' && !$this->favorites)) $this->set_module_variable('browse_mode', $this->browse_mode='all');
                $form = $this->init_module('Libs/QuickForm');
                $form->addElement('select', 'browse_mode', '', $opts, array('onchange'=>$form->get_submit_form_js()));
                $form->setDefaults(array('browse_mode'=>$this->browse_mode));
                if ($form->validate()) {
                    $vals = $form->exportValues();
                    if (isset($opts[$vals['browse_mode']])) {
                        $this->switch_view($vals['browse_mode']);
                        location(array());
                        return;
                    }
                }
                $form->assign_theme('form', $theme);
            }
        }

        ob_start();
        $this->show_data($this->crits, $cols, array_merge($def_order, $this->default_order));
        $table = ob_get_contents();
        ob_end_clean();

        $theme->assign('table', $table);
        if (!$this->disabled['headline']) $theme->assign('caption', _V($this->caption).($this->additional_caption?' - '.$this->additional_caption:'').' '.$this->get_jump_to_id_button());
        $theme->assign('icon', $this->icon);
        $theme->display('Browsing_records');
    }
    public function switch_view($mode){
        Base_User_SettingsCommon::save('Utils/RecordBrowser',$this->tab.'_default_view',$mode);
        $this->browse_mode = $mode;
        $this->changed_view = true;
        $this->set_module_variable('browse_mode', $mode);
    }

    //////////////////////////////////////////////////////////////////////////////////////////
    public function show_filters($filters_set = array(), $f_id='') {
        $this->init();
        if ($this->get_access('browse')===false) {
            return;
        }
        $filters_all = array();
        foreach ($this->table_rows as $k=>$v) {
            if ((!isset($filters_set[$v['id']]) && $v['filter']) || (isset($filters_set[$v['id']]) && $filters_set[$v['id']])) {
                $filters_all[] = $k;
                if (isset($filters_set[$v['id']])) unset($filters_set[$v['id']]);
            }
        }
        if (!$this->data_gb) $this->data_gb = $this->init_module('Utils/GenericBrowser', null, $this->tab);
        if (empty($filters_all)) {
            $this->crits = array();
            return '';
        } // TODO: move it
        $form = $this->init_module('Libs/QuickForm', null, $this->tab.'filters');

//        $form_sub = $form->get_submit_form_js_by_name(array($form->get_name(), $this->data_gb->form_s->get_name()),true,null)."return false;";
//        $this->data_gb->form_s->updateAttributes(array('onsubmit'=>$form_sub));
//	      $form->updateAttributes(array('onsubmit'=>$form_sub));

		$empty_defaults = array();
        $filters = array();
        $text_filters = array();
        foreach ($filters_all as $filter) {
            $filter_id = preg_replace('/[^a-z0-9]/','_',strtolower($filter));
            $field_id = 'filter__'.$filter_id;
            if (isset($this->custom_filters[$filter_id])) {
                $f = $this->custom_filters[$filter_id];
				if ($this->data_gb->show_all()) {
					if (isset($f['trans'])) {
						foreach ($f['trans'] as $k=>$v)
							if (empty($v)) $empty_defaults[$field_id] = $k;
					}
				}
                if (!isset($f['label'])) $f['label'] = $filter;
                if (!isset($f['args'])) $f['args'] = null;
                if (!isset($f['args_2'])) $f['args_2'] = null;
                if (!isset($f['args_3'])) $f['args_3'] = null;
                $form->addElement($f['type'], $field_id, $f['label'], $f['args'], $f['args_2'], $f['args_3']);
                $filters[] = $filter_id;
                continue;
            }
            $arr = array();
            if ($this->table_rows[$filter]['type']=='timestamp' || $this->table_rows[$filter]['type']=='date') {
				$form->addElement('datepicker', $field_id.'__from', _V($filter).' ('.__('From').')', array('label'=>false)); // TRSL
				$form->addElement('datepicker', $field_id.'__to', _V($filter).' ('.__('To').')', array('label'=>false)); // TRSL
				$filters[] = $filter_id.'__from';
				$filters[] = $filter_id.'__to';
				continue;
            }
			if ($this->table_rows[$filter]['type']=='checkbox') {
                $arr = array(''=>__('No'), 1=>__('Yes'));
            } else {
                if ($this->table_rows[$filter]['type'] == 'commondata') {
					$parts = explode('::', $this->table_rows[$filter]['param']['array_id']);
					$array_id = array_shift($parts);
					$arr = Utils_CommonDataCommon::get_translated_array($array_id, $this->table_rows[$filter]['param']['order_by_key']);
					while (!empty($parts)) {
						array_shift($parts);
						$next_arr = array();
						foreach ($arr as $k=>$v) {
							$next = Utils_CommonDataCommon::get_translated_array($array_id.'/'.$k, $this->table_rows[$filter]['param']['order_by_key']);
							foreach ($next as $k2=>$v2)
								$next_arr[$k.'/'.$k2] = $v.' / '.$v2;
						}
						$arr = $next_arr;
					}
                    natcasesort($arr);
                } else {
                    $param = explode(';',$this->table_rows[$filter]['param']);
                    $x = explode('::',$param[0]);
                    if (!isset($x[1])) continue;
                    list($tab, $col) = $x;
                    if ($tab=='__COMMON__') {
                        $arr = Utils_CommonDataCommon::get_translated_tree($col);
                    } else {
                        $col = explode('|',$col);
                        Utils_RecordBrowserCommon::check_table_name($tab);
                        foreach ($col as $k=>$v)
                            $col[$k] = preg_replace('/[^a-z0-9]/','_',strtolower($v));
                        $crits = array();
                        if (isset($this->filter_crits[$this->table_rows[$filter]['id']])) {
                            $crits = $this->filter_crits[$this->table_rows[$filter]['id']];
                        } else {
                            if (isset($param[1])) {
                                $callback = explode('::',$param[1]);
                                if (is_callable($callback))
                                    $crits = call_user_func($callback, true);
                            }
                            if (!is_array($crits)) {
                                $crits = array();
                                if (isset($param[2])) {
                                    $callback = explode('::',$param[2]);
                                    if (is_callable($callback))
                                        $crits = call_user_func($callback, true);
                                }
                                if (!is_array($crits)) $crits = array();
                            }
                        }
                        $ret2 = Utils_RecordBrowserCommon::get_records($tab,$crits,$col);
                        if (count($col)!=1) {
                            foreach ($ret2 as $k=>$v) {
                                $txt = array();
                                foreach ($col as $kk=>$vv)
                                    $txt[] = $v[$vv];
                                $arr[$k] = implode(' ',$txt);
                            }
                        } else {
                            foreach ($ret2 as $k=>$v) {
                                $arr[$k] = $v[$col[0]];
                            }
                        }
                        natcasesort($arr);
						if (isset($x[0]) && $x[0]=='contact') $arr = array($this->crm_perspective_default()=>'['.__('Perspective').']')+$arr;
                    }
                }
            }
            $arr = array('__NULL__'=>'---')+$arr;
            $form->addElement('select', $field_id, _V($filter), $arr); // TRSL
            $filters[] = $filter_id;
        }
        $form->addElement('submit', 'submit', __('Show'));

		if ($this->data_gb->show_all()) {
			$this->set_module_variable('def_filter', $empty_defaults);
			print('<span style="display:none;">'.microtime(true).'</span>');
		}
        $def_filt = $this->get_module_variable('def_filter', array());

        $this->crits = array();

        $form->setDefaults($def_filt);

        $external_filters = array();

        $dont_hide = Base_User_SettingsCommon::get('Utils/RecordBrowser',$this->tab.'_show_filters');

        $ret = DB::Execute('SELECT * FROM recordbrowser_browse_mode_definitions WHERE tab=%s', array($this->tab));
        while ($row = $ret->FetchRow()) {
            $m = $this->init_module($row['module']);
            $next_dont_hide = false; // FIXME deprecated, to be removed
            $this->display_module($m, array(& $form, & $external_filters, & $vals, & $this->crits, & $next_dont_hide, $this), $row['func']);
        }

        $vals = $form->exportValues();

        foreach ($filters_all as $filter) {
            if (in_array(strtolower($filter), $external_filters)) continue;
            $filter_id = preg_replace('/[^a-z0-9]/','_',strtolower($filter));
            $field_id = 'filter__'.$filter_id;
            if (isset($this->custom_filters[$filter_id])) {
                if (!isset($vals['filter__'.$filter_id])) {
					if ($this->custom_filters[$filter_id]['type']!='autoselect')
						$vals['filter__'.$filter_id]='__NULL__';
					else
						$vals['filter__'.$filter_id]='';
				}
                if (isset($this->custom_filters[$filter_id]['trans'][$vals['filter__'.$filter_id]])) {
                    foreach($this->custom_filters[$filter_id]['trans'][$vals['filter__'.$filter_id]] as $k=>$v)
                        $this->crits[$k] = $v;
                } elseif (isset($this->custom_filters[$filter_id]['trans_callback'])) {
                    $new_crits = call_user_func($this->custom_filters[$filter_id]['trans_callback'], $vals['filter__'.$filter_id], $filter_id);
                    $this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $new_crits);
                }
            } else {
				if ($this->table_rows[$filter]['type']=='timestamp' || $this->table_rows[$filter]['type']=='date') {
					if (isset($vals[$field_id.'__from']) && $vals[$field_id.'__from'])
						$this->crits['>='.$filter_id] = $vals[$field_id.'__from'].' 00:00:00';
					if (isset($vals[$field_id.'__to']) && $vals[$field_id.'__to'])
						$this->crits['<='.$filter_id] = $vals[$field_id.'__to'].' 23:59:59';
					continue;
				}
                if (!isset($text_filters[$filter_id])) {
                    if (!isset($vals['filter__'.$filter_id])) $vals['filter__'.$filter_id]='__NULL__';
					if ($vals['filter__'.$filter_id]==='__NULL__') continue;
					if ($this->table_rows[$filter]['type']=='commondata') {
						$vals2 = explode('/',$vals['filter__'.$filter_id]);
						$param = explode('::',$this->table_rows[$filter]['param']['array_id']);
						array_shift($param);
						$param[] = $filter_id;
						foreach ($vals2 as $v)
							$this->crits[preg_replace('/[^a-z0-9]/','_',strtolower(array_shift($param)))] = $v;
					} else {
						$this->crits[$filter_id] = $vals['filter__'.$filter_id];
					}
                } else {
                    if (!isset($vals['filter__'.$filter_id])) $vals['filter__'.$filter_id]='';
                    if ($vals['filter__'.$filter_id]!=='') {
                        $args = $this->table_rows[$filter];
                        $str = explode(';', $args['param']);
                        $ref = explode('::', $str[0]);
                        if ($ref[0]!='' && isset($ref[1])) $this->crits['_"'.$args['id'].'['.$args['ref_field'].']'] = DB::Concat(DB::qstr($vals['filter__'.$filter_id]),DB::qstr('%'));;
                        if ($args['type']=='commondata' || $ref[0]=='__COMMON__') {
							$val = array_pop(explode('/',$vals['filter__'.$filter_id]));
                            if (!isset($ref[1]) || $ref[0]=='__COMMON__') $this->crits['_"'.$args['id'].'['.$args['ref_field'].']'] = DB::Concat(DB::qstr($val),DB::qstr('%'));;
                        }
                    }
                }
            }
        }
		foreach ($this->crits as $k=>$c) if ($c===$this->crm_perspective_default()) {
			$this->crits[$k] = explode(',',trim(CRM_FiltersCommon::get(),'()'));
			if (isset($this->crits[$k][0]) && $this->crits[$k][0]=='') unset($this->crits[$k]);
		}

        $this->set_module_variable('crits', $this->crits);

        $filters = array_merge($filters, $external_filters);

        foreach ($filters as $k=>$v)
            $filters[$k] = 'filter__'.$v;

        foreach ($vals as $k=>$v) {
            $c = str_replace('filter__','',$k);
            if (isset($this->custom_filters[$c]) && $this->custom_filters[$c]['type']=='checkbox' && $v==='__NULL__') unset($vals[$k]);
        }
        $this->set_module_variable('def_filter', $vals);
        $theme = $this->init_module('Base/Theme');
        $form->assign_theme('form',$theme);
        $theme->assign('filters', $filters);
		load_js('modules/Utils/RecordBrowser/filters.js');
        $theme->assign('show_filters', array('attrs'=>'onclick="rb_show_filters(\''.$this->tab.'\',\''.$f_id.'\');" id="show_filter_b_'.$f_id.'"','label'=>__('Show filters')));
        $theme->assign('hide_filters', array('attrs'=>'onclick="rb_hide_filters(\''.$this->tab.'\',\''.$f_id.'\');" id="hide_filter_b_'.$f_id.'"','label'=>__('Hide filters')));
        $theme->assign('id', $f_id);
        if (!$this->isset_module_variable('filters_defaults'))
            $this->set_module_variable('filters_defaults', $this->crits);
        elseif ($this->crits!==$this->get_module_variable('filters_defaults')) $theme->assign('dont_hide', true);
        if ($dont_hide) $theme->assign('dont_hide', true);
        return $this->get_html_of_module($theme, 'Filter', 'display');
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function navigate($func){
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
        $args = func_get_args();
        array_shift($args);
        $x->push_main('Utils/RecordBrowser',$func,$args,array(self::$clone_result!==null?self::$clone_tab:$this->tab),md5($this->get_path()).'_r');
        $this->navigation_executed = true;
        return false;
    }
    public function back(){
        $x = ModuleManager::get_instance('/Base_Box|0');
        if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
        return $x->pop_main();
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function show_data($crits = array(), $cols = array(), $order = array(), $admin = false, $special = false, $pdf = false) {
		$this->help('RecordBrowser','main');
		if (Utils_RecordBrowserCommon::$admin_access) $admin = true;
        if (isset($_SESSION['client']['recordbrowser']['admin_access'])) Utils_RecordBrowserCommon::$admin_access = true;
        if ($this->check_for_jump()) return;
        Utils_RecordBrowserCommon::$cols_order = $this->col_order;
        if ($this->get_access('browse')===false) {
            print(__('You are not authorised to browse this data.'));
            return;
        }

        $this->init();
        $this->action = 'Browse';
        if (!Base_AclCommon::i_am_admin() && $admin) {
            print(__('You don\'t have permission to access this data.'));
        }
        if ($this->data_gb!==null) $gb = $this->data_gb;
        else $gb = $this->init_module('Utils/GenericBrowser', null, $this->tab);
		
        if ($special) {
            $gb_per_page = Base_User_SettingsCommon::get('Utils/GenericBrowser','per_page');
            $gb->set_per_page(Base_User_SettingsCommon::get('Utils/RecordBrowser/RecordPicker','per_page'));
        }
        if (!$this->disabled['search']) {
            $gb->is_adv_search_on();
            $is_searching = $gb->get_module_variable('search','');
            if (!empty($is_searching)) {
                if ($this->get_module_variable('browse_mode')!='all'
//                  || $gb->get_module_variable('quickjump_to')!=null
                    ) {
                    $this->set_module_variable('browse_mode','all');
//                  $gb->set_module_variable('quickjump_to',null);
                    location(array());
                    return;
                }
            }
        }

        if ($special) {
            $table_columns = array(array('name'=>__('Select'), 'width'=>'40px'));
        } else {
            $table_columns = array();
            if (!$pdf && !$admin && $this->favorites) {
                $fav = array('name'=>'&nbsp;', 'width'=>'24px');
                if (!isset($this->force_order)) $fav['order'] = ':Fav';
                $table_columns[] = $fav;
            }
            if (!$pdf && !$admin && $this->watchdog)
                $table_columns[] = array('name'=>'', 'width'=>'24px');
        }
        if (!$this->disabled['quickjump']) $quickjump = DB::GetOne('SELECT quickjump FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
        else $quickjump = '';

        $hash = array();
        $query_cols = array();
        foreach($this->table_rows as $field => $args) {
            $hash[$args['id']] = $field;
            if ($field === 'id') continue;
            if ((!$args['visible'] && (!isset($cols[$args['id']]) || $cols[$args['id']] === false))) continue;
            if (isset($cols[$args['id']]) && $cols[$args['id']] === false) continue;
            $query_cols[] = $args['id'];
            $arr = array('name'=>$args['name']);
            if (!$pdf && !isset($this->force_order) && $this->browse_mode!='recent' && $args['type']!=='multiselect' && ($args['type']!=='calculated' || $args['param']!='') && $args['type']!=='hidden') $arr['order'] = $field;
            if ($args['type']=='checkbox' || (($args['type']=='date' || $args['type']=='timestamp' || $args['type']=='time') && !$this->add_in_table) || $args['type']=='commondata') {
                $arr['wrapmode'] = 'nowrap';
                $arr['width'] = 50;
            } else {
                $arr['width'] = 100;
			}
            $arr['name'] = _V($arr['name']); // ****** Translate field name for table header
            if (isset($this->more_table_properties[$args['id']])) {
                foreach (array('name','wrapmode','width') as $v) if (isset($this->more_table_properties[$args['id']][$v])) {
                    if (is_numeric($this->more_table_properties[$args['id']][$v]) && $v=='width') $this->more_table_properties[$args['id']][$v] = $this->more_table_properties[$args['id']][$v]*10;
                    $arr[$v] = $this->more_table_properties[$args['id']][$v];
                }
            }
            if (is_array($args['param']))
                $str = explode(';', $args['param']['array_id']);
            else
                $str = explode(';', $args['param']);
            $ref = explode('::', $str[0]);
            $each = array();
            if (!$pdf && $quickjump!=='' && $args['name']===$quickjump) $each[] = 'quickjump';
            if (!$pdf && !$this->disabled['search']) $each[] = 'search';
            foreach ($each as $e) {
                if ($args['type']=='text' || $args['type']=='currency' || ($args['type']=='calculated' && preg_match('/^[a-z]+(\([0-9]+\))?$/i',$args['param'])!==0)) $arr[$e] = $args['id'];
				
                if (isset($args['ref_field'])) $arr[$e] = $args['id'];
                if ($args['commondata'] && (!is_array($args['param']) || strpos($args['param']['array_id'],':')===false)) {
                    $arr[$e] = $args['id'];
                }
            }
            if (isset($arr['quickjump'])) $arr['quickjump'] = '"~'.$arr['quickjump'];
			if ($pdf) {
				$arr['attrs'] = 'style="border:1px solid black;font-weight:bold;text-align:center;color:white;background-color:gray"';
				if (!isset($arr['width'])) $arr['width'] = 100;
				if ($arr['width']==1) $arr['width'] = 100;
			}
            $table_columns[] = $arr;
        }
		if ($pdf) {
			$max = 0;
			$width_sum = 0;
			foreach ($table_columns as $k=>$v)
				if ($v['width']>$max) $max = $v['width'];
			foreach ($table_columns as $k=>$v) {
				$table_columns[$k]['width'] = intval($table_columns[$k]['width']);
				if ($table_columns[$k]['width']<$max/2) $table_columns[$k]['width'] = $max/2;
				$width_sum += $table_columns[$k]['width'];
			}
			$fraction = 0;
			foreach ($table_columns as $k=>$v) {
				$table_columns[$k]['width'] = floor(100*$v['width']/$width_sum);
				$fraction += 100*$v['width']/$width_sum - $table_columns[$k]['width'];
				if ($fraction>1) {
					$table_columns[$k]['width'] += 1;
					$fraction -= 1;
				}
				$table_columns[$k]['width'] = $table_columns[$k]['width'].'%';
			}
		}
		
		$gb->set_table_columns( $table_columns );
		
		if (!$pdf) {
			$clean_order = array();
			foreach ($order as $k => $v) {
				if(!in_array($k,$query_cols)) continue;
				if (isset($this->more_table_properties[$k]) && isset($this->more_table_properties[$k]['name'])) $key = $this->more_table_properties[$k]['name'];
				elseif (isset($hash[$k])) $key = $hash[$k];
				else $key = $k;
   				$clean_order[_V($key)] = $v; // TRSL
			}

			if ($this->browse_mode != 'recent')
				$gb->set_default_order($clean_order, $this->changed_view);
		}

        $search = $gb->get_search_query(true);
        $search_res = array();
		if ($this->search_calculated_callback) {
			$search_res = call_user_func($this->search_calculated_callback, $search);
		}
        if ($gb->is_adv_search_on()) {
            foreach ($search as $k=>$v) {
				$f_id = str_replace(array('"','~'),'',$k);
				$args = $this->table_rows[$hash[$f_id]];
				if ($args['commondata']) $k = $k.'[]';
				elseif (isset($args['ref_field'])) $k = $k.'['.Utils_RecordBrowserCommon::get_field_id($args['ref_field']).']';
                if ($k[0]=='"') {
                    $search_res['~_'.$k] = $v;
                    continue;
                }
                if (is_array($v)) $v = $v[0];
                $v = explode(' ', $v);
                foreach ($v as $w) {
					if (!$args['commondata']) {
						$w = DB::Concat(DB::qstr('%'),DB::qstr($w),DB::qstr('%'));
						$op = '"';
					} else {
						$op = '';
					}
                    $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, array('~'.$op.$k =>$w));
				}
            }
        } else {
            $val = reset($search);
            $isearch = $gb->get_module_variable('search');
            if (empty($isearch)) $val = null;
            $val2 = explode(' ', $val[0]);
            $leftovers = array();
            foreach ($val2 as $vv) {
                foreach ($search as $k=>$v) {
                    if ($v!=$val) {
                        $leftovers[$k] = $v;
                        continue;
                    }
                    if ($k[0]=='"') {
                        $search_res['~_'.$k] = $vv;
                        continue;
                    }
					$args = $this->table_rows[$hash[trim($k, '(|')]];
					if ($args['commondata']) $k = $k.'[]';
					elseif (isset($args['ref_field'])) $k = $k.'['.Utils_RecordBrowserCommon::get_field_id($args['ref_field']).']';
					if (!$args['commondata']) {
						$w = DB::Concat(DB::qstr('%'),DB::qstr($vv),DB::qstr('%'));
						$op = '"';
					} else {
						$w = $vv;
						$op = '';
					}
                    $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, array('~'.$op.$k =>$w));
                }
            }
            $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, $leftovers);
        }

        if (!$pdf) $order = $gb->get_order();
        $crits = array_merge($crits, $search_res);
        if ($this->browse_mode == 'favorites')
            $crits[':Fav'] = true;
        if ($this->browse_mode == 'watchdog')
            $crits[':Sub'] = true;
        if ($this->browse_mode == 'recent') {
            $crits[':Recent'] = true;
            $order = array(':Visited_on'=>'DESC');
        }

        if ($admin) {
            $order = array(':Edited_on'=>'DESC');
            $form = $this->init_module('Libs/QuickForm', null, $this->tab.'_admin_filter');
            $form->addElement('select', 'show_records', __('Show records'), array(0=>'['.__('All').']',1=>'['.__('All active').']',2=>'['.__('All deactivated').']'));
            $form->addElement('submit', 'submit', __('Show'));
            $f = $this->get_module_variable('admin_filter', 0);
            $form->setDefaults(array('show_records'=>$f));
            self::$admin_filter = $form->exportValue('show_records');
            $this->set_module_variable('admin_filter', self::$admin_filter);
            if (self::$admin_filter==0) self::$admin_filter = '';
            if (self::$admin_filter==1) self::$admin_filter = 'active=1 AND ';
            if (self::$admin_filter==2) self::$admin_filter = 'active=0 AND ';
            $form->display_as_row();
        }
        if (isset($this->force_order)) $order = $this->force_order;
        if (!$order) $order = array();

        $this->amount_of_records = Utils_RecordBrowserCommon::get_records_count($this->tab, $crits, $admin, $order);

		$key = md5(serialize($this->tab).serialize($crits).serialize($cols).serialize($order).serialize($admin));
		if (!$this->disabled['pdf'] && !$pdf && $this->amount_of_records<200 && $this->get_access('print')) {
			$this->new_button('print', __('Print'), 'href="modules/Utils/RecordBrowser/print.php?'.http_build_query(array('key'=>$key, 'cid'=>CID)).'" target="_blank"');
		}
		$_SESSION['client']['utils_recordbrowser'][$key] = array(
			'tab'=>$this->tab,
			'crits'=>$crits,
			'cols'=>$cols,
			'order'=>$order,
			'admin'=>$admin,
			'more_table_properties'=>$this->more_table_properties
		);

        if ($pdf) $limit = null;
		else $limit = $gb->get_limit($this->amount_of_records);
        $records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, array(), $order, $limit, $admin);

        if (($this->get_access('export') || $this->enable_export) && !$this->disabled['export'])
            $this->new_button('save',__('Export'), 'href="modules/Utils/RecordBrowser/csv_export.php?'.http_build_query(array('tab'=>$this->tab, 'admin'=>$admin, 'cid'=>CID, 'path'=>$this->get_path())).'"');

        $this->set_module_variable('crits_stuff',$crits?$crits:array());
        $this->set_module_variable('order_stuff',$order?$order:array());

        $custom_label = '';
        if (!$pdf && !$special && $this->get_access('add',$this->custom_defaults)!==false) {
            if ($this->add_button!==null) $label = $this->add_button;
            elseif (!$this->multiple_defaults) $label = $this->create_callback_href(array($this, 'navigate'), array('view_entry', 'add', null, $this->custom_defaults));
            else $label = Utils_RecordBrowserCommon::create_new_record_href($this->tab,$this->custom_defaults,'multi',true,true);
            if ($label!==false && $label!=='') $custom_label = '<a '.$label.'><span class="record_browser_add_new" '.Utils_TooltipCommon::open_tag_attrs(__('Add new record')).'><img src="'.Base_ThemeCommon::get_template_file('Utils/RecordBrowser/add.png').'" /><div class="add_new">'.__('Add new').'</div></span></a>';
        }
        if ($this->more_add_button_stuff) {
            if ($custom_label) $custom_label = '<table><tr><td>'.$custom_label.'</td><td>'.$this->more_add_button_stuff.'</td></tr></table>';
            else $custom_label = $this->more_add_button_stuff;
        }
        $gb->set_custom_label($custom_label);

        if ($admin) $this->browse_mode = 'all';
        if ($this->browse_mode == 'recent') {
            $ret = DB::Execute('SELECT * FROM '.$this->tab.'_recent WHERE user_id=%d ORDER BY visited_on DESC', array(Acl::get_user()));
            while ($row = $ret->FetchRow()) {
                if (!isset($records[$row[$this->tab.'_id']])) continue;
                $records[$row[$this->tab.'_id']]['visited_on'] = Base_RegionalSettingsCommon::time2reg(strtotime($row['visited_on']));
            }
        } else {
            $this->set_module_variable('set_browsed_records',array('tab'=>$this->tab,'crits'=>$crits, 'order'=>$order, 'records'=>array()));
        }
        if ($special) $rpicker_ind = array();

        if (!$pdf && !$admin && $this->favorites) {
            $favs = array();
            $ret = DB::Execute('SELECT '.$this->tab.'_id FROM '.$this->tab.'_favorite WHERE user_id=%d', array(Acl::get_user()));
            while ($row=$ret->FetchRow()) $favs[$row[$this->tab.'_id']] = true;
        }
        self::$access_override['tab'] = $this->tab;
        if (isset($limit)) $i = $limit['offset'];

        $grid_enabled = $this->grid===null?Base_User_SettingsCommon::get('Utils/RecordBrowser','grid'):$this->grid;
        if ($grid_enabled) load_js('modules/Utils/RecordBrowser/grid.js');

        $this->view_fields_permission = $this->get_access('add', $this->custom_defaults);
        if (!$pdf && !$special && $this->add_in_table && $this->view_fields_permission) {
            $form = $this->init_module('Libs/QuickForm',null, 'add_in_table__'.$this->tab);
            $form_name = $form->get_name();
        } else $form_name = '';
        foreach ($records as $row) {
            if ($this->browse_mode!='recent' && isset($limit)) {
                self::$browsed_records['records'][$row['id']] = $i;
                $i++;
            }
            self::$access_override['id'] = $row['id'];
            $gb_row = $gb->get_new_row();
			$row_data = array();
            if (!$pdf && !$admin && $this->favorites) {
                $isfav = isset($favs[$row['id']]);
                $row_data[] = Utils_RecordBrowserCommon::get_fav_button($this->tab, $row['id'], $isfav);
            }
            if (!$pdf && !$admin && $this->watchdog)
                $row_data[] = Utils_WatchdogCommon::get_change_subscription_icon($this->tab,$row['id']);
            if ($special) {
                $element = $this->get_module_variable('element');
                $format = $this->get_module_variable('format_func');
                $row_data = array('<input type="checkbox" id="leightbox_rpicker_'.$element.'_'.$row['id'].'" formated_name="'.(is_callable($format)?strip_tags(call_user_func($format, $row, true)):'').'" />');
                $rpicker_ind[] = $row['id'];
            }
            $r_access = $this->get_access('view', $row);
            foreach($query_cols as $k=>$argsid) {
				if (!$r_access || !$r_access[$argsid]) {
					$row_data[] = '';
					continue;
				}
                $field = $hash[$argsid];
                $args = $this->table_rows[$field];
                $value = $this->get_val($field, $row, ($special || $pdf), $args);
                if (strip_tags($value)=='') $value .= '&nbsp;';
                if ($args['style']=='currency' || $args['style']=='number') $value = array('style'=>'text-align:right;','value'=>$value);
                if ($grid_enabled && !in_array($args['type'], array('calculated','multiselect'))) {
                    $table = '<table class="Utils_RecordBrowser__grid_table" style="width:100%" cellpadding="0" cellspacing="0" border="0"><tr><td id="grid_form_field_'.$argsid.'_'.$row['id'].'" style="display:none;">Loading...</td><td id="grid_value_field_'.$argsid.'_'.$row['id'].'">';
                    $ed_icon = '</td><td style="min-width:18px;width:18px;padding:0px;margin:0px;">'.
                                '<span id="grid_edit_'.$argsid.'_'.$row['id'].'" style="float:right;display:none;"><a href="javascript:void(0);" onclick="grid_enable_field_edit(\''.$argsid.'\','.$row['id'].',\''.$this->tab.'\',\''.$form_name.'\');"><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'edit.png').'"></a></span>'.
                                '<span id="grid_save_'.$argsid.'_'.$row['id'].'" style="float:right;display:none;"><a href="javascript:void(0);" onclick="grid_submit_field(\''.$argsid.'\','.$row['id'].',\''.$this->tab.'\');"><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils/RecordBrowser', 'save_grid.png').'"></a></span>'.
                                '</td></tr></table>';

/*                  $table = '<span id="grid_form_field_'.$argsid.'_'.$row['id'].'" style="display:none;">Loading...</span><span id="grid_value_field_'.$argsid.'_'.$row['id'].'">';
                    $ed_icon = '</span>'.
                                '<span id="grid_edit_'.$argsid.'_'.$row['id'].'" style="float:right;display:none;"><a href="javascript:void(0);" onclick="grid_enable_field_edit(\''.$argsid.'\','.$row['id'].',\''.$this->tab.'\',\''.$form_name.'\');"><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'edit.png').'"></a></span>'.
                                '<span id="grid_save_'.$argsid.'_'.$row['id'].'" style="float:right;display:none;"><a href="javascript:void(0);" onclick="grid_submit_field(\''.$argsid.'\','.$row['id'].',\''.$this->tab.'\');"><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils/RecordBrowser', 'save_grid.png').'"></a></span>';*/


                    $attrs = 'onmouseover="if(typeof(mouse_over_grid)!=\'undefined\')mouse_over_grid(\''.$argsid.'\',\''.$row['id'].'\');" onmouseout="if(typeof(mouse_out_grid)!=\'undefined\')mouse_out_grid(\''.$argsid.'\',\''.$row['id'].'\');"';
//                  $attrs = 'onmouseover="$(\'grid_edit_'.$argsid.'_'.$row['id'].'\').style.display=\'inline\'" onmouseout="$(\'grid_edit_'.$argsid.'_'.$row['id'].'\').style.display=\'none\'"';
                } else {
                    $table = '';
                    $ed_icon = '';
                    $attrs = '';
                }
                if (is_array($value)) {
                    $value['value'] = $table.$value['value'].$ed_icon;
                    $value['attrs'] = $attrs;
                } else {
                    $value = array(
                        'value'=>$table.$value.$ed_icon,
                        'attrs'=>$attrs
                    );
                }
				if ($pdf) {
					$value['attrs'] = $attrs.' style="border:1px solid black;" width="'.$table_columns[$k]['width'].'"';
					$value['value'] = '&nbsp;'.$value['value'].'&nbsp;';
				}
                $row_data[] = $value;
            }

            $gb_row->add_data_array($row_data);
            if (!$pdf && $this->disabled['actions']!==true) {
                if ($this->disabled['actions']===false) $da = array();
                else $da = array_flip($this->disabled['actions']);
                if (!$special) {
                    if (!isset($da['view'])) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'view', $row['id'])),'View');
					else $gb_row->add_action('','View',__('You don\'t have permission to view this record.'),null,0,true);
                    if (!isset($da['edit']) && $this->get_access('edit',$row)) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'edit',$row['id'])),'Edit');
					else $gb_row->add_action('','Edit',__('You don\'t have permission to edit this record.'),null,0,true);
                    if ($admin) {
                        if (!$row[':active']) $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],true)),'Activate', null, 'active-off');
                        else $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],false)),'Deactivate', null, 'active-on');
                        $info = Utils_RecordBrowserCommon::get_record_info($this->tab, $row['id']);
                        if ($info['edited_on']===null) $gb_row->add_action('','This record was never edited',null,'history_inactive');
                        else $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_edit_history', $row['id'])),'View edit history',null,'history');
                    } else {
						if (!isset($da['delete']) && $this->get_access('delete',$row)) $gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $row['id'])),'Delete');
						else $gb_row->add_action('','Delete',__('You don\'t have permission to delete this record'),null,0,true);
					}
                }
                if (!isset($da['info'])) $gb_row->add_info(($this->browse_mode=='recent'?'<b>'.__('Visited on: %s', array($row['visited_on'])).'</b><br>':'').Utils_RecordBrowserCommon::get_html_record_info($this->tab, isset($info)?$info:$row['id']));
                if ($this->additional_actions_method!==null && is_callable($this->additional_actions_method))
                    call_user_func($this->additional_actions_method, $row, $gb_row, $this);
            }
        }
        if (!$special && $this->add_in_table && $this->view_fields_permission) {

            $visible_cols = array();
            foreach($this->table_rows as $field => $args){
                if ((!$args['visible'] && (!isset($cols[$args['id']]) || $cols[$args['id']] === false))) continue;
                if (isset($cols[$args['id']]) && $cols[$args['id']] === false) continue;
                $visible_cols[$args['id']] = true;
            }

			$this->record = $this->custom_defaults = Utils_RecordBrowserCommon::record_processing($this->tab, $this->custom_defaults, 'adding');

            $this->prepare_view_entry_details($this->custom_defaults, 'add', null, $form, $visible_cols);
            $form->setDefaults($this->custom_defaults);

            if ($form->isSubmitted()) {
                $this->set_module_variable('force_add_in_table_after_submit', true);
                if ($form->validate()) {
                    $values = $form->exportValues();
                    foreach ($this->custom_defaults as $k=>$v)
                        if (!isset($values[$k])) $values[$k] = $v;
                    $id = Utils_RecordBrowserCommon::new_record($this->tab, $values);
                    location(array());
                } else {
                    $this->show_add_in_table = true;
                }
            }
            $form->addElement('submit', 'submit_qanr', __('Save'), array('style'=>'width:100%;height:19px;', 'class'=>'button'));
            $renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty();
            $form->accept($renderer);
            $data = $renderer->toArray();

            $gb->set_prefix($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");
            $gb->set_postfix("</form>\n");

            if (!$admin && $this->favorites) {
                $row_data= array('&nbsp;');
            } else $row_data= array();
            if (!$admin && $this->watchdog)
                $row_data[] = '&nbsp;';


            $first = true;
            foreach($visible_cols as $k => $v) {
                if (isset($data[$k])) {
                    $row_data[] = array('value'=>$data[$k]['error'].$data[$k]['html'], 'overflow_box'=>false);
                    if ($first) eval_js('focus_on_field = "'.$k.'";');
                    $first = false;
                } else $row_data[] = '&nbsp;';
            }

//          if ($this->browse_mode == 'recent')
//              $row_data[] = '&nbsp;';

            $gb_row = $gb->get_new_row();
            $gb_row->add_action('',$data['submit_qanr']['html'],'', null, 0, false, 7);
            $gb_row->set_attrs('id="add_in_table_row" style="display:'.($this->show_add_in_table?'':'none').';"');
            $gb_row->add_data_array($row_data);
        }
        if ($special) {
            $this->set_module_variable('rpicker_ind',$rpicker_ind);
            $ret = $this->get_html_of_module($gb);
            Base_User_SettingsCommon::save('Utils/RecordBrowser/RecordPicker','per_page',$gb->get_module_variable('per_page'));
            Base_User_SettingsCommon::save('Utils/GenericBrowser','per_page',$gb_per_page);
            return $ret;
        }
		if ($pdf) {
			$gb->absolute_width(true);
			$args = array(Base_ThemeCommon::get_template_filename('Utils_GenericBrowser','pdf'));
		} else $args = array();
		$this->display_module($gb, $args);
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function delete_record($id) {
        Utils_RecordBrowserCommon::delete_record($this->tab, $id);
        return $this->back();
    }
    public function clone_record($id) {
        if (self::$clone_result!==null) {
            if (is_numeric(self::$clone_result)) {
                Utils_RecordBrowserCommon::record_processing($this->tab, self::$clone_result, 'cloned', $id);
                $this->navigate('view_entry', 'view', self::$clone_result);
            }
            self::$clone_result = null;
            return false;
        }
        $record = Utils_RecordBrowserCommon::get_record($this->tab, $id, false);
        $access = $this->get_access('view',$record);
        if (is_array($access))
            foreach ($access as $k=>$v)
                if (!$v) unset($record[$k]);
		$record = Utils_RecordBrowserCommon::record_processing($this->tab, $record, 'cloning', $id);
		unset($record['id']);
        $this->navigate('view_entry', 'add', null, $record);
        return true;
    }
    public function view_entry_with_REQUEST($mode='view', $id = null, $defaults = array(), $show_actions=true, $request=array()) {
        foreach ($request as $k=>$v)
            $_REQUEST[$k] = $v;
        if(isset($_REQUEST['switch_to_addon']))
	        $this->switch_to_addon = $this->get_module_variable('switch_to_addon',$_REQUEST['switch_to_addon']);
        return $this->view_entry($mode, $id, $defaults, $show_actions);
    }
    public function view_entry($mode='view', $id = null, $defaults = array(), $show_actions=true) {
        if (isset($_SESSION['client']['recordbrowser']['admin_access'])) Utils_RecordBrowserCommon::$admin_access = true;
        self::$mode = $mode;
        if ($this->navigation_executed) {
            $this->navigation_executed = false;
            return true;
        }
        if ($this->check_for_jump()) return;
        $theme = $this->init_module('Base/Theme');
        if ($this->isset_module_variable('id')) {
            $id = $this->get_module_variable('id');
            $this->unset_module_variable('id');
        }
        self::$browsed_records = null;

        Utils_RecordBrowserCommon::$cols_order = array();
        $js = ($mode!='view');
        $time = microtime(true);
        if ($this->is_back()) {
            self::$clone_result = 'canceled';
            return $this->back();
        }

        $this->init();
		if (is_numeric($id)) {
	                $id = intVal($id);
			self::$last_record = $this->record = Utils_RecordBrowserCommon::get_record($this->tab, $id, $mode!=='edit');
		} else {
			self::$last_record = $this->record = $id;
			$id = intVal($this->record['id']);
		}
		if ($id===0) $id = null;
        if ($id!==null && is_numeric($id)) Utils_WatchdogCommon::notified($this->tab,$id);

        if($mode=='add') {
            foreach ($defaults as $k=>$v)
                $this->custom_defaults[$k] = $v;
            foreach($this->table_rows as $field => $args)
                if (!isset($this->custom_defaults[$args['id']]))
					$this->custom_defaults[$args['id']] = $args['type'] == 'multiselect' ? array() : '';
			$this->custom_defaults['created_by'] = Acl::get_user();
		}

        $access = $this->get_access($mode=='history'?'view':$mode, isset($this->record)?$this->record:$this->custom_defaults);
        if ($mode=='edit' || $mode=='add')
            $this->view_fields_permission = $this->get_access('view', isset($this->record)?$this->record:$this->custom_defaults);
        else
            $this->view_fields_permission = $access;

        if ($mode!='add' && (!$access || $this->record==null)) {
            if (Base_AclCommon::i_am_admin()) {
                Utils_RecordBrowserCommon::$admin_access = true;
                $access = $this->get_access($mode, isset($this->record)?$this->record:$this->custom_defaults);
                if ($mode=='edit' || $mode=='add')
                    $this->view_fields_permission = $this->get_access('view', isset($this->record)?$this->record:$this->custom_defaults);
                else
                    $this->view_fields_permission = $access;
            } else {
                print(__('You don\'t have permission to view this record.'));
                if ($show_actions===true || (is_array($show_actions) && (!isset($show_actions['back']) || $show_actions['back']))) {
                    Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
                    Utils_ShortcutCommon::add(array('esc'), 'function(){'.$this->create_back_href_js().'}');
                }
                return true;
            }
        }
        if ($mode=='add' && !$access) {
			print(__('You don\'t have permission to perform this action.'));
			if ($show_actions===true || (is_array($show_actions) && (!isset($show_actions['back']) || $show_actions['back']))) {
				Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
				Utils_ShortcutCommon::add(array('esc'), 'function(){'.$this->create_back_href_js().'}');
			}
			return true;
		}

        if($mode == 'add' || $mode == 'edit') {
            $theme -> assign('click2fill', '<div id="c2fBox"></div>');
            load_js('modules/Utils/RecordBrowser/click2fill.js');
            eval_js('initc2f("'.__('Scan/Edit').'","'.__('Paste your data here').'")');
            Base_ActionBarCommon::add('clone', __('Click 2 Fill'), 'href="javascript:void(0)" onclick="c2f()"');
        }

//        if ($mode!='add' && !$this->record[':active'] && !Base_AclCommon::i_am_admin()) return $this->back();

        $tb = $this->init_module('Utils/TabbedBrowser', null, 'recordbrowser_addons');
		if ($mode=='history') $tb->set_inline_display();
        self::$tab_param = $tb->get_path();

        $form = $this->init_module('Libs/QuickForm',null, $mode);
        $this->form = $form;

        if($mode!='add')
            Utils_RecordBrowserCommon::add_recent_entry($this->tab, Acl::get_user(),$id);

		$dp = Utils_RecordBrowserCommon::record_processing($this->tab, $mode!='add'?$this->record:$this->custom_defaults, ($mode=='view' || $mode=='history')?'view':$mode.'ing');
		if (is_array($dp))
			$defaults = $this->custom_defaults = self::$last_record = $this->record = $dp;

        if (self::$last_record===null) self::$last_record = $defaults;
        if($mode=='add')
            $form->setDefaults($defaults);

        switch ($mode) {
            case 'add':     $this->action = 'New record'; break;
            case 'edit':    $this->action = 'Edit record'; break;
            case 'view':    $this->action = 'View record'; break;
            case 'history':    $this->action = 'Record history view'; break;
        }

        $this->prepare_view_entry_details($this->record, $mode=='history'?'view':$mode, $id, $form);

        if ($mode==='edit' || $mode==='add')
            foreach($this->table_rows as $field => $args) {
                if (!$access[$args['id']])
                    $form->freeze($args['id']);
            }
        if ($form->validate() && $form->exportValue('submited')) {
            $values = $form->exportValues();
			
			foreach ($defaults as $k=>$v) {
				if (!isset($values[$k]) && isset($this->view_fields_permission[$k]) && !$this->view_fields_permission[$k]) $values[$k] = $v;
				if (isset($access[$k]) && !$access[$k]) $values[$k] = $v;
			}
            foreach ($this->table_rows as $v) {
                if ($v['type']=='checkbox' && !isset($values[$v['id']])) $values[$v['id']]='';
            }
            $values['id'] = $id;
            foreach ($this->custom_defaults as $k=>$v)
                if (!isset($values[$k])) $values[$k] = $v;
            if ($mode=='add') {
                $id = Utils_RecordBrowserCommon::new_record($this->tab, $values);
                self::$clone_result = $id;
                self::$clone_tab = $this->tab;
                return $this->back();
            }
            $time_from = date('Y-m-d H:i:s', $this->get_module_variable('edit_start_time'));
            $ret = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history WHERE edited_on>=%T AND edited_on<=%T AND '.$this->tab.'_id=%d',array($time_from, date('Y-m-d H:i:s'), $id));
            if ($ret->EOF) {
                $this->update_record($id,$values);
                return $this->back();
            }
            $this->dirty_read_changes($id, $time_from);
        }
		$form->add_error_closing_buttons();

        if (($mode=='edit' || $mode=='add') && $show_actions!==false) {
            Utils_ShortcutCommon::add(array('Ctrl','S'), 'function(){'.$form->get_submit_form_js().'}');
        }
        if ($mode=='edit') {
            $this->set_module_variable('edit_start_time',$time);
        }

        if ($show_actions!==false) {
            if ($mode=='view') {
                if ($this->get_access('edit',$this->record)) {
                    Base_ActionBarCommon::add('edit', __('Edit'), $this->create_callback_href(array($this,'navigate'), array('view_entry','edit',$id)));
                    Utils_ShortcutCommon::add(array('Ctrl','E'), 'function(){'.$this->create_callback_href_js(array($this,'navigate'), array('view_entry','edit',$id)).'}');
                }
                if ($this->get_access('delete',$this->record)) {
                    Base_ActionBarCommon::add('delete', __('Delete'), $this->create_confirm_callback_href(__('Are you sure you want to delete this record?'),array($this,'delete_record'),array($id)));
                }
                if ($this->get_access('add',$this->record)) {
                    Base_ActionBarCommon::add('clone',__('Clone'), $this->create_confirm_callback_href(__('You are about to create a copy of this record. Do you want to continue?'),array($this,'clone_record'),array($id)));
                }
                if ($show_actions===true || (is_array($show_actions) && (!isset($show_actions['back']) || $show_actions['back'])))
                    Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
            } elseif($mode!='history') {
                Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
                Base_ActionBarCommon::add('delete', __('Cancel'), $this->create_back_href());
            }
            Utils_ShortcutCommon::add(array('esc'), 'function(){'.$this->create_back_href_js().'}');
        }

        if ($mode!='add') {
            $theme -> assign('info_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(Utils_RecordBrowserCommon::get_html_record_info($this->tab, $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'" /></a>');
            $row_data= array();

			if ($mode!='history') {
				if ($this->favorites)
					$theme -> assign('fav_tooltip', Utils_RecordBrowserCommon::get_fav_button($this->tab, $id));
				if ($this->watchdog)
					$theme -> assign('subscription_tooltip', Utils_WatchdogCommon::get_change_subscription_icon($this->tab, $id));
				if ($this->full_history) {
					$info = Utils_RecordBrowserCommon::get_record_info($this->tab, $id);
					if ($info['edited_on']===null) $theme -> assign('history_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(__('This record was never edited')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history_inactive.png').'" /></a>');
					else $theme -> assign('history_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(__('Click to view edit history of currently displayed record')).' '.$this->create_callback_href(array($this,'navigate'), array('view_edit_history', $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history.png').'" /></a>');
				}
				if ($this->clipboard_pattern) {
					$theme -> assign('clipboard_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(__('Click to export values to copy')).' '.Libs_LeightboxCommon::get_open_href('clipboard').'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','clipboard.png').'" /></a>');
					$text = $this->clipboard_pattern;
					$record = Utils_RecordBrowserCommon::get_record($this->tab, $id);
					/* for every field name store its value */
					$data = array();
					foreach($this->table_rows as $val) {
						$fval = Utils_RecordBrowserCommon::get_val($this->tab, $val['id'], $record, true);
						if(strlen($fval)) $data[$val['id']] = $fval;
					}
					/* to some complicate preg match to find every occurence
					 * of %{ .. {f_name} .. } pattern
					 */
					$elem = 0;
					$match = array();
					$original_text = $text;
					$text = '%{'.$text.'}';
					while(preg_match('/%\{(([^%\}\{]*?\{[^%\}\{]+?\}[^%\}\{]*?)+?)\}/', $text, $match)) { // match for pattern %{...{..}...}
						$text_replace = $match[1];
						$changed = false;
						while(preg_match('/\{(.+?)\}/', $text_replace, $second_match)) { // match for keys in braces {key}
							$replace_value = '';
							if(key_exists($second_match[1], $data)) {
								$replace_value = $data[$second_match[1]];
								$changed = true;
							}
							$text_replace = str_replace($second_match[0], $replace_value, $text_replace);
						}
						if(! $changed ) $text_replace = '';
						$data["int$elem"] = $text_replace;
						$text = str_replace($match[0], '{int'.$elem.'}', $text);
						$elem++;
					}
					$elem--;
					if ($elem>=0) {
						$text = str_replace('{int'.$elem.'}', $data["int$elem"], $text);
					} else {
						$text = $original_text;
					}
					load_js("modules/Utils/RecordBrowser/selecttext.js");
					/* remove all php new lines, replace <br>|<br/> to new lines and quote all special chars */
					$ftext = htmlspecialchars(preg_replace('#<[bB][rR]/?>#', "\n", str_replace("\n", '', $text)));
					$flash_copy = '<object width="60" height="20">'.
								'<param name="FlashVars" value="txtToCopy='.$ftext.'">'.
								'<param name="movie" value="'.$this->get_module_dir().'copyButton.swf">'.
								'<embed src="'.$this->get_module_dir().'copyButton.swf" flashvars="txtToCopy='.$ftext.'" width="60" height="20">'.
								'</embed>'.
								'</object>';
					$text = '<h3>'.__('Click Copy under the box or move mouse over box below to select text and hit Ctrl-c to copy it.').'</h3><div onmouseover="fnSelect(this)" style="border: 1px solid gray; margin: 15px; padding: 20px;">'.$text.'</div>'.$flash_copy;

					Libs_LeightboxCommon::display('clipboard',$text,__('Copy'));
				}
			}
        }

		if ($mode=='view') {
			$dp = Utils_RecordBrowserCommon::record_processing($this->tab, $this->record, 'display');
			if ($dp && is_array($dp))
				foreach ($dp as $k=>$v)
					$theme->assign($k, $v);
		}

        if ($mode=='view' || $mode=='history') $form->freeze();
        $renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty();
        $form->accept($renderer);
        $data = $renderer->toArray();

        print($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");

        $last_page = DB::GetOne('SELECT MIN(position) FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field != \'General\'');
		if (!$last_page) $last_page = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field')+1;
        $label = DB::GetRow('SELECT field, param FROM '.$this->tab.'_field WHERE position=%s', array($last_page));
		if ($label) {
			$cols = $label['param'];
			$label = $label['field'];
		} else $cols = false;

        $this->view_entry_details(1, $last_page, $data, $theme, true);
        $ret = DB::Execute('SELECT position, field, param FROM '.$this->tab.'_field WHERE type = \'page_split\' AND position > %d ORDER BY position', array($last_page));
        $row = true;
        if ($mode=='view')
            print("</form>\n");
        $tab_counter=-1;
		$additional_tabs = 0;
		$default_tab = null;
        while ($row) {
            $row = $ret->FetchRow();
            if ($row) $pos = $row['position'];
            else $pos = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field WHERE active=1')+1;

            $valid_page = false;
			$hide_page = ($mode=='view' && Base_User_SettingsCommon::get('Utils/RecordBrowser','hide_empty'));
            foreach($this->table_rows as $field => $args) {
                if (!isset($data[$args['id']]) || $data[$args['id']]['type']=='hidden') continue;
                if ($args['position'] >= $last_page && ($pos+1 == -1 || $args['position'] < $pos+1)) {
                    $valid_page = true;
					if ($hide_page && !$this->field_is_empty($this->record, $args['id'])) $hide_page = false;
                    break;
                }
            }
            if ($valid_page && $pos - $last_page>1 && !isset($this->hide_tab[$label])) {
				$tb->set_tab(_V($label),array($this,'view_entry_details'), array($last_page, $pos+1, $data, null, false, $cols, _V($label)), $js); // TRSL
				if ($hide_page) {
					eval_js('$("'.$tb->get_tab_id(_V($label)).'").style.display="none";');
					if ($default_tab===($tab_counter+1) || $tb->get_tab()==($tab_counter+1)) $default_tab = $tab_counter+2;
				} else
					$additional_tabs++;
			}
            $cols = $row['param'];
            $last_page = $pos;
            if ($row) $label = $row['field'];
            $tab_counter++;
        }
		if ($default_tab!==null) $tb->set_default_tab($default_tab);
        if ($mode!='history') {
            $ret = DB::Execute('SELECT * FROM recordbrowser_addon WHERE tab=%s AND enabled=1 ORDER BY pos', array($this->tab));
            $addons_mod = array();
            while ($row = $ret->FetchRow()) {
                if (ModuleManager::is_installed($row['module'])==-1) continue;
                if (is_callable(explode('::',$row['label']))) {
                    $result = call_user_func(explode('::',$row['label']), $this->record, $this);
                    if (!isset($result['show'])) $result['show']=true;
					if (($mode=='add' || $mode=='edit') && (!isset($result['show_in_edit']) || !$result['show_in_edit'])) continue;
                    if ($result['show']==false) continue;
                    if (!isset($result['label'])) $result['label']='';
                    $row['label'] = $result['label'];
                } else {
					if ($mode=='add' || $mode=='edit') continue;
					$row['label'] = _V($row['label']); // ****** Translate addons captions frrom the DB
				}
                $mod_id = md5(serialize($row));
				if (method_exists($row['module'].'Common',$row['func'].'_access') && !call_user_func(array($row['module'].'Common',$row['func'].'_access'), $this->record, $this)) continue;
                $addons_mod[$mod_id] = $this->init_module($row['module']);
                if (!method_exists($addons_mod[$mod_id],$row['func'])) $tb->set_tab($row['label'],array($this, 'broken_addon'), array(), $js);
                else $tb->set_tab($row['label'],array($this, 'display_module'), array(& $addons_mod[$mod_id], array($this->record, $this), $row['func']), $js);
            }
        }
        if ($additional_tabs==0 && ($mode=='add' || $mode=='edit' || $mode=='history'))
            print("</form>\n");
        $this->display_module($tb);
        $tb->tag();
		
		foreach ($this->fields_in_tabs as $label=>$fields) {
			$highlight = false;
			foreach ($fields as $f) {
				$err = $form->getElementError($f);
				if ($err) {
					$highlight = true;
					break;
				}
			}
			if ($highlight)
				$tb->tab_icon($label, Base_ThemeCommon::get_template_file('Utils_RecordBrowser','notify_error.png'));
		}
		
        if ($this->switch_to_addon) {
    	    $this->set_module_variable('switch_to_addon',false);
            if($tab_counter<0) $tab_counter=0;
            $ret = DB::Execute('SELECT * FROM recordbrowser_addon WHERE tab=%s AND enabled=1 ORDER BY pos', array($this->tab));
            while ($row = $ret->FetchRow()) {
                if (ModuleManager::is_installed($row['module'])==-1) continue;
                if (is_callable(explode('::',$row['label']))) {
                    $result = call_user_func(explode('::',$row['label']), $this->record,$this);
                    if (isset($result['show']) && $result['show']==false) continue;
                    $row['label'] = $result['label'];
                }
                if ($row['label']==$this->switch_to_addon) $this->switch_to_addon = $tab_counter;
                $tab_counter++;
            }
            $tb->switch_tab($this->switch_to_addon);
            location(array());
        }
        if ($additional_tabs!=0 && ($mode=='add' || $mode=='edit' || $mode=='history'))
            print("</form>\n");

        return true;
    } //view_entry
	
	public function field_is_empty($r, $f) {
		if (is_array($r[$f])) return empty($r[$f]);
		return $r[$f]=='';
	}

    public function broken_addon(){
        print('Addon is broken, please contact system administrator.');
    }

    public function view_entry_details($from, $to, $data, $theme=null, $main_page = false, $cols = 2, $tab_label = null){
        if ($theme==null) $theme = $this->init_module('Base/Theme');
        $fields = array();
        $longfields = array();
        foreach($this->table_rows as $field => $args) {
            if (!isset($data[$args['id']]) || $data[$args['id']]['type']=='hidden') continue;
            if ($args['position'] >= $from && ($to == -1 || $args['position'] < $to))
            {
				if ($tab_label) $this->fields_in_tabs[$tab_label][] = $args['id'];
                if (!isset($data[$args['id']])) $data[$args['id']] = array('label'=>'', 'html'=>'');
                    $arr = array(   'label'=>$data[$args['id']]['label'],
                                    'element'=>$args['id'],
                                    'advanced'=>isset($this->advanced[$args['id']])?$this->advanced[$args['id']]:'',
                                    'html'=>$data[$args['id']]['html'],
                                    'style'=>$args['style'].($data[$args['id']]['frozen']?' frozen':''),
                                    'error'=>isset($data[$args['id']]['error'])?$data[$args['id']]['error']:null,
                                    'required'=>isset($args['required'])?$args['required']:null,
                                    'type'=>$args['type']);
                    if ($args['type']<>'long text') $fields[$args['id']] = $arr; else $longfields[$args['id']] = $arr;
            }
        }
        if ($cols==0) $cols=2;
        $theme->assign('fields', $fields);
        $theme->assign('cols', $cols);
        $theme->assign('longfields', $longfields);
        $theme->assign('action', self::$mode=='history'?'view':self::$mode);
        $theme->assign('form_data', $data);
        $theme->assign('required_note', __('Indicates required fields.'));

        $theme->assign('caption',_V($this->caption).' '.$this->get_jump_to_id_button());
        $theme->assign('icon',$this->icon);

        $theme->assign('main_page',$main_page);

        if ($main_page) {
            $tpl = DB::GetOne('SELECT tpl FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
            $theme->assign('raw_data',$this->record);
        } else {
            $tpl = '';
            if (self::$mode=='view') print('<form>');
        }
		if ($tpl) Base_ThemeCommon::load_css('Utils_RecordBrowser','View_entry');
        $theme->display(($tpl!=='')?$tpl:'View_entry', ($tpl!==''));
        if (!$main_page && self::$mode=='view') print('</form>');
    }

    public function timestamp_required($v) {
        return $v['__datepicker']!=='' && Base_RegionalSettingsCommon::reg2time($v['__datepicker'],false)!==false;
    }

    public function prepare_view_entry_details($record, $mode, $id, $form, $visible_cols = null, $for_grid=false){
        foreach($this->table_rows as $field => $args){
            if (isset($this->view_fields_permission[$args['id']]) && !$this->view_fields_permission[$args['id']]) continue;
            if ($visible_cols!==null && !isset($visible_cols[$args['id']])) continue;
            if (!isset($record[$args['id']])) $record[$args['id']] = '';
            if ($for_grid) {
                $nk = '__grid_'.$args['id'];
                $record[$nk] = $record[$args['id']];
                $args['id'] = $nk;
            }
            if ($args['type']=='hidden') {
                $form->addElement('hidden', $args['id']);
                $form->setDefaults(array($args['id']=>$record[$args['id']]));
                continue;
            }
			if ($mode=='view' && Base_User_SettingsCommon::get('Utils/RecordBrowser','hide_empty') && $this->field_is_empty($record, $args['id']) && $args['type']!='checkbox') {
				eval_js('var e=$("_'.$args['id'].'__data");if(e)e.up("tr").style.display="none";');
			}
            $label = '<span id="_'.$args['id'].'__label">'._V($args['name']).'</span>'; // TRSL
            if (isset($this->QFfield_callback_table[$field])) {
				//$label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
                $ff = $this->QFfield_callback_table[$field];
                call_user_func_array($ff, array(&$form, $args['id'], $label, $mode, $mode=='add'?(isset($this->custom_defaults[$args['id']])?$this->custom_defaults[$args['id']]:''):$record[$args['id']], $args, $this, $this->display_callback_table));
            } else {
                if ($mode!=='add' && $mode!=='edit') {
                    if ($args['type']!='checkbox' || isset($this->display_callback_table[$field])) {
                        $def = $this->get_val($field, $record, false, $args);
                        $form->addElement('static', $args['id'], $label, $def, array('id'=>$args['id']));
                        continue;
                    }
                }
                switch ($args['type']) {
                    case 'calculated':  $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
										$form->addElement('static', $args['id'], $label);
//                                      if ($mode=='edit')
                                        if (!is_array($this->record))
                                            $values = $this->custom_defaults;
                                        else {
                                            $values = $this->record;
                                            if (is_array($this->custom_defaults)) $values = $values+$this->custom_defaults;
                                        }
                                        $val = @$this->get_val($field, $values, true, $args);
                                        if (!$val) $val = '['.__('formula').']';
                                        $form->setDefaults(array($args['id']=>'<div class="static_field" id="'.Utils_RecordBrowserCommon::get_calculated_id($this->tab, $args['id'], $id).'">'.$val.'</div>'));
                                        break;
                    case 'integer':
                    case 'float':       $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
										$form->addElement('text', $args['id'], $label, array('id'=>$args['id']));
                                        if ($args['type']=='integer')
                                            $form->addRule($args['id'], __('Only integer numbers are allowed.'), 'regex', '/^[0-9]*$/');
                                        else
                                            $form->addRule($args['id'], __('Only numbers are allowed.'), 'numeric');
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'checkbox':    $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
										$form->addElement('checkbox', $args['id'], $label, '', array('id'=>$args['id']));
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'currency':    $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
										$form->addElement('currency', $args['id'], $label, array('id'=>$args['id']));
//                                      if ($mode!=='add') $form->setDefaults(array($args['id']=>Utils_CurrencyFieldCommon::format_default($record[$args['id']])));
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'text':        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type'], $args['param']);
										$form->addElement('text', $args['id'], $label, array('id'=>$args['id'], 'maxlength'=>$args['param']));
//                                      else $form->addElement('static', $args['id'], $label, array('id'=>$args['id']));
                                        $form->addRule($args['id'], __('Maximum length for this field is %s characters.',array($args['param'])), 'maxlength', $args['param']);
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'long text':   $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
										$form->addElement($this->add_in_table?'text':'textarea', $args['id'], $label, array('id'=>$args['id']));
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'date':		$label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
										$form->addElement('datepicker', $args['id'], $label, array('id'=>$args['id'], 'label'=>$this->add_in_table?'':null));
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'timestamp':   $f_param = array('id'=>$args['id']);
										if ($args['param']) $f_param['optionIncrement'] = array('i'=>$args['param']);
										$label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
										$form->addElement('timestamp', $args['id'], $label, $f_param);
                                        static $rule_defined = false;
                                        if (!$rule_defined) $form->registerRule('timestamp_required', 'callback', 'timestamp_required', $this);
                                        $rule_defined = true;
                                        if (isset($args['required']) && $args['required']) $form->addRule($args['id'], __('Field required'), 'timestamp_required');
                                        if ($mode!=='add' && $record[$args['id']]) $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'time':        $time_format = Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';
                                        $lang_code = Base_LangCommon::get_lang_code();
										$label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
                                        $form->addElement('timestamp', $args['id'], $label, array('date'=>false, 'format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code, 'id'=>$args['id']));
                                        if ($mode!=='add' && $record[$args['id']]) $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'commondata':  $param = explode('::',$args['param']['array_id']);
                                        foreach ($param as $k=>$v) if ($k!=0) $param[$k] = preg_replace('/[^a-z0-9]/','_',strtolower($v));
										$label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type'], $args['param']['array_id']);
                                        $form->addElement($args['type'], $args['id'], $label, $param, array('empty_option'=>true, 'id'=>$args['id'], 'order_by_key'=>$args['param']['order_by_key']));
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'select':
                    case 'multiselect': $comp = array();
                                        $ref = explode(';',$args['param']);
                                        if (isset($ref[1])) $crits_callback = $ref[1];
                                        else $crits_callback = null;
                                        if (isset($ref[2])) $multi_adv_params = call_user_func(explode('::',$ref[2]), $record);
                                        else $multi_adv_params = null;
                                        if (!isset($multi_adv_params) || !is_array($multi_adv_params)) $multi_adv_params = array();
                                        if (!isset($multi_adv_params['order'])) $multi_adv_params['order'] = array();
                                        if (!isset($multi_adv_params['cols'])) $multi_adv_params['cols'] = array();
                                        if (!isset($multi_adv_params['format_callback'])) $multi_adv_params['format_callback'] = array();
                                        $ref = $ref[0];
                                        @(list($tab, $col) = explode('::',$ref));
                                        if (!isset($col)) trigger_error($field);
                                        if ($tab=='__COMMON__') {
                                            $data = Utils_CommonDataCommon::get_translated_tree($col);
                                            if (!is_array($data)) $data = array();
                                            $comp = $comp+$data;
                                            $rec_count = 0;
											$label = Utils_RecordBrowserCommon::get_field_tooltip($label, 'commondata', $col);
                                        } else {
                                            if (isset($crits_callback)) {
                                                $crit_callback = explode('::',$crits_callback);
                                                if (is_callable($crit_callback)) {
                                                    $crits = call_user_func($crit_callback, false, $record);
                                                    $adv_crits = call_user_func($crit_callback, true, $record);
                                                } else $crits = $adv_crits = array();
                                                if ($adv_crits === $crits) $adv_crits = null;
                                                if ($adv_crits !== null) {
                                                    $crits = $adv_crits;
                                                }
                                            } else $crits = array();
                                            $col = explode('|',$col);
                                            $col_id = array();
                                            foreach ($col as $c) $col_id[] = preg_replace('/[^a-z0-9]/','_',strtolower($c));
                                            $rec_count = Utils_RecordBrowserCommon::get_records_count($tab, $crits, null, !empty($multi_adv_params['order'])?$multi_adv_params['order']:array());
                                            if ($rec_count<=Utils_RecordBrowserCommon::$options_limit) {
                                                $records = Utils_RecordBrowserCommon::get_records($tab, $crits, empty($multi_adv_params['format_callback'])?$col_id:array(), !empty($multi_adv_params['order'])?$multi_adv_params['order']:array());
                                            } else {
                                                $records = array();
                                            }
                                            $ext_rec = array();
                                            if (isset($record[$args['id']])) {
                                                if (!is_array($record[$args['id']])) {
                                                    if ($record[$args['id']]!='') $record[$args['id']] = array($record[$args['id']]=>$record[$args['id']]); else $record[$args['id']] = array();
                                                }
                                            }
                                            if (isset($this->custom_defaults[$args['id']])) {
                                                if (!is_array($this->custom_defaults[$args['id']]))
                                                    $record[$args['id']][$this->custom_defaults[$args['id']]] = $this->custom_defaults[$args['id']];
                                                else {
                                                    foreach ($this->custom_defaults[$args['id']] as $v)
                                                        $record[$args['id']][$v] = $v;
                                                }
                                            }
                                            $single_column = (count($col_id)==1);
                                            if (isset($record[$args['id']])) {
                                                $ext_rec = array_flip($record[$args['id']]);
                                                foreach($ext_rec as $k=>$v) {
                                                    $c = Utils_RecordBrowserCommon::get_record($tab, $k);
                                                    if (!empty($multi_adv_params['format_callback'])) $n = call_user_func($multi_adv_params['format_callback'], $c);
                                                    else {
                                                        if ($single_column) $n = $c[$col_id[0]];
                                                        else {
                                                            $n = array();
                                                            foreach ($col_id as $cid) $n[] = $c[$cid];
                                                            $n = implode(' ',$n);
                                                        }
                                                    }
                                                    $comp[$k] = $n;
                                                }
                                            }
                                            if (!empty($multi_adv_params['order'])) natcasesort($comp);
                                            foreach ($records as $k=>$v) {
                                                if (!empty($multi_adv_params['format_callback'])) $n = call_user_func($multi_adv_params['format_callback'], $v);
                                                else {
//                                                  $n = $v[$col_id];
                                                    if ($single_column) $n = $v[$col_id[0]];
                                                    else {
                                                        $n = array();
                                                        foreach ($col_id as $cid) $n[] = $v[$cid];
                                                        $n = implode(' ',$n);
                                                    }
                                                }
                                                $comp[$k] = $n;
                                                unset($ext_rec[$v['id']]);
                                            }
                                            if (empty($multi_adv_params['order'])) natcasesort($comp);
											$label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type'], $tab, $crits);

                                        }
										if ($rec_count>Utils_RecordBrowserCommon::$options_limit) {
											$f_callback = $multi_adv_params['format_callback'];
											if ($args['type']=='multiselect') {
												$el = $form->addElement('automulti', $args['id'], $label, array('Utils_RecordBrowserCommon','automulti_suggestbox'), array($this->tab, $crits, $f_callback, $args['param']), $f_callback);
												${'rp_'.$args['id']} = $this->init_module('Utils/RecordBrowser/RecordPicker',array());
												$filters_defaults = isset($multi_adv_params['filters_defaults'])?$multi_adv_params['filters_defaults']:array();
												$this->display_module(${'rp_'.$args['id']}, array($this->tab,$args['id'],$multi_adv_params['format_callback'],$crits,array(),array(),array(),$filters_defaults));
												$el->set_search_button('<a '.${'rp_'.$args['id']}->create_open_href().' '.Utils_TooltipCommon::open_tag_attrs(__('Advanced Selection')).' href="javascript:void(0);"><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','icon_zoom.png').'"></a>');
												} else {
													if (empty($f_callback)) $f_callback = array('Utils_RecordBrowserCommon', 'autoselect_label');
													$form->addElement('autoselect', $args['id'], $label, $comp, array(array('Utils_RecordBrowserCommon','automulti_suggestbox'), array($this->tab, $crits, $f_callback, $args['param'])), $f_callback);
												}
                                        } else {
                                            if ($args['type']==='select') $comp = array(''=>'---')+$comp;
                                            $form->addElement($args['type'], $args['id'], $label, $comp, array('id'=>$args['id']));
                                        }
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                }
            }
            if ($args['required']) {
				$el = $form->getElement($args['id']);
				if (!$form->isError($el)) {
					if ($el->getType()!='static') {
						$form->addRule($args['id'], __('Field required'), 'required');
						$el->setAttribute('placeholder', __('Field required'));
					}
				}
			}
        }
    }
    public function update_record($id,$values) {
        Utils_RecordBrowserCommon::update_record($this->tab, $id, $values);
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function administrator_panel() {
        $_SESSION['client']['recordbrowser']['admin_access'] = Base_AdminCommon::get_access('Utils_RecordBrowser', 'records')==2;
        Utils_RecordBrowserCommon::$admin_access = Base_AdminCommon::get_access('Utils_RecordBrowser', 'records')==2;
        $this->init();
        $tb = $this->init_module('Utils/TabbedBrowser');
		
		$tabs = array(
		array(
			'access'=>'records',
			'func'=>array($this, 'show_data'),
			'label'=>__('Manage Records'),
			'args'=>array(array(), array(), array(), Base_AdminCommon::get_access('Utils_RecordBrowser', 'records')==2)
		),
		array(
			'access'=>'fields',
			'func'=>array($this, 'setup_loader'),
			'label'=>__('Manage Fields'),
			'args'=>array()
		),
		array(
			'access'=>'addons',
			'func'=>array($this, 'manage_addons'),
			'label'=>__('Manage Addons'),
			'args'=>array()
		),
		array(
			'access'=>'permissions',
			'func'=>array($this, 'manage_permissions'),
			'label'=>__('Permissions'),
			'args'=>array()
		),
		array(
			'access'=>'pattern',
			'func'=>array($this, 'setup_clipboard_pattern'),
			'label'=>__('Clipboard Pattern'),
			'args'=>array()
		)
		);
		foreach($tabs as $t) {
			$access = Base_AdminCommon::get_access('Utils_RecordBrowser', $t['access']);
			if ($access!=0)
				$tb->set_tab($t['label'], $t['func'], $t['args']);
		}

        $tb->body();
        $tb->tag();
    }

    public function set_addon_active($tab, $pos, $v) {
        DB::Execute('UPDATE recordbrowser_addon SET enabled=%d WHERE tab=%s AND pos=%d', array($v?1:0, $tab, $pos));
        return false;
    }

    public function move_addon($tab, $pos, $v) {
        DB::StartTrans();
        DB::Execute('UPDATE recordbrowser_addon SET pos=0 WHERE tab=%s AND pos=%d', array($tab, $pos));
        DB::Execute('UPDATE recordbrowser_addon SET pos=%d WHERE tab=%s AND pos=%d', array($pos, $tab, $pos+$v));
        DB::Execute('UPDATE recordbrowser_addon SET pos=%d WHERE tab=%s AND pos=0', array($pos+$v, $tab));
        DB::CompleteTrans();
        return false;
    }

    public function manage_addons() {
		$full_access = Base_AdminCommon::get_access('Utils_RecordBrowser', 'addons')==2;

        $gb = $this->init_module('Utils/GenericBrowser','manage_addons'.$this->tab, 'manage_addons'.$this->tab);
        $gb->set_table_columns(array(
                                array('name'=>__('Addon caption')),
                                array('name'=>__('Called method'))
                                ));
        $add = DB::GetAll('SELECT * FROM recordbrowser_addon WHERE tab=%s ORDER BY pos',array($this->tab));
        $first = true;
        foreach ($add as $v) {
            if (isset($gb_row) && $full_access) $gb_row->add_action($this->create_callback_href(array($this, 'move_addon'),array($v['tab'],$v['pos']-1, +1)),'Move down', null, 'move-down');
            $gb_row = $gb->get_new_row();
            $gb_row->add_data($v['label'], $v['module'].' -> '.$v['func'].'()');
			if ($full_access) {
				$gb_row->add_action($this->create_callback_href(array($this, 'set_addon_active'), array($v['tab'],$v['pos'],!$v['enabled'])), ($v['enabled']?'Dea':'A').'ctivate', null, 'active-'.($v['enabled']?'on':'off'));

				if (!$first) $gb_row->add_action($this->create_callback_href(array($this, 'move_addon'),array($v['tab'],$v['pos'], -1)),'Move up', null, 'move-up');
				$first = false;
			}
        }
        $this->display_module($gb);
    }

    public function new_page() {
        DB::StartTrans();
        $max_f = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field');
        $num = 1;
        do {
            $num++;
            $x = DB::GetOne('SELECT position FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field = %s', array('Details '.$num));
        } while ($x!==false && $x!==null);
        DB::Execute('INSERT INTO '.$this->tab.'_field (field, type, extra, position) VALUES(%s, \'page_split\', 1, %d)', array('Details '.$num, $max_f+1));
        DB::CompleteTrans();
    }
    public function delete_page($id) {
        DB::StartTrans();
        $p = DB::GetOne('SELECT position FROM '.$this->tab.'_field WHERE field=%s', array($id));
        DB::Execute('UPDATE '.$this->tab.'_field SET position = position-1 WHERE position > %d', array($p));
        DB::Execute('DELETE FROM '.$this->tab.'_field WHERE field=%s', array($id));
        DB::CompleteTrans();
    }
    public function edit_page($id) {
        if ($this->is_back())
            return false;
        $this->init();
        $form = $this->init_module('Libs/QuickForm', null, 'edit_page');

        $form->addElement('header', null, __('Edit page properties'));
        $form->addElement('text', 'label', __('Label'));
        $this->current_field = $id;
        $form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', $this);
        $form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', $this);
        $form->addRule('label', __('Field required'), 'required');
        $form->addRule('label', __('Field or Page with this name already exists.'), 'check_if_column_exists');
        $form->addRule('label', __('Only letters and space are allowed.'), 'regex', '/^[a-zA-Z ]*$/');
        $form->addRule('label', __('"ID" as page name is not allowed.'), 'check_if_no_id');
        $form->setDefaults(array('label'=>$id));

        if($form->validate()) {
            $data = $form->exportValues();
            foreach($data as $key=>$val)
                $data[$key] = htmlspecialchars($val);
            DB::Execute('UPDATE '.$this->tab.'_field SET field=%s WHERE field=%s',
                        array($data['label'], $id));
            $this->init(true, true);
            return false;
        }
        $form->display();
		Base_ActionBarCommon::add('back',__('Cancel'),$this->create_back_href());
		Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());

        return true;
    }
    public function setup_clipboard_pattern() {
		$full_access = Base_AdminCommon::get_access('Utils_RecordBrowser', 'pattern')==2;
        $form = $this->init_module('Libs/QuickForm');
        $r = Utils_RecordBrowserCommon::get_clipboard_pattern($this->tab, true);
        $form->addElement('select', 'enable', __('Enable'), array(__('No'), __('Yes')));
        $info = '<b>'.__('This is an html pattern. All html tags are allowed.').'<br/>'.__('Use &lt;pre&gt; some text &lt;/pre&gt; to generate text identical as you typed it.').'<br/><br/>'.__('Conditional use:').'<br/>'.__('%%{lorem {keyword} ipsum {keyword2}}').'<br/>'.__('lorem ipsum will be shown only when at least one of keywords has a value. Nested conditions are allowed.').'<br/><br/>'.__('Normal use:').'<br/>'.__('%%{{keyword}}').'<br/><br/>'.__('Keywords:').'<br/></b>';
        foreach($this->table_rows as $name=>$val) {
            $info .= '<b>'.$val['id'].'</b> - '.$name.', ';
        }
        $label = '<img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser', 'info.png').'" '.Utils_TooltipCommon::open_tag_attrs($info).'/> '.__('Pattern');
        $textarea = $form->addElement('textarea', 'pattern', $label);
        $textarea->setRows(12);
        $textarea->setCols(80);
		if ($full_access) {
			Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		} else {
			$form->freeze();
		}
        if($r) $form->setDefaults(array('enable'=>($r['enabled']?1:0), 'pattern'=>$r['pattern']));
        else $form->setDefaults(array('enable'=>0));
        $form->display();
        if ($form->validate()) {
            $enable = $form->exportValue('enable');
            $pattern = $form->exportValue('pattern');
            Utils_RecordBrowserCommon::set_clipboard_pattern($this->tab, $pattern, $enable, true);
        }
    }
    public function setup_loader() {
        $this->init(true);
        $action = $this->get_module_variable_or_unique_href_variable('setup_action', 'show');
        $subject = $this->get_module_variable_or_unique_href_variable('subject', 'regular');
		
		$full_access = Base_AdminCommon::get_access('Utils_RecordBrowser', 'fields')==2;

		if ($full_access) {
			Base_ActionBarCommon::add('add',__('New field'),$this->create_callback_href(array($this, 'view_field')));
			Base_ActionBarCommon::add('add',__('New page'),$this->create_callback_href(array($this, 'new_page')));
		}
        $gb = $this->init_module('Utils/GenericBrowser', null, 'fields');
        $gb->set_table_columns(array(
            array('name'=>__('Field'), 'width'=>20),
            array('name'=>__('Type'), 'width'=>10),
            array('name'=>__('Table view'), 'width'=>5),
            array('name'=>__('Required'), 'width'=>5),
            array('name'=>__('Filter'), 'width'=>5),
            array('name'=>__('Parameters'), 'width'=>27),
            array('name'=>__('Value display function'), 'width'=>5),
            array('name'=>__('Field generator function'), 'width'=>5)
		));
		
		$display_callbacbacks = DB::GetAssoc('SELECT field, callback FROM '.$this->tab.'_callback WHERE freezed=1');
		$QFfield_callbacbacks = DB::GetAssoc('SELECT field, callback FROM '.$this->tab.'_callback WHERE freezed=0');

        //read database
        $rows = end($this->table_rows);
		$rows = $rows['position'];
        foreach($this->table_rows as $field=>$args) {
            $gb_row = $gb->get_new_row();
			if ($full_access) {
				if ($args['type'] != 'page_split') {
					$gb_row->add_action($this->create_callback_href(array($this, 'view_field'),array('edit',$field)),'Edit');
				} elseif ($field!='General') {
					$gb_row->add_action($this->create_callback_href(array($this, 'delete_page'),array($field)),'Delete');
					$gb_row->add_action($this->create_callback_href(array($this, 'edit_page'),array($field)),'Edit');
				}
				if ($args['type']!=='page_split' && $args['extra']){
					if ($args['active']) $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, false)),'Deactivate', null, 'active-on');
					else $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, true)),'Activate', null, 'active-off');
				}
				if ($args['position']<$rows && $args['position']>2)
					$gb_row->add_action($this->create_callback_href(array($this, 'move_field'),array($field, $args['position'], +1)),'Move down', null, 'move-down');
				if ($args['position']>3)
					$gb_row->add_action($this->create_callback_href(array($this, 'move_field'),array($field, $args['position'], -1)),'Move up', null, 'move-up');
			}
            if ($args['type']=='text')
                $args['param'] = __('Length').' '.$args['param'];
            if ($args['type'] == 'page_split')
                    $gb_row->add_data(
                        array('style'=>'background-color: #DFDFFF;', 'value'=>$field),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>__('Page Split')),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>'')
                    );
                else {
					if (isset($display_callbacbacks[$field])) {
						$d_c = '<b>Yes</b>';
						if (!is_callable(explode('::', $display_callbacbacks[$field]))) $d_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
						$d_c = Utils_TooltipCommon::create($d_c, $display_callbacbacks[$field], false);
					} else $d_c = '';
					if (isset($QFfield_callbacbacks[$field])) {
						$QF_c = '<b>Yes</b>';
						if (!is_callable(explode('::', $QFfield_callbacbacks[$field]))) $QF_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
						$QF_c = Utils_TooltipCommon::create($QF_c, $QFfield_callbacbacks[$field], false);
					} else $QF_c = '';
                    $gb_row->add_data(
                        $field,
                        $args['type'],
                        $args['visible']?__('<b>Yes</b>'):__('No'),
                        $args['required']?__('<b>Yes</b>'):__('No'),
                        $args['filter']?__('<b>Yes</b>'):__('No'),
                        is_array($args['param'])?serialize($args['param']):$args['param'],
						$d_c,
						$QF_c
                    );
				}
        }
        $this->display_module($gb);
    }
    public function move_field($field, $pos, $dir){
        DB::StartTrans();
        DB::Execute('UPDATE '.$this->tab.'_field SET position=%d WHERE position=%d',array($pos, $pos+$dir));
        DB::Execute('UPDATE '.$this->tab.'_field SET position=%d WHERE field=%s',array($pos+$dir, $field));
        DB::CompleteTrans();
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function set_field_active($field, $set=true) {
        DB::Execute('UPDATE '.$this->tab.'_field SET active=%d WHERE field=%s',array($set?1:0,$field));
        return false;
    } //submit_delete_field
    //////////////////////////////////////////////////////////////////////////////////////////
    public function view_field($action = 'add', $field = null) {
        if (!$action) $action = 'add';
        if ($this->is_back()) return false;
        $data_type = array(
            'currency'=>'currency',
            'checkbox'=>'checkbox',
            'date'=>'date',
            'integer'=>'integer',
            'float'=>'float',
            'text'=>'text',
            'long text'=>'long text',
			'commondata'=>'commondata'
        );
        natcasesort($data_type);

        $form = $this->init_module('Libs/QuickForm');

        switch ($action) {
            case 'add': $form->addElement('header', null, __('Add new field'));
                        break;
            case 'edit': $form->addElement('header', null, __('Edit field properties'));
                        break;
        }
        $form->addElement('text', 'field', __('Field'), array('maxlength'=>32));
        $form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', $this);
        $this->current_field = $field;
        $form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', $this);
        $form->addRule('field', __('Field required'), 'required');
        $form->addRule('field', __('Field with this name already exists.'), 'check_if_column_exists');
        $form->addRule('field', __('Field length cannot be over 32 characters.'), 'maxlength', 32);
        $form->addRule('field', __('Invalid field name.'), 'regex', '/^[a-zA-Z][a-zA-Z \(\)\%0-9]*$/');
        $form->addRule('field', __('"ID" as field name is not allowed.'), 'check_if_no_id');


        if ($action=='edit') {
            $row = DB::GetRow('SELECT field, type, visible, required, param, filter, extra FROM '.$this->tab.'_field WHERE field=%s',array($field));
            $form->setDefaults($row);
            $form->addElement('static', 'select_data_type', __('Data Type'), $row['type']);
			if (!$row['extra']) $form->freeze('field');
            $selected_data= $row['type'];
        } else {
            $form->addElement('select', 'select_data_type', __('Data Type'), $data_type);
            $selected_data= $form->exportValue('select_data_type');
            $form->setDefaults(array('visible'=>1));
        }
        switch($selected_data) {
            case 'text':
                if ($action=='edit')
                    $form->addElement('static', 'text_length', __('Length'), $row['param']);
                else {
                    $form->addElement('text', 'text_length', __('Length'));
                    $form->addRule('text_length', __('Field required'), 'required');
                    $form->addRule('text_length', __('Must be a number greater than 0.'), 'regex', '/^[1-9][0-9]*$/');
                }
                break;
			case 'multiselect':
				if ($action=='edit') {
					@list($tab, $col) = explode('::', $row['param']);
					if ($tab=='__COMMON__') {
						$row['param'] = '__'.$col;
						$form->setDefaults(array('select_type'=>'multi'));
					} else {
						break;
					}
				}
			case 'commondata':
				$form->addElement('text', 'commondata_table', __('CommonData table'));
				$form->addElement('select', 'select_type', __('Type'), array('select'=>__('Single value selection'), 'multi'=>__('Multiple values selection')));
				$form->addElement('select', 'order_by', __('Order by'), array('key'=>__('Key'), 'value'=>__('Value')));
				$form->addRule('commondata_table', __('Field required'), 'required');
				if ($action=='edit') {
					$param = Utils_RecordBrowserCommon::decode_commondata_param($row['param']);
					$form->setDefaults(array('order_by'=>$param['order_by_key']?'key':'value', 'commondata_table'=>$param['array_id']));
				}
				break;
        }
        $form->addElement('checkbox', 'visible', __('Table view'));
        $form->addElement('checkbox', 'required', __('Required'));
        $form->addElement('checkbox', 'filter', __('Filter enabled'));

        $form->addElement('header', null, __('For advanced users'));
        $form->addElement('text', 'display_callback', __('Value display function'), array('maxlength'=>255, 'style'=>'width:300px'));
        $form->addElement('text', 'QFfield_callback', __('Field generator function'), array('maxlength'=>255, 'style'=>'width:300px'));
		
		if ($action=='edit') {
			$display_callbacback = DB::GetOne('SELECT callback FROM '.$this->tab.'_callback WHERE freezed=1 AND field=%s', array($field));
			$QFfield_callbacback = DB::GetOne('SELECT callback FROM '.$this->tab.'_callback WHERE freezed=0 AND field=%s', array($field));
			$form->setDefaults(array('display_callback'=>$display_callbacback));
			$form->setDefaults(array('QFfield_callback'=>$QFfield_callbacback));
		}

        if ($form->validate()) {
            $data = $form->exportValues();
            $data['field'] = trim($data['field']);
			$type = DB::GetOne('SELECT type FROM '.$this->tab.'_field WHERE field=%s', array($field));
			if (!isset($data['select_data_type'])) $data['select_data_type'] = $type;
            if ($action=='add')
                $field = $data['field'];
            $id = preg_replace('/[^a-z0-9]/','_',strtolower($field));
            $new_id = preg_replace('/[^a-z0-9]/','_',strtolower($data['field']));
            if (preg_match('/^[a-z0-9_]*$/',$id)==0) trigger_error('Invalid column name: '.$field);
            if (preg_match('/^[a-z0-9_]*$/',$new_id)==0) trigger_error('Invalid new column name: '.$data['field']);
			$param = '';
			switch ($data['select_data_type']) {
				case 'text': if ($action=='add') $param = $data['text_length'];
							 else $param = $row['param'];
								break;
				case 'commondata':
							if ($data['select_type']=='select') {
								$param = Utils_RecordBrowserCommon::encode_commondata_param(array('order_by_key'=>$data['order_by']=='key', 'array_id'=>$data['commondata_table']));
							} else {
								$param = '__COMMON__::'.$data['commondata_table'];
								$data['select_data_type'] = 'multiselect';
							}
							if ($action!='add')
								DB::Execute('UPDATE '.$this->tab.'_field SET param=%s WHERE field=%s', array($param, $field));
							break;
				case 'multiselect':
							$param = '__COMMON__::'.$data['commondata_table'];
							$data['select_data_type'] = 'multiselect';
							break;
			}
            if ($action=='add') {
                $id = $new_id;
                if (in_array($data['select_data_type'], array('time','timestamp','currency','integer')))
                    $style = $data['select_data_type'];
                else
                    $style = '';
                Utils_RecordBrowserCommon::new_record_field($this->tab, $data['field'], $data['select_data_type'], 0, 0, $param, $style);
            }
            if(!isset($data['visible']) || $data['visible'] == '') $data['visible'] = 0;
            if(!isset($data['required']) || $data['required'] == '') $data['required'] = 0;
            if(!isset($data['filter']) || $data['filter'] == '') $data['filter'] = 0;

            foreach($data as $key=>$val)
                $data[$key] = htmlspecialchars($val);

            DB::StartTrans();
            if ($id!=$new_id) {
                Utils_RecordBrowserCommon::check_table_name($this->tab);
                if(DATABASE_DRIVER=='postgres')
                    DB::Execute('ALTER TABLE '.$this->tab.'_data_1 RENAME COLUMN f_'.$id.' TO f_'.$new_id);
                else {
                    $old_param = DB::GetOne('SELECT param FROM '.$this->tab.'_field WHERE field=%s', array($field));
                    DB::RenameColumn($this->tab.'_data_1', 'f_'.$id, 'f_'.$new_id, Utils_RecordBrowserCommon::actual_db_type($type, $old_param));
                }
            }
            DB::Execute('UPDATE '.$this->tab.'_field SET param=%s, type=%s, field=%s, visible=%d, required=%d, filter=%d WHERE field=%s',
                        array($param, $data['select_data_type'], $data['field'], $data['visible'], $data['required'], $data['filter'], $field));
            DB::Execute('UPDATE '.$this->tab.'_edit_history_data SET field=%s WHERE field=%s',
                        array($new_id, $id));
            DB::CompleteTrans();
			
			DB::Execute('DELETE FROM '.$this->tab.'_callback WHERE freezed=1 AND field=%s', array($field));
			if ($data['display_callback'])
				DB::Execute('INSERT INTO '.$this->tab.'_callback (callback,freezed,field) VALUES (%s,1,%s)', array($data['display_callback'], $data['field']));
				
			DB::Execute('DELETE FROM '.$this->tab.'_callback WHERE freezed=0 AND field=%s', array($field));
			if ($data['QFfield_callback'])
				DB::Execute('INSERT INTO '.$this->tab.'_callback (callback,freezed,field) VALUES (%s,0,%s)', array($data['QFfield_callback'], $data['field']));
			
            $this->init(true, true);
            return false;
        }
        $form->display();
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		Base_ActionBarCommon::add('back', __('Cancel'), $this->create_back_href());
		
        return true;
    }
    public function check_if_no_id($arg){
        return !preg_match('/^[iI][dD]$/',$arg);
    }
    public function check_if_column_exists($arg){
        $this->init(true);
        if (strtolower($arg)==strtolower($this->current_field)) return true;
        foreach($this->table_rows as $field=>$args)
            if (strtolower($args['name']) == strtolower($arg))
                return false;
        return true;
    }
    public function dirty_read_changes($id, $time_from) {
        print('<b>'.__('The following changes were applied to this record while you were editing it.<br>Please revise this data and make sure to keep this record most accurate.').'</b><br>');
        $gb_cha = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__changes');
        $table_columns_changes = array( array('name'=>__('Date'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Username'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Field'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Old value'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('New value'), 'width'=>10, 'wrapmode'=>'nowrap'));
        $gb_cha->set_table_columns( $table_columns_changes );

        $created = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
        $field_hash = array();
        foreach($this->table_rows as $field => $args)
            $field_hash[$args['id']] = $field;
        $ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.edited_on>=%T AND c.'.$this->tab.'_id=%d ORDER BY edited_on DESC',array($time_from,$id));
        while ($row = $ret->FetchRow()) {
            $changed = array();
            $ret2 = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
            while($row2 = $ret2->FetchRow()) {
                if (isset($changed[$row2['field']])) {
                    if (is_array($changed[$row2['field']]))
                        array_unshift($changed[$row2['field']], $row2['old_value']);
                    else
                        $changed[$row2['field']] = array($row2['old_value'], $changed[$row2['field']]);
                } else {
                    $changed[$row2['field']] = $row2['old_value'];
                }
                if (is_array($changed[$row2['field']]))
                    sort($changed[$row2['field']]);
            }
            foreach($changed as $k=>$v) {
                $new = $this->get_val($field_hash[$k], $created, false, $this->table_rows[$field_hash[$k]]);
                $created[$k] = $v;
                $old = $this->get_val($field_hash[$k], $created, false, $this->table_rows[$field_hash[$k]]);
                $gb_row = $gb_cha->get_new_row();
//              eval_js('apply_changes_to_'.$k.'=function(){element = document.getElementsByName(\''.$k.'\')[0].value=\''.$v.'\';};');
//              $gb_row->add_action('href="javascript:apply_changes_to_'.$k.'()"', 'Apply', null, 'apply');
                $gb_row->add_data(
                    Base_RegionalSettingsCommon::time2reg($row['edited_on']),
                    $row['edited_by']!==null?Base_UserCommon::get_user_label($row['edited_by']):'',
                    $field_hash[$k],
                    $old,
                    $new
                );
            }
        }
        $theme = $this->init_module('Base/Theme');
        $theme->assign('table',$this->get_html_of_module($gb_cha));
        $theme->assign('label',__('Recent Changes'));
        $theme->display('View_dirty_read');
    }
    public function view_edit_history($id){
		load_js('modules/Utils/RecordBrowser/edit_history.js');
        if ($this->is_back())
            return $this->back();
        $this->init();
		$tb = $this->init_module('Utils_TabbedBrowser');		
        $gb_cha = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__changes');
		$form = $this->init_module('Libs_QuickForm');

        $table_columns_changes = array( array('name'=>__('Date'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Username'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Field'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Old value'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('New value'), 'width'=>10, 'wrapmode'=>'nowrap'));

        $gb_cha->set_table_columns( $table_columns_changes );

        $gb_cha->set_inline_display();

        $created = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
        $access = $this->get_access('view', $created);
        $field_hash = array();
        $edited = DB::GetRow('SELECT ul.login, c.edited_on FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->tab.'_id=%d ORDER BY edited_on DESC',array($id));
        foreach($this->table_rows as $field => $args)
            $field_hash[$args['id']] = $field;

        $ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->tab.'_id=%d ORDER BY edited_on DESC, id DESC',array($id));
		$dates_select = array();
		$tb_path = escapeJS($tb->get_path());
        while ($row = $ret->FetchRow()) {
			$user = Base_UserCommon::get_user_label($row['edited_by']);
			$date_and_time = Base_RegionalSettingsCommon::time2reg($row['edited_on']);
			$dates_select[$row['edited_on']] = $date_and_time;
            $changed = array();
            $ret2 = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
            while($row2 = $ret2->FetchRow()) {
                if ($row2['field']!='id' && (!isset($access[$row2['field']]) || !$access[$row2['field']])) continue;
                $changed[$row2['field']] = $row2['old_value'];
                $last_row = $row2;
            }
            foreach($changed as $k=>$v) {
                if ($k=='id') {
					$gb_cha->add_row(
						$date_and_time, 
						$user, 
						array('value'=>_V($last_row['old_value']), 'attrs'=>'colspan="3" style="text-align:center;font-weight:bold;"'),
						array('value'=>'', 'dummy'=>true),
						array('value'=>'', 'dummy'=>true)
					);
                } else {
                    if (!isset($field_hash[$k])) continue;
                    $new = $this->get_val($field_hash[$k], $created, false, $this->table_rows[$field_hash[$k]]);
                    if ($this->table_rows[$field_hash[$k]]['type']=='multiselect') $v = Utils_RecordBrowserCommon::decode_multi($v);
                    $created[$k] = $v;
                    $old = $this->get_val($field_hash[$k], $created, false, $this->table_rows[$field_hash[$k]]);
					$gb_row = $gb_cha->get_new_row();
					$gb_row->add_action('href="javascript:void(0);" onclick="recordbrowser_edit_history_jump(\''.$row['edited_on'].'\',\''.$this->tab.'\','.$created['id'].',\''.$form->get_name().'\');tabbed_browser_switch(1,2,null,\''.$tb_path.'\')"','View');
                    $gb_row->add_data(
                        $date_and_time,
                        $row['edited_by']!==null?$user:'',
                        _V($field_hash[$k]), // TRSL
                        $old,
                        $new
                    );
                }
            }
        }

		$gb_row = $gb_cha->get_new_row();
		$gb_row->add_data(
			Base_RegionalSettingsCommon::time2reg($created['created_on']),
			$created['created_by']!==null?Base_UserCommon::get_user_label($created['created_by']):'',
			array('value'=>__('RECORD CREATED'), 'attrs'=>'colspan="3" style="text-align:center;font-weight:bold;"'),
			array('value'=>'', 'dummy'=>true),
			array('value'=>'', 'dummy'=>true)
		);


//		$tb->set_tab(__('Record historical view'), array($this, 'record_historical_view'), array($created, $access, $form, $dates_select), true);
		$tb->start_tab(__('Changes History'));
		$this->display_module($gb_cha);
		$tb->end_tab();

		$tb->start_tab(__('Record historical view'));
		$dates_select[$created['created_on']] = Base_RegionalSettingsCommon::time2reg($created['created_on']);
        foreach($this->table_rows as $field => $args) {
            if (!$access[$args['id']]) continue;
            $val = $this->get_val($field, $created, false, $args);
        }
		$form->addElement('select', 'historical_view_pick_date', __('View the record as of'), $dates_select, array('onChange'=>'recordbrowser_edit_history("'.$this->tab.'",'.$created['id'].',"'.$form->get_name().'");', 'id'=>'historical_view_pick_date'));
		$form->setDefaults(array('historical_view_pick_date'=>$created['created_on']));
		$form->display();
		$this->view_entry('history', $created);
		$tb->end_tab();

		
		$this->display_module($tb);
        Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
        return true;
    }
	
	public function record_historical_view($created, $access, $form, $dates_select) {
	}

    public function set_active($id, $state=true){
        Utils_RecordBrowserCommon::set_active($this->tab, $id, $state);
        return false;
    }
    public function set_defaults($arg, $multiple=false){
        foreach ($arg as $k=>$v)
            $this->custom_defaults[$k] = $v;
        if ($multiple) $this->multiple_defaults = true;
    }
	public function crm_perspective_default() {
		return '__PERSPECTIVE__';
	}
    public function set_filters_defaults($arg){
		if(!$this->isset_module_variable('def_filter')) {
			$f = array();
			if(is_array($arg)) {
				foreach ($arg as $k=>$v) {
					$f['filter__'.$k] = $v;
				}
			}
			$this->set_module_variable('def_filter', $f);
		}
    }
    public function set_default_order($arg){
        foreach ($arg as $k=>$v)
            $this->default_order[$k] = $v;
    }
    public function force_order($arg){
        $this->force_order = $arg;
    }
    public function caption(){
        return $this->caption.': '.$this->action;
    }
    public function recordpicker($element, $format, $crits=array(), $cols=array(), $order=array(), $filters=array()) {
        $this->init();
        $this->set_module_variable('element',$element);
        $this->set_module_variable('format_func',$format);
        $theme = $this->init_module('Base/Theme');
        Base_ThemeCommon::load_css($this->get_type(),'Browsing_records');
        $theme->assign('filters', $this->show_filters($filters, $element));
        $theme->assign('disabled', '');
        foreach ($crits as $k=>$v) {
            if (!is_array($v)) $v = array($v);
            if (isset($this->crits[$k]) && !empty($v)) {
                foreach ($v as $w) if (!in_array($w, $this->crits[$k])) $this->crits[$k][] = $w;
            } else $this->crits[$k] = $v;
        }
        $theme->assign('table', $this->show_data($this->crits, $cols, $order, false, true));
        if ($this->amount_of_records>=10000) {
            $theme->assign('select_all', array('js'=>'', 'label'=>__('Select all')));
            $theme->assign('deselect_all', array('js'=>'', 'label'=>__('Deselect all')));
        } else {
            load_js('modules/Utils/RecordBrowser/RecordPicker/select_all.js');
            $theme->assign('select_all', array('js'=>'RecordPicker_select_all(1,\''.$this->get_path().'\',\''.__('Processing...').'\');', 'label'=>__('Select all')));
            $theme->assign('deselect_all', array('js'=>'RecordPicker_select_all(0,\''.$this->get_path().'\',\''.__('Processing...').'\');', 'label'=>__('Deselect all')));
        }
        $theme->assign('close_leightbox', array('js'=>'leightbox_deactivate(\'rpicker_leightbox_'.$element.'\');', 'label'=>__('Commit Selection')));
        load_js('modules/Utils/RecordBrowser/rpicker.js');

        $rpicker_ind = $this->get_module_variable('rpicker_ind');
        foreach($rpicker_ind as $v) {
            eval_js('rpicker_init(\''.$element.'\','.$v.')');
        }
        $theme->display('Record_picker');
    }
    public function recordpicker_fs($crits, $cols, $order, $filters, $path) {
		self::$browsed_records = array();
        $this->init();
        $theme = $this->init_module('Base/Theme');
        Base_ThemeCommon::load_css($this->get_type(),'Browsing_records');
        $this->set_module_variable('rp_fs_path',$path);
        $selected = Module::static_get_module_variable($path,'selected',array());
        $theme->assign('filters', $this->show_filters($filters));
        $theme->assign('disabled', '');
        foreach ($crits as $k=>$v) {
            if (!is_array($v)) $v = array($v);
            if (isset($this->crits[$k]) && !empty($v)) {
                foreach ($v as $w) if (!in_array($w, $this->crits[$k])) $this->crits[$k][] = $w;
            } else $this->crits[$k] = $v;
        }
        $theme->assign('table', $this->show_data($this->crits, $cols, $order, false, true));
		if (empty(self::$browsed_records)) return;
        if ($this->amount_of_records>=10000) {
            $theme->assign('disabled', '_disabled');
            $theme->assign('select_all', array('js'=>'', 'label'=>__('Select all')));
            $theme->assign('deselect_all', array('js'=>'', 'label'=>__('Deselect all')));
        } else {
            load_js('modules/Utils/RecordBrowser/RecordPickerFS/select_all.js');
            $theme->assign('select_all', array('js'=>'RecordPicker_select_all(1,\''.$this->get_path().'\',\''.__('Processing...').'\');', 'label'=>__('Select all')));
            $theme->assign('deselect_all', array('js'=>'RecordPicker_select_all(0,\''.$this->get_path().'\',\''.__('Processing...').'\');', 'label'=>__('Deselect all')));
        }

        load_js('modules/Utils/RecordBrowser/rpicker_fs.js');
        foreach(self::$browsed_records['records'] as $id=>$i) {
            eval_js('rpicker_fs_init('.$id.','.(isset($selected[$id]) && $selected[$id]?1:0).',\''.$this->get_path().'\')');
        }
/*
        $rpicker_ind = $this->get_module_variable('rpicker_ind');
        $init_func = 'init_all_rpicker_'.$element.' = function(id, cstring){';
        foreach($rpicker_ind as $v)
            $init_func .= 'rpicker_init(\''.$element.'\','.$v.');';
        $init_func .= '}';
        eval_js($init_func.';init_all_rpicker_'.$element.'();');*/
        $theme->display('Record_picker');
    }
    public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());

        $ret = DB::Execute('SELECT tab, caption FROM recordbrowser_table_properties');
        $form = $this->init_module('Libs/QuickForm');
        $opts = array();
        $first = false;
        while ($row=$ret->FetchRow()) {
            $text = $row['caption'] ? $row['caption'] : $row['tab'];
            $opts[$row['tab']] = _V($text);
        }
		asort($opts);
		$first = array_keys($opts);
		$first = reset($first);
        $form->addElement('select', 'recordset', __('Recordset'), $opts, array('onchange'=>$form->get_submit_form_js()));
        if ($form->validate()) {
            $tab = $form->exportValue('recordset');
            $this->set_module_variable('admin_browse_recordset', $tab);
        }
        $tab = $this->get_module_variable('admin_browse_recordset', $first);
        $form->setDefaults(array('recordset'=>$tab));
        $form->display_as_column();
        if ($tab) {
			$this->record_management($tab);
		}
    }
    public function record_management($table){
		$this->tab = $table;
		$this->administrator_panel();
    }

    public function enable_quick_new_records($button = true, $force_show = null) {
        $this->add_in_table = true;
        if ($button) $this->add_button = 'href="javascript:void(0);" onclick="$(\'add_in_table_row\').style.display=($(\'add_in_table_row\').style.display==\'none\'?\'\':\'none\');if(focus_on_field)if($(focus_on_field))focus_by_id(focus_on_field);"';
        if ($force_show===null) $this->show_add_in_table = Base_User_SettingsCommon::get('Utils_RecordBrowser','add_in_table_shown');
        else $this->show_add_in_table = $force_show;
        if ($this->get_module_variable('force_add_in_table_after_submit', false)) {
            $this->show_add_in_table = true;
            $this->set_module_variable('force_add_in_table_after_submit', false);
        }
    }
	
    public function set_custom_filter($arg, $spec){
        $this->custom_filters[$arg] = $spec;
    }

    public function set_no_limit_in_mini_view($arg){
        $this->set_module_variable('no_limit_in_mini_view',$arg);
    }

    public function mini_view($cols, $crits, $order, $info=null, $limit=null, $conf = array('actions_edit'=>true, 'actions_info'=>true), & $opts = array()){
        unset($_SESSION['client']['recordbrowser']['admin_access']);
        $this->init();
        $gb = $this->init_module('Utils/GenericBrowser',$this->tab,$this->tab);
        $field_hash = array();
        foreach($this->table_rows as $field => $args)
            $field_hash[$args['id']] = $field;
        $header = array();
        $callbacks = array();
        foreach($cols as $k=>$v) {
            if (isset($v['callback'])) $callbacks[] = $v['callback'];
            else $callbacks[] = null;
            if (is_array($v)) {
                $arr = array('name'=>_V($field_hash[$v['field']])); // TRSL
				if (isset($v['width'])) $arr['width'] = $v['width'];
                $cols[$k] = $v['field'];
            } else {
                $arr = array('name'=>_V($field_hash[$v])); // TRSL
                $cols[$k] = $v;
            }
            if (isset($v['label'])) $arr['name'] = $v['label'];
            $arr['wrapmode'] = 'nowrap';
            $header[] = $arr;
        }
        $gb->set_table_columns($header);

        $clean_order = array();
        foreach($order as $k=>$v) {
    	    if ($k==':Visited_on') $field_hash[$k] = $k;
    	    if ($k==':Fav') $field_hash[$k] = $k;
    	    if ($k==':Edited_on') $field_hash[$k] = $k;
            if ($k==':id') $field_hash[$k] = $k;
            $clean_order[] = array('column'=>$field_hash[$k],'order'=>$field_hash[$k],'direction'=>$v);
        }
        if ($limit!=null && !isset($conf['force_limit'])) {
            $limit = array('offset'=>0, 'numrows'=>$limit);
            $records_qty = Utils_RecordBrowserCommon::get_records_count($this->tab, $crits);
            if ($records_qty>$limit['numrows']) {
                if ($this->get_module_variable('no_limit_in_mini_view',false)) {
                    $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('Display first %d records', array($limit['numrows']))).' '.$this->create_callback_href(array($this, 'set_no_limit_in_mini_view'), array(false)).'><img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','show_some.png').'" border="0"></a>';
                    $limit = null;
                } else {
                    print(__('Displaying %s of %s records', array($limit['numrows'], $records_qty)));
                    $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('Display all records')).' '.$this->create_callback_href(array($this, 'set_no_limit_in_mini_view'), array(true)).'><img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','show_all.png').'" border="0"></a>';
                }
            }
        }
        $records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, array(), $clean_order, $limit);
        foreach($records as $v) {
            $gb_row = $gb->get_new_row();
            $arr = array();
            foreach($cols as $k=>$w) {
                if (!isset($callbacks[$k])) $s = $this->get_val($field_hash[$w], $v, false, $this->table_rows[$field_hash[$w]]);
                else $s = call_user_func($callbacks[$k], $v);
                $arr[] = $s;
            }
            $gb_row->add_data_array($arr);
            if (is_callable($info)) {
                $additional_info = call_user_func($info, $v);
            } else $additional_info = '';
            if (!is_array($additional_info) && isset($additional_info)) $additional_info = array('notes'=>$additional_info);
            if (isset($additional_info['notes'])) $additional_info['notes'] = $additional_info['notes'].'<hr />';
            if (isset($additional_info['row_attrs'])) $gb_row->set_attrs($additional_info['row_attrs']);
            if (isset($conf['actions_info']) && $conf['actions_info']) $gb_row->add_info($additional_info['notes'].Utils_RecordBrowserCommon::get_html_record_info($this->tab, $v['id']));
            if (isset($conf['actions_view']) && $conf['actions_view']) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'view',$v['id'])),'View');
            if (isset($conf['actions_edit']) && $conf['actions_edit']) if ($this->get_access('edit',$v)) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'edit',$v['id'])),'Edit');
            if (isset($conf['actions_delete']) && $conf['actions_delete']) if ($this->get_access('delete',$v)) $gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $v['id'])),'Delete');
            if (isset($conf['actions_history']) && $conf['actions_history']) {
                $r_info = Utils_RecordBrowserCommon::get_record_info($this->tab, $v['id']);
                if ($r_info['edited_on']===null) $gb_row->add_action('','This record was never edited',null,'history_inactive');
                else $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_edit_history', $v['id'])),'View edit history',null,'history');
            }
            if ($this->additional_actions_method!==null && is_callable($this->additional_actions_method))
                call_user_func($this->additional_actions_method, $v, $gb_row, $this);
        }
        $this->display_module($gb);
    }
	
	public function get_jump_to_id_button() {
		$link = Module::create_href_js(Utils_RecordBrowserCommon::get_record_href_array($this->tab, '__ID__'));
		$link = str_replace('__ID__', '\'+this.value+\'', $link);
		return '<a '.Utils_TooltipCommon::open_tag_attrs(__('Jump to record by ID')).' href="javascript:void(0);" onclick="jump_to_record_id(\''.$this->tab.'\')"><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','jump_to.png').'"></a><input type="text" id="jump_to_record_input" style="display:none;width:50px;" onkeypress="if(event.keyCode==13)'.$link.'">';
	}

    public function search_by_id_form($label) {
        $message = '';
        $form = $this->init_module('Libs/QuickForm');
        $theme = $this->init_module('Base/Theme');
        $form->addElement('text', 'record_id', $label);
        $form->addRule('record_id', __('Must be a number'), 'numeric');
        $form->addRule('record_id', __('Field required'), 'required');
        $ret = false;
		if ($form->isSubmitted())
            $ret = true;
        if ($form->validate()) {
            $id = $form->exportValue('record_id');
            if (!is_numeric($id)) trigger_error('Invalid id',E_USER_ERROR);
            $r = Utils_RecordBrowserCommon::get_record($this->tab,$id);
            if (!$r || empty($r)) $message = __('There is no such record').'<br>';
            else if (!$r[':active']) $message = __('This record was deleted from the system').'<br>';
            else {
                $x = ModuleManager::get_instance('/Base_Box|0');
                if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
                $x->push_main('Utils/RecordBrowser','view_entry',array('view', $id),array($this->tab));
                return;
            }
        }
        $form->assign_theme('form', $theme);
        $theme->assign('message', $message);
        $theme->display('search_by_id');
        return $ret;
    }
	
	public function manage_permissions() {
		$this->help('Permissions Editor','permissions');
		$this->init();
        $gb = $this->init_module('Utils/GenericBrowser','permissions_'.$this->tab, 'permissions_'.$this->tab);
		$gb->set_table_columns(array(
				array('name'=>__('Access type'), 'width'=>'100px'),
				array('name'=>__('Clearance required'), 'width'=>'30'),
				array('name'=>__('Applies to records'), 'width'=>'60'),
				array('name'=>__('Fields'), 'width'=>'100px')
		));
		$ret = DB::Execute('SELECT * FROM '.$this->tab.'_access AS acs ORDER BY action DESC');
		
		$tmp = DB::GetAll('SELECT * FROM '.$this->tab.'_access_clearance AS acs');
		$clearance = array();
		foreach ($tmp as $t) $clearance[$t['rule_id']][] = $t['clearance'];
		
		$tmp = DB::GetAll('SELECT * FROM '.$this->tab.'_access_fields AS acs');
		$fields = array();
		foreach ($tmp as $t) $fields[$t['rule_id']][] = $t['block_field'];
		
		$all_clearances = array_flip(Base_AclCommon::get_clearance(true));
		$all_fields = array();
		foreach ($this->table_rows as $v)
			$all_fields[$v['id']] = $v['name'];
		$actions = $this->get_permission_actions();
		$rules = array();
		while ($row = $ret->FetchRow()) {
			if (!isset($clearance[$row['id']])) $clearance[$row['id']] = array();
			if (!isset($fields[$row['id']])) $fields[$row['id']] = array();
			$action = $actions[$row['action']];
			$crits = Utils_RecordBrowserCommon::parse_access_crits($row['crits'], true);
			$crits = Utils_RecordBrowserCommon::crits_to_words($this->tab, $crits, false);
			$crits_text = '';
			foreach ($crits as $c) {
				switch ($c) {
					case 'and': $crits_text .= '<span class="joint">'.__('and').'</span><br>'; break;
					case 'or': $crits_text .= '<span class="joint">'.__('or').'</span> '; break;
					default: $crits_text .= $c.' ';
				}
			}
			foreach ($fields[$row['id']] as $k=>$v)
				if (isset($all_fields[$v]))
					$fields[$row['id']][$k] = $all_fields[$v];
				else
					unset($fields[$row['id']][$k]);
			foreach ($clearance[$row['id']] as $k=>$v)
				if (isset($all_clearances[$v])) $clearance[$row['id']][$k] = $all_clearances[$v];
				else unset($clearance[$row['id']][$k]);
			$c_all_fields = count($all_fields);
			$c_fields = count($fields[$row['id']]);

			$props = ($c_all_fields-$c_fields)/$c_all_fields;
			$color = dechex(255-68*$props).dechex(187+68*$props).'BB';
			$fields_value = ($c_all_fields-$c_fields).' / '.$c_all_fields;
			if ($props!=1) $fields_value = Utils_TooltipCommon::create($fields_value, '<b>'.__('Excluded fields').':</b><hr>'.implode('<br>',$fields[$row['id']]), false);
			$rules[$row['action']][$row['id']] = array(
				$action, 
				'<span class="Utils_RecordBrowser__permissions_crits">'.implode(' <span class="joint">'.__('and').'</span><br>',$clearance[$row['id']]).'</span>', 
				array('value'=>'<span class="Utils_RecordBrowser__permissions_crits">'.$crits_text.'</span>', 'overflow_box'=>false), 
				array('style'=>'background-color:#'.$color, 'value'=>$fields_value)
			);
		}
		foreach ($actions as $a=>$l)
			if (isset($rules[$a]))
				foreach ($rules[$a] as $id=>$vals) {
					$gb_row = $gb->get_new_row();
					$gb_row->add_data_array($vals);
					if (Base_AdminCommon::get_access('Utils_RecordBrowser', 'permissions')==2) {
						$gb_row->add_action($this->create_callback_href(array($this, 'edit_permissions_rule'), array($id)), 'edit', 'Edit');
						$gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this rule?'), array($this, 'delete_permissions_rule'), array($id)), 'delete', 'Delete');
				}
		}
		if (Base_AdminCommon::get_access('Utils_RecordBrowser', 'permissions')==2) 
			Base_ActionBarCommon::add('add',__('Add new rule'), $this->create_callback_href(array($this, 'edit_permissions_rule'), array(null)));
		Base_ThemeCommon::load_css('Utils_RecordBrowser', 'edit_permissions');
		$this->display_module($gb);
		eval_js('utils_recordbrowser__crits_initialized = false;');
	}
	public function delete_permissions_rule($id) {
		Utils_RecordBrowserCommon::delete_access($this->tab, $id);
		return false;
	}
	
	public function edit_permissions_rule($id = null) {
		if (Base_AdminCommon::get_access('Utils_RecordBrowser', 'permissions')!=2) return false;
        if ($this->is_back()) {
            return false;
		}
		load_js('modules/Utils/RecordBrowser/edit_permissions.js');
		$all_clearances = array(''=>'---')+array_flip(Base_AclCommon::get_clearance(true));
		$all_fields = array();
		$this->init();
		foreach ($this->table_rows as $k=>$v)
			$all_fields[$v['id']] = $k;
		$js = '';
		$operators = array(
			'='=>__('equal'), 
			'!'=>__('not equal'), 
			'>'=>'>',
			'>='=>'>=',
			'<'=>'<',
			'<='=>'<='
		);

		$form = $this->init_module('Libs_QuickForm');
		$theme = $this->init_module('Base_Theme');
		
		$counts = array(
			'clearance'=>5,
			'ands'=>5,
			'ors'=>10
		);
		
		$actions = $this->get_permission_actions();
		$form->addElement('select', 'action', __('Action'), $actions);
		
		$fields_permissions = $all_fields;

		foreach ($all_fields as $k=>$v) {
			if ($this->table_rows[$v]['type']=='calculated' || $this->table_rows[$v]['type']=='hidden') unset($all_fields[$k]);
			else $this->manage_permissions_set_field_values($k);
		}

		$all_fields = array(
			':Created_by'=>__('Created by'),
			':Created_on'=>__('Created on'),
			':Edited_on'=>__('Edited on')
		) + $all_fields;
		if ($this->tab=='contact' || $this->tab=='company')
			$all_fields = array('id'=>__('ID')) + $all_fields;
		
		$this->manage_permissions_set_field_values(':Created_by', array('USER_ID'=>__('User Login')));
		$this->manage_permissions_set_field_values(':Created_on', Utils_RecordBrowserCommon::$date_values);
		$this->manage_permissions_set_field_values(':Edited_on', Utils_RecordBrowserCommon::$date_values);
		if ($this->tab=='contact')
			$this->manage_permissions_set_field_values('id', array('USER'=>__('User Contact')));
		if ($this->tab=='company')
			$this->manage_permissions_set_field_values('id', array('USER_COMPANY'=>__('User Company')));
		
		for ($i=0; $i<$counts['clearance']; $i++)
			$form->addElement('select', 'clearance_'.$i, __('Clearance'), $all_clearances);
		$current_or = array();
		$current_and = 0;
		
		foreach ($all_fields as $k=>$v) {
			if (isset($this->table_rows[$v])) {
				$v = $this->table_rows[$v]['name'];
			}
			$all_fields[$k] = _V($v);
		}
		
		for ($i=0; $i<$counts['ands']; $i++) {
			$current_or[$i] = 0;
			for ($j=0; $j<$counts['ors']; $j++) {
				$form->addElement('select', 'crits_'.$i.'_'.$j.'_field', __('Crits'), array(''=>'---')+$all_fields, array('onchange'=>'utils_recordbrowser__update_field_values('.$i.', '.$j.');', 'id'=>'crits_'.$i.'_'.$j.'_field'));
				$form->addElement('select', 'crits_'.$i.'_'.$j.'_op', __('Operator'), array(''=>'---')+$operators);
				$form->addElement('select', 'crits_'.$i.'_'.$j.'_value', __('Value'), array(), array('id'=>'crits_'.$i.'_'.$j.'_value', 'onchange'=>'utils_recordbrowser__update_field_sub_values('.$i.', '.$j.');'));
				$form->addElement('select', 'crits_'.$i.'_'.$j.'_sub_value', __('Subrecord Value'), array(), array('id'=>'crits_'.$i.'_'.$j.'_sub_value', 'style'=>'display:none;'));
				$js .= 'utils_recordbrowser__update_field_values('.$i.', '.$j.');';
			}
		}
		$defaults = array();
		foreach ($fields_permissions as $k=>$v) {
			$defaults['field_'.$k] = 1;
			$form->addElement('checkbox', 'field_'.$k, _V($this->table_rows[$v]['name']));
		}
		$theme->assign('labels', array(
			'and' => '<span class="joint">'.__('and').'</span>',
			'or' => '<span class="joint">'.__('or').'</span>',
			'caption' => $id?__('Edit permission rule'):__('Add permission rule'),
			'clearance' => __('Clearance requried'),
			'fields' => __('Fields allowed'),
			'crits' => __('Criteria required'),
			'add_clearance' => __('Add clearance'),
			'add_or' => __('Add criteria (or)'),
			'add_and' => __('Add criteria (and)')
 		));
		$current_clearance = 0;
		$sub_values = array();
		if ($id!==null) {
			$row = DB::GetRow('SELECT * FROM '.$this->tab.'_access AS acs WHERE id=%d', array($id));
			
			$defaults['action'] = $row['action'];
			$crits = unserialize($row['crits']);
			$i = 0;
			$j = 0;
			$or = false;
			$first = true;
			foreach ($crits as $k=>$v) {
				$operator = '=';
				while (($k[0]<'a' || $k[0]>'z') && ($k[0]<'A' || $k[0]>'Z') && $k[0]!=':') {
					if ($k[0]=='!') $operator = '!';
					if ($k[0]=='(' && $or) $or = false;
					if ($k[0]=='|') $or = true;
					if ($k[0]=='<') $operator = '<';
					if ($k[0]=='>') $operator = '>';
					if ($k[0]=='~') $operator = DB::like();
					if ($k[1]=='=' && $operator!=DB::like()) {
						$operator .= '=';
						$k = substr($k, 2);
					} else $k = substr($k, 1);
				}
				if (!$first) {
					if ($or) $j++;
					else {
						$current_or[$i] += $j;
						$j = 0;
						$i++;
					}
				} else {
					$first = false;
				}
				$sub_value = null;
				if (!isset($r[$k]) && $k[strlen($k)-1]==']') {
					$sub_value = $v;
					list($k, $v) = explode('[', trim($k, ']'));
				}
				$defaults['crits_'.$i.'_'.$j.'_field'] = $k;
				$defaults['crits_'.$i.'_'.$j.'_op'] = $operator;
				$js .= '$("crits_'.$i.'_'.$j.'_value").value = "'.$v.'";';
				if ($sub_value!==null) $sub_values['crits_'.$i.'_'.$j.'_sub_value'] = $sub_value;
			}
			$current_or[$i] += $j;
			$current_and += $i;
			
			$i = 0;
			$tmp = DB::GetAll('SELECT * FROM '.$this->tab.'_access_clearance AS acs WHERE rule_id=%d', array($id));
			foreach ($tmp as $t) {
				$defaults['clearance_'.$i] = $t['clearance'];
				$i++;
			}
			$current_clearance += $i-1;
			
			$tmp = DB::GetAll('SELECT * FROM '.$this->tab.'_access_fields AS acs WHERE rule_id=%d', array($id));
			foreach ($tmp as $t) {
				unset($defaults['field_'.$t['block_field']]);
			}
		}
		for ($i=0; $i<$counts['ands']; $i++)
			for ($j=0; $j<$counts['ors']; $j++)
				$js .= 'utils_recordbrowser__update_field_sub_values('.$i.', '.$j.');';
		foreach ($sub_values as $k=>$v)
			$js .= '$("'.$k.'").value = "'.$v.'";';

		$form->setDefaults($defaults);
		
		if ($form->validate()) {
			$vals = $form->exportValues();
			$action = $vals['action'];

			$clearance = array();
			for ($i=0; $i<$counts['clearance']; $i++)
				if ($vals['clearance_'.$i]) $clearance[] = $vals['clearance_'.$i];
			
			$crits = array();
			for ($i=0; $i<$counts['ands']; $i++) {
				$or = '(';
				for ($j=0; $j<$counts['ors']; $j++) {
					if ($vals['crits_'.$i.'_'.$j.'_field'] && $vals['crits_'.$i.'_'.$j.'_op']) {
						if (!isset($operators[$vals['crits_'.$i.'_'.$j.'_op']])) trigger_error('Fatal error',E_USER_ERROR);
						if (!isset($all_fields[$vals['crits_'.$i.'_'.$j.'_field']])) trigger_error('Fatal error',E_USER_ERROR);
						$op = $vals['crits_'.$i.'_'.$j.'_op'];
						if ($op=='=') $op = '';
						if (isset($vals['crits_'.$i.'_'.$j.'_sub_value'])) {
							$vals['crits_'.$i.'_'.$j.'_field'] = $vals['crits_'.$i.'_'.$j.'_field'].'['.$vals['crits_'.$i.'_'.$j.'_value'].']';
							$vals['crits_'.$i.'_'.$j.'_value'] = $vals['crits_'.$i.'_'.$j.'_sub_value'];
						}
						$next = array($or.$op.$vals['crits_'.$i.'_'.$j.'_field'] => $vals['crits_'.$i.'_'.$j.'_value']);
						$crits = Utils_RecordBrowserCommon::merge_crits($crits, $next);
					}
					$or = '|';
				}
			}

			$blocked_fields = array();
			foreach ($fields_permissions as $k=>$v) {
				if (isset($vals['field_'.$k])) continue;
				$blocked_fields[] = $k;
			}
			
			if ($id===null)
				Utils_RecordBrowserCommon::add_access($this->tab, $action, $clearance, $crits, $blocked_fields);
			else
				Utils_RecordBrowserCommon::update_access($this->tab, $id, $action, $clearance, $crits, $blocked_fields);
			return false;
		}
		
		eval_js($js);

		eval_js('utils_recordbrowser__init_clearance('.$current_clearance.', '.$counts['clearance'].')');
		eval_js('utils_recordbrowser__init_crits_and('.$current_and.', '.$counts['ands'].')');
		for ($i=0; $i<$counts['ands']; $i++)
				eval_js('utils_recordbrowser__init_crits_or('.$i.', '.$current_or[$i].', '.$counts['ors'].')');
		eval_js('utils_recordbrowser__crits_initialized = true;');
		
		$form->assign_theme('form', $theme);
		$theme->assign('fields', $fields_permissions);
		$theme->assign('counts', $counts);
		
		$theme->display('edit_permissions');
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		Base_ActionBarCommon::add('delete', __('Cancel'), $this->create_back_href());
		return true;
	}
	
	private function get_permission_actions() {
		return array(
			'view'=>__('View'),
			'edit'=>__('Edit'),
			'add'=>__('Add'),
			'delete'=>__('Delete'),
			'print'=>__('Print'),
			'export'=>__('Export')
		);
	}
	
	private function manage_permissions_set_field_values($field, $arr=null) {
		if ($arr===null) {
			$arr = $this->permissions_get_field_values($field, true);
		}
		foreach ($arr as $k=>$v)
			$arr[$k] = '"'.$k.'":"'.$v.'"';
		eval_js('utils_recordbrowser__field_values["'.$field.'"] = {'.implode(',',$arr).'};');
	}
	
	private function permissions_get_field_values($field, $in_depth=true) {
		static $all_fields = array();
		if (!isset($all_fields[$this->tab]))
			foreach ($this->table_rows as $k=>$v)
				$all_fields[$this->tab][$v['id']] = $k;
		$args = $this->table_rows[$all_fields[$this->tab][$field]];
		$arr = array(''=>'['.__('Empty').']');
		switch (true) {
			case $args['commondata']:
				$array_id = is_array($args['param']) ? $args['param']['array_id'] : $args['ref_table'];
				if (strpos($array_id, '::')===false) 
					$arr = $arr + Utils_CommonDataCommon::get_translated_array($array_id, is_array($args['param'])?$args['param']['order_by_key']:false);
				break;
			case $this->tab=='contact' && $field=='login' ||
				 $this->tab=='rc_accounts' && $field=='epesi_user': // just a quickfix, better solution will be needed
				$arr = $arr + array('USER_ID'=>__('User Login'));
				break;
			case $args['type']=='date' || $args['type']=='timestamp':
				$arr = $arr + Utils_RecordBrowserCommon::$date_values;
				break;
			case ($args['type']=='multiselect' || $args['type']=='select') && (!isset($args['ref_table']) || !$args['ref_table']):
				$arr = $arr + array('USER'=>__('User Contact'));
				$arr = $arr + array('USER_COMPANY'=>__('User Company'));
				break;
			case $args['type']=='checkbox':
				$arr = array('1'=>__('Yes'),'0'=>__('No'));
				break;
			case ($args['type']=='select' || $args['type']=='multiselect') && isset($args['ref_table']):
				if ($args['ref_table']=='contact') $arr = $arr + array('USER'=>__('User Contact'));
				if ($args['ref_table']=='company') $arr = $arr + array('USER_COMPANY'=>__('User Company'));
				if (!$in_depth) continue;

				$last_tab = $this->tab;
				$this->tab = $args['ref_table'];
				$this->init();
				if (!isset($all_fields[$this->tab]))
					foreach ($this->table_rows as $k=>$v)
						$all_fields[$this->tab][$v['id']] = $k;
						

				foreach ($all_fields[$this->tab] as $k=>$v) {
					if ($this->table_rows[$v]['type']=='calculated' || $this->table_rows[$v]['type']=='hidden') unset($all_fields[$this->tab][$k]);
					else {
						$arr2 = $this->permissions_get_field_values($k, false, $this->tab);
						foreach ($arr2 as $k2=>$v2)
							$arr2[$k2] = '"'.$k2.'":"'.$v2.'"';
						eval_js('utils_recordbrowser__field_sub_values["'.$field.'__'.$k.'"] = {'.implode(',',$arr2).'};');
					}
				}
				foreach ($all_fields[$this->tab] as $k=>$v) {
					$arr[$k] = __(' records with %s set to ', array(_V($v)));
				}

				$this->tab = $last_tab;
				$this->init();
				break;
		}
		return $arr;
	}
}
?>
