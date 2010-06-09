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
    private $action = 'Browsing';
    private $crits = array();
    private $access_callback;
    private $noneditable_fields = array();
    private $add_button = null;
    private $more_add_button_stuff = '';
    private $changed_view = false;
    private $is_on_main_page = false;
    private $multiple_defaults = false;
    private $add_in_table = false;
    private $custom_filters = array();
    private $filter_field;
    private $default_order = array();
    private $cut = array();
    private $more_table_properties = array();
    private $fullscreen_table = false;
    private $amount_of_records = 0;
    private $switch_to_addon = null;
    private $additional_caption = '';
    private $enable_export = false;
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
    private $disabled = array('search'=>false, 'browse_mode'=>false, 'watchdog'=>false, 'quickjump'=>false, 'filters'=>false, 'headline'=>false, 'actions'=>false, 'fav'=>false);
    private $force_order;
    private $clipboard_pattern = false;
    private $show_add_in_table = false;
    private $data_gb = null;
    public $view_fields_permission;
    public $form = null;
    public $tab;
    public $grid = null;

    public function enable_grid($arg) {
        $this->grid = $arg;
    }

    public function set_filter_crits($field, $crits) {
        $this->filter_crits[$field] = $crits;
    }

    public function switch_to_addon($arg) {
        $this->switch_to_addon = $arg;
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

    public function set_additional_actions_method($callback) {
        $this->additional_actions_method = $callback;
    }

    public function set_cut_lengths($ar) {
        $this->cut = $ar;
    }

    public function set_table_column_order($arg) {
        $this->col_order = $arg;
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
        self::$rb_obj = $this;
        $this->tab = & $this->get_module_variable('tab', $tab);
        if ($this->tab!==null) Utils_RecordBrowserCommon::check_table_name($this->tab);
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
            print($this->t('You are not authorised to browse this data.'));
            return;
        }
        if ($this->watchdog) Utils_WatchdogCommon::add_actionbar_change_subscription_button($this->tab);
        $this->is_on_main_page = true;
        if ($this->get_access('add')!==false && $this->add_button!==false) {
            if (!$this->multiple_defaults) {
                if ($this->add_button===null) {
                    Base_ActionBarCommon::add('add','New', $this->create_callback_href(array($this,'navigate'),array('view_entry', 'add', null, $this->custom_defaults)));
                    Utils_ShortcutCommon::add(array('Ctrl','N'), 'function(){'.$this->create_callback_href_js(array($this,'navigate'),array('view_entry', 'add', null, $this->custom_defaults)).'}');
                } elseif($this->add_button!=='') {
                    Base_ActionBarCommon::add('add','New', $this->add_button);
                }
            } else {
                eval_js_once('actionbar_rb_new_record_deactivate = function(){leightbox_deactivate(\'actionbar_rb_new_record\');}');
                $th = $this->init_module('Base/Theme');
                $cds = array();
                foreach ($this->custom_defaults as $k=>$v) {
                    $cds[] = array( 'label'=>$k,
                                    'open'=>'<a OnClick="actionbar_rb_new_record_deactivate();'.$this->create_callback_href_js(array($this,'navigate'),array('view_entry', 'add', null, $v['defaults'])).'">',
                                    'icon'=>$v['icon'],
                                    'close'=>'</a>'
                                    );
                }
                $th->assign('custom_defaults', $cds);
                ob_start();
                $th->display('new_record_leightbox');
                $panel = ob_get_clean();
                Libs_LeightboxCommon::display('actionbar_rb_new_record',$panel,$this->t('New record'));
                Base_ActionBarCommon::add('add','New', Libs_LeightboxCommon::get_open_href('actionbar_rb_new_record'));
            }
        }

        if (!$this->disabled['filters']) $filters = $this->show_filters($filters_set);
        else $filters = '';

        if (isset($this->filter_field)) {
            CRM_FiltersCommon::add_action_bar_icon();
			$ff = trim(CRM_FiltersCommon::get(),'()');
			if ($ff) {
				$ff = explode(',',$ff);
				if ($ff[0]!='') $ff[] = '';
				$op = '(';
				if (!is_array($this->filter_field)) $this->filter_field = array($this->filter_field);
				foreach ($this->filter_field as $f) {
					$this->crits[$op.$f] = $ff;
					$op = '|';
				}
			}
        }
        $this->crits = $this->crits+$crits;

        $theme = $this->init_module('Base/Theme');
        $theme->assign('filters', $filters);
        $opts = array('all'=>$this->t('All'));
        if ($this->recent>0) $opts['recent'] = $this->t('Recent');
        if ($this->favorites) $opts['favorites'] = $this->t('Favorites');
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
        if (!$this->disabled['headline']) $theme->assign('caption', $this->t($this->caption).($this->additional_caption?' - '.$this->additional_caption:''));
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
        if (empty($filters_all)) {
            $this->crits = array();
            return '';
        } // TODO: move it
        $form = $this->init_module('Libs/QuickForm', null, $this->tab.'filters');
        $this->data_gb = $this->init_module('Utils/GenericBrowser', null, $this->tab);

//        $form_sub = $form->get_submit_form_js_by_name(array($form->get_name(), $this->data_gb->form_s->get_name()),true,null)."return false;";
//        $this->data_gb->form_s->updateAttributes(array('onsubmit'=>$form_sub));
//	      $form->updateAttributes(array('onsubmit'=>$form_sub));

        $filters = array();
        $text_filters = array();
        foreach ($filters_all as $filter) {
            $filter_id = preg_replace('/[^a-z0-9]/','_',strtolower($filter));
            $field_id = 'filter__'.$filter_id;
            if (isset($this->custom_filters[$filter_id])) {
                $f = $this->custom_filters[$filter_id];
                if (!isset($f['label'])) $f['label'] = $filter;
                if (!isset($f['args'])) $f['args'] = null;
                if (!isset($f['args_2'])) $f['args_2'] = null;
                if (!isset($f['args_3'])) $f['args_3'] = null;
                $form->addElement($f['type'], $field_id, $f['label'], $f['args'], $f['args_2'], $f['args_3']);
                $filters[] = $filter_id;
                continue;
            }
            $arr = array();
            if ($this->table_rows[$filter]['type']=='timestamp') {
				$form->addElement('datepicker', $field_id.'__from', $this->t($filter).' ('.$this->t('from').')', array('label'=>false));
				$form->addElement('datepicker', $field_id.'__to', $this->t($filter).' ('.$this->t('to').')', array('label'=>false));
				$filters[] = $filter_id.'__from';
				$filters[] = $filter_id.'__to';
				continue;
            }
			if ($this->table_rows[$filter]['type']=='checkbox') {
                $arr = array(''=>$this->ht('No'), 1=>$this->ht('Yes'));
            } else {
                if ($this->table_rows[$filter]['type'] == 'commondata') {
                    $arr = Utils_CommonDataCommon::get_translated_array($this->table_rows[$filter]['param']['array_id'], $this->table_rows[$filter]['param']['order_by_key']);
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
                    }
                }
            }
            $arr = array('__NULL__'=>'---')+$arr;
            $form->addElement('select', $field_id, $this->t($filter), $arr);
            $filters[] = $filter_id;
        }
        $form->addElement('submit', 'submit', 'Show');
        $def_filt = $this->get_module_variable('def_filter', array());

        $this->crits = array();

        $form->setDefaults($def_filt);

        $external_filters = array();

        $dont_hide = $this->get_module_variable('dont_hide', false);
        if (!isset($_REQUEST['__location']) || !$_REQUEST['__location']) $dont_hide = false;

        $ret = DB::Execute('SELECT * FROM recordbrowser_browse_mode_definitions WHERE tab=%s', array($this->tab));
        while ($row = $ret->FetchRow()) {
            $m = $this->init_module($row['module']);
            $next_dont_hide = false;
            $this->display_module($m, array(& $form, & $external_filters, & $vals, & $this->crits, & $next_dont_hide), $row['func']);
            $dont_hide |= $next_dont_hide;
        }
        $this->set_module_variable('dont_hide', $dont_hide);

        $vals = $form->exportValues();

        foreach ($filters_all as $filter) {
            if (in_array(strtolower($filter), $external_filters)) continue;
            $filter_id = preg_replace('/[^a-z0-9]/','_',strtolower($filter));
            $field_id = 'filter__'.$filter_id;
            if (isset($this->custom_filters[$filter_id])) {
                if (!isset($vals['filter__'.$filter_id])) $vals['filter__'.$filter_id]='__NULL__';
                if (isset($this->custom_filters[$filter_id]['trans'][$vals['filter__'.$filter_id]])) {
                    foreach($this->custom_filters[$filter_id]['trans'][$vals['filter__'.$filter_id]] as $k=>$v)
                        $this->crits[$k] = $v;
                } elseif (isset($this->custom_filters[$filter_id]['trans_callback'])) {
                    $new_crits = call_user_func($this->custom_filters[$filter_id]['trans_callback'], $vals['filter__'.$filter_id], $filter_id);
                    $this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $new_crits);
                }
            } else {
				if ($this->table_rows[$filter]['type']=='timestamp') {
					if ($vals[$field_id.'__from'])
						$this->crits['>='.$filter_id] = $vals[$field_id.'__from'].' 00:00:00';
					if ($vals[$field_id.'__to'])
						$this->crits['<='.$filter_id] = $vals[$field_id.'__to'].' 23:59:59';
					continue;
				}
                if (!isset($text_filters[$filter_id])) {
                    if (!isset($vals['filter__'.$filter_id])) $vals['filter__'.$filter_id]='__NULL__';
                    if ($vals['filter__'.$filter_id]!=='__NULL__') $this->crits[$filter_id] = $vals['filter__'.$filter_id];
                } else {
                    if (!isset($vals['filter__'.$filter_id])) $vals['filter__'.$filter_id]='';
                    if ($vals['filter__'.$filter_id]!=='') {
                        $args = $this->table_rows[$filter];
                        $str = explode(';', $args['param']);
                        $ref = explode('::', $str[0]);
                        if ($ref[0]!='' && isset($ref[1])) $this->crits['_":Ref:'.$args['id']] = DB::Concat(DB::qstr($vals['filter__'.$filter_id]),DB::qstr('%'));;
                        if ($args['type']=='commondata' || $ref[0]=='__COMMON__') {
                            if (!isset($ref[1]) || $ref[0]=='__COMMON__') $this->crits['_":RefCD:'.$args['id']] = DB::Concat(DB::qstr($vals['filter__'.$filter_id]),DB::qstr('%'));;
                        }
                    }
                }
            }
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
        $theme->assign('show_filters', $this->t('Show filters'));
        $theme->assign('hide_filters', $this->t('Hide filters'));
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
    public function show_data($crits = array(), $cols = array(), $order = array(), $admin = false, $special = false) {
        if (isset($_SESSION['client']['recordbrowser']['admin_access'])) Utils_RecordBrowserCommon::$admin_access = true;
        if ($this->check_for_jump()) return;
        Utils_RecordBrowserCommon::$cols_order = $this->col_order;
        if ($this->get_access('browse')===false) {
            print($this->t('You are not authorised to browse this data.'));
            return;
        }
        $this->init();
        $this->action = 'Browse';
        if (!Base_AclCommon::i_am_admin() && $admin) {
            print($this->t('You don\'t have permission to access this data.'));
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
            $table_columns = array(array('name'=>$this->t('Select'), 'width'=>1));
        } else {
            $table_columns = array();
            if (!$admin && $this->favorites) {
                $fav = array('name'=>$this->t('Fav'), 'width'=>1);
                if (!isset($this->force_order)) $fav['order'] = ':Fav';
                $table_columns[] = $fav;
            }
            if (!$admin && $this->watchdog)
                $table_columns[] = array('name'=>$this->t('Sub'), 'width'=>1);
        }
        if (!$this->disabled['quickjump']) $quickjump = DB::GetOne('SELECT quickjump FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
        else $quickjump = '';

        $hash = array();
        $access = $this->get_access('browse');
        $query_cols = array();
        foreach($this->table_rows as $field => $args) {
            $hash[$args['id']] = $field;
            if ($field === 'id') continue;
            if ((!$args['visible'] && (!isset($cols[$args['id']]) || $cols[$args['id']] === false)) || !$access[$args['id']]) continue;
            if (isset($cols[$args['id']]) && $cols[$args['id']] === false) continue;
            $query_cols[] = $args['id'];
            $arr = array('name'=>$args['name']);
            if (!isset($this->force_order) && $this->browse_mode!='recent' && $args['type']!=='multiselect' && ($args['type']!=='calculated' || $args['param']!='') && $args['type']!=='hidden') $arr['order'] = $field;
            if ($args['type']=='checkbox' || (($args['type']=='date' || $args['type']=='timestamp' || $args['type']=='time') && !$this->add_in_table) || $args['type']=='commondata') {
                $arr['wrapmode'] = 'nowrap';
                $arr['width'] = 1;
            }
            if (isset($this->more_table_properties[$args['id']])) {
                foreach (array('name','wrapmode','width') as $v)
                    if (isset($this->more_table_properties[$args['id']][$v])) $arr[$v] = $this->more_table_properties[$args['id']][$v];
            }
            $arr['name'] = $this->t($arr['name']);
            if (is_array($args['param']))
                $str = explode(';', $args['param']['array_id']);
            else
                $str = explode(';', $args['param']);
            $ref = explode('::', $str[0]);
            $each = array();
            if ($quickjump!=='' && $args['name']===$quickjump) $each[] = 'quickjump';
            if (!$this->disabled['search']) $each[] = 'search';
            foreach ($each as $e) {
                if ($args['type']=='text' || $args['type']=='currency' || ($args['type']=='calculated' && $args['param']!='')) $arr[$e] = $args['id'];
                if ($ref[0]!='' && isset($ref[1])) $arr[$e] = '__Ref__'.$args['id'];
                if ($args['type']=='commondata' || $ref[0]=='__COMMON__') {
                    if (!isset($ref[1]) || $ref[0]=='__COMMON__') $arr[$e] = '__RefCD__'.$args['id'];
                    else unset($arr[$e]);
                }
            }
            if (isset($arr['quickjump'])) $arr['quickjump'] = '"~'.$arr['quickjump'];
            /*
            if ($quickjump!=='' && $args['name']===$quickjump) $arr['quickjump'] = '"~'.$args['id'];
            if (!$this->disabled['search']) {
                if ($args['type']=='text' || $args['type']=='currency' || ($args['type']=='calculated' && $args['param']!='')) $arr['search'] = $args['id'];//preg_replace('/[^a-z0-9]/i','_',$field);
                if ($ref[0]!='' && isset($ref[1])) $arr['search'] = '__Ref__'.$args['id'];//preg_replace('/[^a-z0-9]/i','_',$field);
                if ($args['type']=='commondata' || $ref[0]=='__COMMON__') {
                    if (!isset($ref[1]) || $ref[0]=='__COMMON__') $arr['search'] = '__RefCD__'.$args['id'];//preg_replace('/[^a-z0-9]/i','_',$field);
                    else unset($arr['search']);
                }
            }
            */
            $table_columns[] = $arr;
        }
        $clean_order = array();
        foreach ($order as $k => $v) {
            if (isset($this->more_table_properties[$k]) && isset($this->more_table_properties[$k]['name'])) $key = $this->more_table_properties[$k]['name'];
            elseif (isset($hash[$k])) $key = $hash[$k];
            else $key = $k;
            $clean_order[$this->t($key)] = $v;
        }
//      if ($this->browse_mode == 'recent')
//          $table_columns[] = array('name'=>$this->t('Visited on'), 'wrapmode'=>'nowrap', 'width'=>1);

        $gb->set_table_columns( $table_columns );

        if ($this->browse_mode != 'recent')
            $gb->set_default_order($clean_order, $this->changed_view);

        if (!$special && $this->get_access('add')!==false) {
            $custom_label = '';
            if ($this->add_button!==null) $label = $this->add_button;
            elseif (!$this->multiple_defaults) $label = $this->create_callback_href(array($this, 'navigate'), array('view_entry', 'add', null, $this->custom_defaults));
            else $label = false;
            if ($label!==false && $label!=='') $custom_label = '<a '.$label.' '.Utils_TooltipCommon::open_tag_attrs($this->t('Add new record')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Base/ActionBar','icons/add-small.png').'" /></a>';
            if ($this->more_add_button_stuff) {
                if ($custom_label) $custom_label = '<table><tr><td>'.$custom_label.'</td><td>'.$this->more_add_button_stuff.'</td></tr></table>';
                else $custom_label = $this->more_add_button_stuff;
            }
            $gb->set_custom_label($custom_label);
        }
        $search = $gb->get_search_query(true);
        $search_res = array();
        if ($gb->is_adv_search_on()) {
            foreach ($search as $k=>$v) {
                $k = str_replace('__',':',$k);
                $type = explode(':',$k);
                if ($k[0]=='"') {
                    $search_res['~_'.$k] = $v;
                    continue;
                }
                if (isset($type[1]) && $type[1]=='RefCD') {
                    $search_res[$k] = $v;
                    continue;
                }
                if (is_array($v)) $v = $v[0];
                $v = explode(' ', $v);

    //          $r = array();
    //          foreach ($v as $w)
    //              $r[] = DB::Concat(DB::qstr('%'),DB::qstr($w),DB::qstr('%'));
    //          $search_res['~"'.$k] = $r;
                foreach ($v as $w)
                    $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, array('~"'.$k =>DB::Concat(DB::qstr('%'),DB::qstr($w),DB::qstr('%'))));
            }
        } else {
            $val = reset($search);
            $isearch = $gb->get_module_variable('search');
            if (empty($isearch)) $val = null;
            $val2 = explode(' ', $val[0]);
            $leftovers = array();
            foreach ($val2 as $vv) {
                foreach ($search as $k=>$v) {
                    $k = str_replace('__',':',$k);
                    if ($v!=$val) {
                        $leftovers[$k] = $v;
                        continue;
                    }
                    $type = explode(':',$k);
                    if ($k[0]=='"') {
                        $search_res['~_'.$k] = $vv;
                        continue;
                    }
                    if (isset($type[1]) && $type[1]=='RefCD') {
                        $search_res[$k] = $vv;
                        continue;
                    }
                    $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, array('~"'.$k =>DB::Concat(DB::qstr('%'),DB::qstr($vv),DB::qstr('%'))));
                }
            }
            $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, $leftovers);
        }

        $order = $gb->get_order();
        $crits = array_merge($crits, $search_res);
        if ($this->browse_mode == 'favorites')
            $crits[':Fav'] = true;
        if ($this->browse_mode == 'recent') {
            $crits[':Recent'] = true;
            $order = array(':Visited_on'=>'DESC');
        }

        if ($admin) {
            $order = array(':Edited_on'=>'DESC');
            $form = $this->init_module('Libs/QuickForm', null, $this->tab.'_admin_filter');
            $form->addElement('select', 'show_records', 'Show records', array(0=>'all',1=>'active',2=>'deactivated'));
            $form->addElement('submit', 'submit', 'Show');
            $f = $this->get_module_variable('admin_filter', 0);
            $form->setDefaults(array('show_records'=>$f));
            self::$admin_filter = $form->exportValue('show_records');
            $this->set_module_variable('admin_filter', self::$admin_filter);
            if (self::$admin_filter==0) self::$admin_filter = '';
            if (self::$admin_filter==1) self::$admin_filter = ' AND active=1';
            if (self::$admin_filter==2) self::$admin_filter = ' AND active=0';
            $form->display();
        }
        if (isset($this->force_order)) $order = $this->force_order;

        $this->amount_of_records = Utils_RecordBrowserCommon::get_records_count($this->tab, $crits, $admin);
        $limit = $gb->get_limit($this->amount_of_records);
        $records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, array(), $order, $limit, $admin);

        if ((Base_AclCommon::i_am_admin() && $this->fullscreen_table) || $this->enable_export)
            Base_ActionBarCommon::add('save','Export', 'href="modules/Utils/RecordBrowser/csv_export.php?'.http_build_query(array('tab'=>$this->tab, 'admin'=>$admin, 'cid'=>CID, 'path'=>$this->get_path())).'"');

        $this->set_module_variable('crits_stuff',$crits?$crits:array());
        $this->set_module_variable('order_stuff',$order?$order:array());

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

        if (!$admin && $this->favorites) {
            $favs = array();
            $ret = DB::Execute('SELECT '.$this->tab.'_id FROM '.$this->tab.'_favorite WHERE user_id=%d', array(Acl::get_user()));
            while ($row=$ret->FetchRow()) $favs[$row[$this->tab.'_id']] = true;
            $star_on = Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_fav.png');
            $star_off = Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_nofav.png');
        }
        self::$access_override['tab'] = $this->tab;
        if (isset($limit)) $i = $limit['offset'];

        $grid_enabled = $this->grid===null?Base_User_SettingsCommon::get('Utils/RecordBrowser','grid'):$this->grid;
        if ($grid_enabled) load_js('modules/Utils/RecordBrowser/grid.js');

        $this->view_fields_permission = $this->get_access('add', $this->custom_defaults);
        if (!$special && $this->add_in_table && $this->view_fields_permission) {
            $form = $this->init_module('Libs/QuickForm',null, 'add_in_table__'.$this->tab);
            $form_name = $form->get_name();
        } else $form_name = '';
        foreach ($records as $row) {
            $row = Utils_RecordBrowserCommon::format_long_text($this->tab,$row);
            if ($this->browse_mode!='recent' && isset($limit)) {
                self::$browsed_records['records'][$row['id']] = $i;
                $i++;
            }
            self::$access_override['id'] = $row['id'];
            $gb_row = $gb->get_new_row();
            if (!$admin && $this->favorites) {
                $isfav = isset($favs[$row['id']]);
                $row_data= array('<a '.Utils_TooltipCommon::open_tag_attrs(($isfav?$this->t('This item is on your favourites list<br>Click to remove it from your favorites'):$this->t('Click to add this item to favorites'))).' '.$this->create_callback_href(array($this,($isfav?'remove_from_favs':'add_to_favs')), array($row['id'])).'><img style="width: 14px; height: 14px; vertical-align: middle;" border="0" src="'.($isfav==false?$star_off:$star_on).'" /></a>');
            } else $row_data= array();
            if (!$admin && $this->watchdog)
                $row_data[] = Utils_WatchdogCommon::get_change_subscription_icon($this->tab,$row['id']);
            if ($special) {
                $element = $this->get_module_variable('element');
                $format = $this->get_module_variable('format_func');
//              $row_data= array('<a href="javascript:rpicker_addto(\''.$element.'\','.$row['id'].',\''.Base_ThemeCommon::get_template_file('images/active_on.png').'\',\''.Base_ThemeCommon::get_template_file('images/active_off2.png').'\',\''.(is_callable($func)?strip_tags(call_user_func($func, $row, true)):'').'\');"><img border="0" name="leightbox_rpicker_'.$element.'_'.$row['id'].'" /></a>');
                $row_data = array('<input type="checkbox" id="leightbox_rpicker_'.$element.'_'.$row['id'].'" formated_name="'.(is_callable($format)?strip_tags(call_user_func($format, $row, true)):'').'" />');
                $rpicker_ind[] = $row['id'];
            }
            foreach($query_cols as $argsid) {
                if (!$access[$argsid]) continue;
                $field = $hash[$argsid];
                $args = $this->table_rows[$field];
                $value = $this->get_val($field, $row, $special, $args);
                if (isset($this->cut[$args['id']])) {
                    $value = Utils_RecordBrowserCommon::cut_string($value,$this->cut[$args['id']]);
                }
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
                    if (!isset($value['attrs'])) $value['attrs'] = '';
                    $value['attrs'] = $attrs;
                } else {
                    $value = array(
                        'value'=>$table.$value.$ed_icon,
                        'attrs'=>$attrs
                    );
                }
                $row_data[] = $value;
            }
//          if ($this->browse_mode == 'recent')
//              $row_data[] = $row['visited_on'];

            $gb_row->add_data_array($row_data);
            if ($this->disabled['actions']!==true) {
                if ($this->disabled['actions']===false) $da = array();
                else $da = array_flip($this->disabled['actions']);
                if (!$special) {
                    if (!isset($da['view'])) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'view', $row['id'])),'View');
                    if (!isset($da['edit']) && $this->get_access('edit',$row)) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'edit',$row['id'])),'Edit');
                    if ($admin) {
                        if (!$row[':active']) $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],true)),'Activate', null, 'active-off');
                        else $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],false)),'Deactivate', null, 'active-on');
                        $info = Utils_RecordBrowserCommon::get_record_info($this->tab, $row['id']);
                        if ($info['edited_on']===null) $gb_row->add_action('','This record was never edited',null,'history_inactive');
                        else $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_edit_history', $row['id'])),'View edit history',null,'history');
                    } else
                    if (!isset($da['delete']) && $this->get_access('delete',$row)) $gb_row->add_action($this->create_confirm_callback_href($this->t('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $row['id'])),'Delete');
                }
                if (!isset($da['info'])) $gb_row->add_info(($this->browse_mode=='recent'?'<b>'.$this->t('Visited on: %s', array($row['visited_on'])).'</b><br>':'').Utils_RecordBrowserCommon::get_html_record_info($this->tab, isset($info)?$info:$row['id']));
                if ($this->additional_actions_method!==null && is_callable($this->additional_actions_method))
                    call_user_func($this->additional_actions_method, $row, $gb_row, $this);
            }
        }
        if (!$special && $this->add_in_table && $this->view_fields_permission) {

            $visible_cols = array();
            foreach($this->table_rows as $field => $args){
                if ((!$args['visible'] && (!isset($cols[$args['id']]) || $cols[$args['id']] === false)) || !$access[$args['id']]) continue;
                if (isset($cols[$args['id']]) && $cols[$args['id']] === false) continue;
                $visible_cols[$args['id']] = true;
            }

            $dpm = DB::GetOne('SELECT data_process_method FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
            if ($dpm!=='') {
                $method = explode('::',$dpm);
                if (is_callable($method)) $this->custom_defaults = call_user_func($method, $this->custom_defaults, 'adding');
            }
            $this->record = $this->custom_defaults;

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
            $form->addElement('submit', 'submit_qanr', $this->t('Submit'), array('style'=>'width:auto;'));
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
                    $row_data[] = $data[$k]['error'].$data[$k]['html'];
                    if ($first) eval_js('focus_on_field = "'.$k.'";');
                    $first = false;
                } else $row_data[] = '&nbsp;';
            }

//          if ($this->browse_mode == 'recent')
//              $row_data[] = '&nbsp;';

            $gb_row = $gb->get_new_row();
            $gb_row->add_action('',$data['submit_qanr']['html'],'');
            $gb_row->set_attrs('id="add_in_table_row" style="display:'.($this->show_add_in_table?'':'none').';"');
            $gb_row->add_data_array($row_data);
        }
        if ($special) {
            $this->set_module_variable('rpicker_ind',$rpicker_ind);
            $ret = $this->get_html_of_module($gb);
            Base_User_SettingsCommon::save('Utils/RecordBrowser/RecordPicker','per_page',$gb->get_module_variable('per_page'));
            Base_User_SettingsCommon::save('Utils/GenericBrowser','per_page',$gb_per_page);
            return $ret;
        } else $this->display_module($gb);
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function delete_record($id) {
        Utils_RecordBrowserCommon::delete_record($this->tab, $id);
        return $this->back();
    }
    public function clone_record($id) {
        if (self::$clone_result!==null) {
            if (is_numeric(self::$clone_result)) {
                $dpm = DB::GetOne('SELECT data_process_method FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
                if ($dpm!=='') {
                    $method = explode('::',$dpm);
                    if (is_callable($method)) call_user_func($method, array('original'=>$id, 'clone'=>self::$clone_result), 'cloned');
                }
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
        $this->navigate('view_entry', 'add', null, $record);
        return true;
    }
    public function view_entry_with_REQUEST($mode='view', $id = null, $defaults = array(), $show_actions=true, $request=array()) {
        foreach ($request as $k=>$v)
            $_REQUEST[$k] = $v;
        return $this->view_entry($mode, $id, $defaults, $show_actions);
    }
    public function view_entry($mode='view', $id = null, $defaults = array(), $show_actions=true) {
        if (isset($_SESSION['client']['recordbrowser']['admin_access'])) Utils_RecordBrowserCommon::$admin_access = true;
        self::$mode = $mode;
        if ($this->navigation_executed) {
            $this->navigation_executed = false;
            return true;
        }
        $theme = $this->init_module('Base/Theme');
        if ($this->isset_module_variable('id')) {
            $id = $this->get_module_variable('id');
            $this->unset_module_variable('id');
        }
        if($mode == 'add' || $mode == 'edit') {
            $theme -> assign('click2fill', '<div id="c2fBox"></div>');
            load_js('modules/Utils/RecordBrowser/click2fill.js');
            eval_js('initc2f()');
            Base_ActionBarCommon::add('clone', 'Click 2 Fill', 'href="javascript:void(0)" onclick="c2f()"');
        }
        if ($mode=='view' && false) {
            if (self::$browsed_records!==null &&
                isset(self::$browsed_records['tab']) &&
                self::$browsed_records['tab']==$this->tab)
                $this->set_module_variable('browsed_records',self::$browsed_records);
            $browsed_records = $this->get_module_variable('browsed_records',null);
            if ($browsed_records!=null) {
                $this->set_module_variable('id',$id);
                if (!is_array($browsed_records['crits'])) $browsed_records['crits'] = array();
                if (!is_array($browsed_records['order'])) $browsed_records['order'] = array();
                if (!is_array($browsed_records['records'])) $browsed_records['records'] = array();
                $ids = Utils_RecordBrowserCommon::get_next_and_prev_record($this->tab, $browsed_records['crits'], $browsed_records['order'], $id, $this->get_module_variable('browsed_records_curr',isset($browsed_records['records'][$id])?$browsed_records['records'][$id]:null));
                if (isset($ids['prev']))
                    $theme->assign('prev_record', '<a '.$this->create_href(array('utils_recordbrowser_move_to_id'=>$ids['prev'])).'>Prev</a>');
                if (isset($ids['next']))
                    $theme->assign('next_record', '<a '.$this->create_href(array('utils_recordbrowser_move_to_id'=>$ids['next'])).'>Next</a>');
                if (isset($ids['curr']))
                    $this->set_module_variable('browsed_records_curr',$ids['curr']);
                if (isset($_REQUEST['utils_recordbrowser_move_to_id']) &&
                    ($_REQUEST['utils_recordbrowser_move_to_id']===$ids['next'] ||
                    $_REQUEST['utils_recordbrowser_move_to_id']===$ids['prev'])) {
                    self::$browsed_records = $browsed_records;
                    $this->set_module_variable('browsed_records_curr',$_REQUEST['utils_recordbrowser_move_to_id']===$ids['next']?$ids['curr']+1:$ids['curr']-1);
                    $this->set_module_variable('id',$_REQUEST['utils_recordbrowser_move_to_id']);
                    unset($_REQUEST['utils_recordbrowser_move_to_id']);
                    location(array());
                    return;
                }
            }
        }
        self::$browsed_records = null;

        Utils_RecordBrowserCommon::$cols_order = array();
        $js = ($mode!='view');
        $time = microtime(true);
        if ($this->is_back()) {
            self::$clone_result = 'canceled';
            return $this->back();
        }

        if ($id!==null) Utils_WatchdogCommon::notified($this->tab,$id);

        $this->init();
        self::$last_record = $this->record = Utils_RecordBrowserCommon::get_record($this->tab, $id, $mode!=='edit');

        if($mode=='add')
            foreach ($defaults as $k=>$v)
                $this->custom_defaults[$k] = $v;

        $access = $this->get_access($mode, isset($this->record)?$this->record:$this->custom_defaults);
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
                print($this->t('You have no longer permission to view this record.'));
                if ($show_actions===true || (is_array($show_actions) && (!isset($show_actions['back']) || $show_actions['back']))) {
                    Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
                    Utils_ShortcutCommon::add(array('esc'), 'function(){'.$this->create_back_href_js().'}');
                }
                return true;
            }
        }

        if ($mode!='add' && !$this->record[':active'] && !Base_AclCommon::i_am_admin()) return $this->back();

        if ($mode=='view')
            $this->record = Utils_RecordBrowserCommon::format_long_text($this->tab,$this->record);

        $tb = $this->init_module('Utils/TabbedBrowser');
        self::$tab_param = $tb->get_path();

        $form = $this->init_module('Libs/QuickForm',null, $mode);
        $this->form = $form;

        if($mode!='add')
            Utils_RecordBrowserCommon::add_recent_entry($this->tab, Acl::get_user(),$id);

        $dpm = DB::GetOne('SELECT data_process_method FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
        if ($dpm!=='') {
            $method = explode('::',$dpm);
            if (is_callable($method)) {
                if ($mode=='view') {
                    $processing_result = call_user_func($method, $this->record, 'display');
                    if (is_array($processing_result))
                        foreach ($processing_result as $k=>$v)
                            $theme->assign($k, $v);
                }
                $processing_result = call_user_func($method, $mode!='add'?$this->record:$this->custom_defaults, $mode=='view'?'view':$mode.'ing');
                if (is_array($processing_result)) {
                    $defaults = $this->custom_defaults = self::$last_record = $this->record = $processing_result;
//                  foreach ($processing_result as $k=>$v) {
//                      $this->record[$k] = $v;
//                      $this->custom_defaults[$k] = $v;
//                      $defaults[$k] = $v;
//                  }
                }
            }
        }

        if (self::$last_record===null) self::$last_record = $defaults;
        if($mode=='add')
            $form->setDefaults($defaults);

        switch ($mode) {
            case 'add':     $this->action = 'New record'; break;
            case 'edit':    $this->action = 'Edit record'; break;
            case 'view':    $this->action = 'View record'; break;
        }

        $this->prepare_view_entry_details($this->record, $mode, $id, $form);

        if ($mode==='edit' || $mode==='add')
            foreach($this->table_rows as $field => $args) {
                if (!$access[$args['id']])
                    $form->freeze($args['id']);
            }
        if ($form->validate()) {
            $values = $form->exportValues();
            foreach ($this->table_rows as $v) {
                if ($v['type']=='checkbox' && !isset($values[$v['id']])) $values[$v['id']]=0;
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
        if (($mode=='edit' || $mode=='add') && $show_actions!==false) {
            Utils_ShortcutCommon::add(array('Ctrl','S'), 'function(){'.$form->get_submit_form_js().'}');
        }
        if ($mode=='edit') {
            $this->set_module_variable('edit_start_time',$time);
        }

        if ($show_actions!==false) {
            if ($mode=='view') {
                if ($this->get_access('edit',$this->record)) {
                    Base_ActionBarCommon::add('edit', 'Edit', $this->create_callback_href(array($this,'navigate'), array('view_entry','edit',$id)));
                    Utils_ShortcutCommon::add(array('Ctrl','E'), 'function(){'.$this->create_callback_href_js(array($this,'navigate'), array('view_entry','edit',$id)).'}');
                }
                if ($this->get_access('delete',$this->record)) {
                    Base_ActionBarCommon::add('delete', 'Delete', $this->create_confirm_callback_href($this->t('Are you sure you want to delete this record?'),array($this,'delete_record'),array($id)));
                }
                if ($this->get_access('clone',$this->record)) {
                    Base_ActionBarCommon::add('clone','Clone', $this->create_confirm_callback_href($this->ht('You are about to create a copy of this record. Do you want to continue?'),array($this,'clone_record'),array($id)));
                }
                if ($show_actions===true || (is_array($show_actions) && (!isset($show_actions['back']) || $show_actions['back'])))
                    Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
            } else {
                Base_ActionBarCommon::add('save', 'Save', $form->get_submit_form_href());
                Base_ActionBarCommon::add('delete', 'Cancel', $this->create_back_href());
            }
            Utils_ShortcutCommon::add(array('esc'), 'function(){'.$this->create_back_href_js().'}');
        }

        if ($mode!='add') {
            $isfav_query_result = DB::GetOne('SELECT user_id FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%d', array(Acl::get_user(), $id));
            $isfav = ($isfav_query_result!==false && $isfav_query_result!==null);
            $theme -> assign('info_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(Utils_RecordBrowserCommon::get_html_record_info($this->tab, $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'" /></a>');
            $row_data= array();

            if ($this->favorites)
                $theme -> assign('fav_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(($isfav?$this->t('This item is on your favourites list<br>Click to remove it from your favorites'):$this->t('Click to add this item to favorites'))).' '.$this->create_callback_href(array($this,($isfav?'remove_from_favs':'add_to_favs')), array($id)).'><img style="width: 14px; height: 14px;" border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_'.($isfav==false?'no':'').'fav.png').'" /></a>');
            if ($this->watchdog)
                $theme -> assign('subscription_tooltip', Utils_WatchdogCommon::get_change_subscription_icon($this->tab, $id));
            if ($this->full_history) {
                $info = Utils_RecordBrowserCommon::get_record_info($this->tab, $id);
                if ($info['edited_on']===null) $theme -> assign('history_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs($this->t('This record was never edited')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history_inactive.png').'" /></a>');
                else $theme -> assign('history_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs($this->t('Click to view edit history of currently displayed record')).' '.$this->create_callback_href(array($this,'navigate'), array('view_edit_history', $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history.png').'" /></a>');
            }
            if ($this->clipboard_pattern) {
                $theme -> assign('clipboard_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs($this->t('Click to export values to copy')).' '.Libs_LeightboxCommon::get_open_href('clipboard').'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','clipboard.png').'" /></a>');
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
                $flash_copy = '<object width="60" height="20">
<param name="FlashVars" value="txtToCopy='.$ftext.'">
<param name="movie" value="'.$this->get_module_dir().'copyButton.swf">
<embed src="'.$this->get_module_dir().'copyButton.swf" flashvars="txtToCopy='.$ftext.'" width="60" height="20">
</embed>
</object>';
                $text = '<h3>'.$this->t('Click Copy under the box or move mouse over box below to select text and hit Ctrl-c to copy it.').'</h3><div onmouseover="fnSelect(this)" style="border: 1px solid gray; margin: 15px; padding: 20px;">'.$text.'</div>'.$flash_copy;

//                $text = '<h3>'.$this->t('Move mouse over box below to select text and hit Ctrl-c to copy it.').'</h3><div onmouseover="fnSelect(this)" style="border: 1px solid gray; margin: 15px; padding: 20px;">'.$text.'</div>';
                Libs_LeightboxCommon::display('clipboard',$text,'Copy');
            }
        }

        if ($mode=='view') $form->freeze();
        $renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty();
        $form->accept($renderer);
        $data = $renderer->toArray();
//      trigger_error(print_r($data,true));

        print($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");

        $last_page = DB::GetOne('SELECT MIN(position) FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field != \'General\'');
        $label = DB::GetRow('SELECT field, param FROM '.$this->tab.'_field WHERE position=%s', array($last_page));
        $cols = $label['param'];
        $label = $label['field'];

        $this->view_entry_details(1, $last_page, $data, $theme, true);
        $ret = DB::Execute('SELECT position, field, param FROM '.$this->tab.'_field WHERE type = \'page_split\' AND position > %d', array($last_page));
        $row = true;
        if ($mode=='view')
            print("</form>\n");
        while ($row) {
            $row = $ret->FetchRow();
            if ($row) $pos = $row['position'];
            else $pos = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field WHERE active=1')+1;

            $valid_page = false;
            foreach($this->table_rows as $field => $args) {
                if (!isset($data[$args['id']]) || $data[$args['id']]['type']=='hidden') continue;
                if ($args['position'] >= $last_page && ($pos+1 == -1 || $args['position'] < $pos+1)) {
                    $valid_page = true;
                    break;
                }
            }
            if ($valid_page && $pos - $last_page>1) $tb->set_tab($this->t($label),array($this,'view_entry_details'), array($last_page, $pos+1, $data, null, false, $cols), $js);
            $cols = $row['param'];
            $last_page = $pos;
            if ($row) $label = $row['field'];
        }
        if ($mode!='add' && $mode!='edit') {
            $ret = DB::Execute('SELECT * FROM recordbrowser_addon WHERE tab=%s AND enabled=1 ORDER BY pos', array($this->tab));
            $addons_mod = array();
            while ($row = $ret->FetchRow()) {
                if (ModuleManager::is_installed($row['module'])==-1) continue;
                if (is_callable(explode('::',$row['label']))) {
                    $result = call_user_func(explode('::',$row['label']), $this->record, $this);
                    if (!isset($result['show'])) $result['show']=true;
                    if ($result['show']==false) continue;
                    if (!isset($result['label'])) $result['label']='';
                    $row['label'] = $result['label'];
                }
                $mod_id = md5(serialize($row));
                $addons_mod[$mod_id] = $this->init_module($row['module']);
                if (!method_exists($addons_mod[$mod_id],$row['func'])) $tb->set_tab($this->t($row['label']),array($this, 'broken_addon'), array(), $js);
                else $tb->set_tab($this->t($row['label']),array($this, 'display_module'), array(& $addons_mod[$mod_id], array($this->record, $this), $row['func']), $js);
            }
        }
        $this->display_module($tb);
        if ($this->switch_to_addon!==null) {
            $ret = DB::Execute('SELECT * FROM recordbrowser_addon WHERE tab=%s AND enabled=1 ORDER BY pos', array($this->tab));
            $tab_counter=0;
            while ($row = $ret->FetchRow()) {
                if (ModuleManager::is_installed($row['module'])==-1) continue;
                if (is_callable(explode('::',$row['label']))) {
                    $result = call_user_func(explode('::',$row['label']), $this->record);
                    if ($result['show']==false) continue;
                    $row['label'] = $result['label'];
                }
                if ($row['label']==$this->switch_to_addon) $this->switch_to_addon = $tab_counter;
                $tab_counter++;
            }
            $tb->switch_tab($this->switch_to_addon);
            location(array());
        }
        if ($mode=='add' || $mode=='edit') {
            print("</form>\n");
        }
        $tb->tag();

        return true;
    } //view_entry

    public function broken_addon(){
        print('Addon is broken, please contact system administrator.');
    }

    public function view_entry_details($from, $to, $data, $theme=null, $main_page = false, $cols = 2){
        if ($theme==null) $theme = $this->init_module('Base/Theme');
        $fields = array();
        $longfields = array();
        foreach($this->table_rows as $field => $args) {
            if (!isset($data[$args['id']]) || $data[$args['id']]['type']=='hidden') continue;
            if ($args['position'] >= $from && ($to == -1 || $args['position'] < $to))
            {
                if (!isset($data[$args['id']])) $data[$args['id']] = array('label'=>'', 'html'=>'');
                    $arr = array(   'label'=>$data[$args['id']]['label'],
                                    'element'=>$args['id'],
                                    'advanced'=>isset($this->advanced[$args['id']])?$this->advanced[$args['id']]:'',
                                    'html'=>$data[$args['id']]['html'],
                                    'style'=>$args['style'],
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
        $theme->assign('action', self::$mode);
        $theme->assign('form_data', $data);
        $theme->assign('required_note', $this->t('Indicates required fields.'));

        $theme->assign('caption',$this->t($this->caption));
        $theme->assign('icon',$this->icon);

        $theme->assign('main_page',$main_page);

        if ($main_page) {
            $tpl = DB::GetOne('SELECT tpl FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
            $theme->assign('raw_data',$this->record);
        } else {
            $tpl = '';
            if (self::$mode=='view') print('<form>');
        }
        $theme->display(($tpl!=='')?$tpl:'View_entry', ($tpl!==''));
        if (!$main_page && self::$mode=='view') print('</form>');
    }

    public function timestamp_required($v) {
        return $v['__datepicker']!=='' && Base_RegionalSettingsCommon::reg2time($v['__datepicker'],false)!==false;
    }

    public function max_description($string){
        return strlen(Utils_BBCodeCommon::strip($string))<400;
    }

    public function prepare_view_entry_details($record, $mode, $id, $form, $visible_cols = null, $for_grid=false){
        //$init_js = '';
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
            $label = '<span id="_'.$args['id'].'__label">'.$this->t($args['name']).'</span>';
            if (isset($this->QFfield_callback_table[$field])) {
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
                    case 'calculated':  $form->addElement('static', $args['id'], $label, array('id'=>$args['id']));
//                                      if ($mode=='edit')
                                        if (!is_array($this->record))
                                            $values = $this->custom_defaults;
                                        else {
                                            $values = $this->record;
                                            if (is_array($this->custom_defaults)) $values = $values+$this->custom_defaults;
                                        }
                                        $val = @$this->get_val($field, $values, true, $args);
                                        if (!$val) $val = '['.$this->t('formula').']';
                                        $form->setDefaults(array($args['id']=>'<div id="'.Utils_RecordBrowserCommon::get_calculated_id($this->tab, $args['id'], $id).'">'.$val.'</div>'));
                                        break;
                    case 'integer':
                    case 'float':       $form->addElement('text', $args['id'], $label, array('id'=>$args['id']));
                                        if ($args['type']=='integer')
                                            $form->addRule($args['id'], $this->t('Only integer numbers are allowed.'), 'regex', '/^[0-9]*$/');
                                        else
                                            $form->addRule($args['id'], $this->t('Only numbers are allowed.'), 'numeric');
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'checkbox':    $form->addElement('checkbox', $args['id'], $label, '', array('id'=>$args['id']));
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'currency':    $form->addElement('currency', $args['id'], $label, array('id'=>$args['id']));
//                                      if ($mode!=='add') $form->setDefaults(array($args['id']=>Utils_CurrencyFieldCommon::format_default($record[$args['id']])));
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'text':        $form->addElement('text', $args['id'], $label, array('id'=>$args['id'], 'maxlength'=>$args['param']));
//                                      else $form->addElement('static', $args['id'], $label, array('id'=>$args['id']));
                                        $form->addRule($args['id'], $this->t('Maximum length for this field is '.$args['param'].'.'), 'maxlength', $args['param']);
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'long text':   $form->addElement($this->add_in_table?'text':'textarea', $args['id'], $label, array('id'=>$args['id'], 'onkeypress'=>'var key=event.which || event.keyCode;return this.value.length < 400 || ((key<32 || key>126) && key!=10 && key!=13) ;'));
                                        $form->registerRule('max_description', 'callback', 'max_description', $this);
                                        $form->addRule($args['id'], $this->t('Maximum length for this field is 400 chars.'), 'max_description');
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'date':        $form->addElement('datepicker', $args['id'], $label, array('id'=>$args['id'], 'label'=>$this->add_in_table?'':null));
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'timestamp':   $form->addElement('timestamp', $args['id'], $label, array('id'=>$args['id']));
                                        static $rule_defined = false;
                                        if (!$rule_defined) $form->registerRule('timestamp_required', 'callback', 'timestamp_required', $this);
                                        $rule_defined = true;
                                        if (isset($args['required']) && $args['required']) $form->addRule($args['id'], Base_LangCommon::ts('Utils_RecordBrowser','Field required'), 'timestamp_required');
                                        if ($mode!=='add' && $record[$args['id']]) $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'time':        $time_format = Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';
                                        $lang_code = Base_LangCommon::get_lang_code();
                                        $form->addElement('timestamp', $args['id'], $label, array('date'=>false, 'format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code, 'id'=>$args['id']));
                                        if ($mode!=='add' && $record[$args['id']]) $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'commondata':  $param = explode('::',$args['param']['array_id']);
                                        foreach ($param as $k=>$v) if ($k!=0) $param[$k] = preg_replace('/[^a-z0-9]/','_',strtolower($v));
                                        $form->addElement($args['type'], $args['id'], $label, $param, array('empty_option'=>true, 'id'=>$args['id'], 'order_by_key'=>$args['param']['order_by_key']));
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                    case 'select':
                    case 'multiselect': $comp = array();
                                        $ref = explode(';',$args['param']);
                                        if (isset($ref[1])) $crits_callback = $ref[1];
                                        else $crits_callback = null;
                                        if (isset($ref[2])) $multi_adv_params = call_user_func(explode('::',$ref[2]));
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
                                            $rec_count = Utils_RecordBrowserCommon::get_records_count($tab, $crits, empty($multi_adv_params['format_callback'])?$col_id:array(), !empty($multi_adv_params['order'])?$multi_adv_params['order']:array());
                                            if ($rec_count<=50 || $args['type']!='multiselect') {
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
                                        }
                                        if ($args['type']==='select') $comp = array(''=>'---')+$comp;
                                        if ($rec_count>50 && $args['type']=='multiselect') {
                                            $f_callback = $multi_adv_params['format_callback'];
                                            $form->addElement('automulti', $args['id'], $label, array('Utils_RecordBrowserCommon','automulti_suggestbox'), array($this->tab, $crits, $f_callback, $args['param']), $f_callback);
                                        } else {
                                            $form->addElement($args['type'], $args['id'], $label, $comp, array('id'=>$args['id']));
                                        }
                                        if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
                                        break;
                }
            }
            if ($args['required'])
                $form->addRule($args['id'], $this->t('Field required'), 'required');
        }
        //eval_js($init_js);
    }
    public function add_to_favs($id) {
        DB::Execute('INSERT INTO '.$this->tab.'_favorite (user_id, '.$this->tab.'_id) VALUES (%d, %d)', array(Acl::get_user(), $id));
    }
    public function remove_from_favs($id) {
        DB::Execute('DELETE FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%d', array(Acl::get_user(), $id));
    }
    public function update_record($id,$values) {
        Utils_RecordBrowserCommon::update_record($this->tab, $id, $values);
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function administrator_panel() {
        $_SESSION['client']['recordbrowser']['admin_access'] = true;
        Utils_RecordBrowserCommon::$admin_access = true;
        $this->init();
        $tb = $this->init_module('Utils/TabbedBrowser');

        $tb->set_tab($this->t('Manage Records'),array($this, 'show_data'), array(array(), array(), array(), true) );
        $tb->set_tab($this->t('Manage Fields'),array($this, 'setup_loader') );
        $tb->set_tab($this->t('Manage Addons'),array($this, 'manage_addons') );
        $tb->set_tab($this->t('Clipboard Pattern'), array($this, 'setup_clipboard_pattern') );

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
        $gb = $this->init_module('Utils/GenericBrowser','manage_addons'.$this->tab, 'manage_addons'.$this->tab);
        $gb->set_table_columns(array(
                                array('name'=>$this->t('Addon caption')),
                                array('name'=>$this->t('Called method'))
                                ));
        $add = DB::GetAll('SELECT * FROM recordbrowser_addon WHERE tab=%s ORDER BY pos',array($this->tab));
        $first = true;
        foreach ($add as $v) {
            if (isset($gb_row)) $gb_row->add_action($this->create_callback_href(array($this, 'move_addon'),array($v['tab'],$v['pos']-1, +1)),'Move down', null, 'move-down');
            $gb_row = $gb->get_new_row();
            $gb_row->add_data($v['label'], $v['module'].' -> '.$v['func'].'()');
            $gb_row->add_action($this->create_callback_href(array($this, 'set_addon_active'), array($v['tab'],$v['pos'],!$v['enabled'])), ($v['enabled']?'Dea':'A').'ctivate', null, 'active-'.($v['enabled']?'on':'off'));

            if (!$first) $gb_row->add_action($this->create_callback_href(array($this, 'move_addon'),array($v['tab'],$v['pos'], -1)),'Move up', null, 'move-up');
            $first = false;
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

        $form->addElement('header', null, $this->t('Edit page properties'));
        $form->addElement('text', 'label', $this->t('Label'));
        $this->current_field = $id;
        $form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', $this);
        $form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', $this);
        $form->addRule('label', $this->t('Field required.'), 'required');
        $form->addRule('label', $this->t('Field or Page with this name already exists.'), 'check_if_column_exists');
        $form->addRule('label', $this->t('Only letters and space are allowed.'), 'regex', '/^[a-zA-Z ]*$/');
        $form->addRule('label', $this->t('"ID" as page name is not allowed.'), 'check_if_no_id');
        $form->setDefaults(array('label'=>$id));

        $ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->ht('OK'));
        $cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->ht('Cancel'), $this->create_back_href());
        $form->addGroup(array($ok_b, $cancel_b));

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
        return true;
    }
    public function setup_clipboard_pattern() {
        $form = $this->init_module('Libs/QuickForm');
        $r = Utils_RecordBrowserCommon::get_clipboard_pattern($this->tab, true);
        $form->addElement('select', 'enable', $this->t('Enable'), array($this->t('No'), $this->t('Yes')));
        $info = '<b>'.$this->t('This is html pattern. All html tags are allowed.<br/>Use &lt;pre&gt; some text &lt;/pre&gt; to generate text identical as you typed it.<br/><br/>Conditional use:<br/>%%{lorem {keyword} ipsum {keyword2}}<br/>lorem ipsum will be shown only when at least one of keywords has value. Nested conditions are allowed.<br/><br/>Normal use:<br/>%%{{keyword}}<br/><br/>Keywords:<br/>').'</b>';
        foreach($this->table_rows as $name=>$val) {
            $info .= '<b>'.$val['id'].'</b> - '.$name.', ';
        }
        $label = '<img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser', 'info.png').'" '.Utils_TooltipCommon::open_tag_attrs($info).'/> '.$this->t('Pattern');
        $textarea = $form->addElement('textarea', 'pattern', $label);
        $textarea->setRows(12);
        $textarea->setCols(80);
        $form->addElement('submit', 'save', $this->t('Save'));
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

        Base_ActionBarCommon::add('add','New field',$this->create_callback_href(array($this, 'view_field')));
        Base_ActionBarCommon::add('add','New page',$this->create_callback_href(array($this, 'new_page')));
        $gb = $this->init_module('Utils/GenericBrowser', null, 'fields');
        $gb->set_table_columns(array(
            array('name'=>$this->t('Field'), 'width'=>20),
            array('name'=>$this->t('Type'), 'width'=>20),
            array('name'=>$this->t('Table view'), 'width'=>5),
            array('name'=>$this->t('Required'), 'width'=>5),
            array('name'=>$this->t('Filter'), 'width'=>5),
            array('name'=>$this->t('Parameters'), 'width'=>5))
        );

        //read database
        $rows = count($this->table_rows);
        $max_p = DB::GetOne('SELECT MIN(position) FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field!=\'General\'');
        foreach($this->table_rows as $field=>$args) {
            $gb_row = $gb->get_new_row();
            if($args['extra']) {
                if ($args['type'] != 'page_split') {
                    $gb_row->add_action($this->create_callback_href(array($this, 'view_field'),array('edit',$field)),'Edit');
                } else {
                    $gb_row->add_action($this->create_callback_href(array($this, 'delete_page'),array($field)),'Delete');
                    $gb_row->add_action($this->create_callback_href(array($this, 'edit_page'),array($field)),'Edit');
                }
            } else {
                if ($field!='General' && $args['type']=='page_split')
                    $gb_row->add_action($this->create_callback_href(array($this, 'edit_page'),array($field)),'Edit');
            }
            if ($args['type']!=='page_split' && $args['extra']){
                if ($args['active']) $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, false)),'Deactivate', null, 'active-on');
                else $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, true)),'Activate', null, 'active-off');
            }
            if ($args['position']>$max_p && $args['position']<=$rows || ($args['position']<$max_p-1 && $args['position']>2))
                $gb_row->add_action($this->create_callback_href(array($this, 'move_field'),array($field, $args['position'], +1)),'Move down', null, 'move-down');
            if ($args['position']>$max_p+1 || ($args['position']<$max_p && $args['position']>3))
                $gb_row->add_action($this->create_callback_href(array($this, 'move_field'),array($field, $args['position'], -1)),'Move up', null, 'move-up');
            if ($args['type']=='text')
                $args['param'] = $this->t('Length').' '.$args['param'];
            if ($args['type'] == 'page_split')
                    $gb_row->add_data(
                        array('style'=>'background-color: #DFDFFF;', 'value'=>$field),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>$this->t('Page Split')),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>'')
                    );
                else
                    $gb_row->add_data(
                        $field,
                        $args['type'],
                        $args['visible']?$this->t('<b>Yes</b>'):$this->t('No'),
                        $args['required']?$this->t('<b>Yes</b>'):$this->t('No'),
                        $args['filter']?$this->t('<b>Yes</b>'):$this->t('No'),
                        is_array($args['param'])?serialize($args['param']):$args['param']
                    );
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
            'long text'=>'long text'
        );
        natcasesort($data_type);

        $form = $this->init_module('Libs/QuickForm');

        switch ($action) {
            case 'add': $form->addElement('header', null, $this->t('Add new field'));
                        break;
            case 'edit': $form->addElement('header', null, $this->t('Edit field properties'));
                        break;
        }
        $form->addElement('text', 'field', $this->t('Field'), array('maxlength'=>32));
        $form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', $this);
        $this->current_field = $field;
        $form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', $this);
        $form->addRule('field', $this->t('Field required.'), 'required');
        $form->addRule('field', $this->t('Field with this name already exists.'), 'check_if_column_exists');
        $form->addRule('field', $this->t('Field length cannot be over 32 characters.'), 'maxlength', 32);
        $form->addRule('field', $this->t('Only letters and space are allowed.'), 'regex', '/^[a-zA-Z ]*$/');
        $form->addRule('field', $this->t('"ID" as field name is not allowed.'), 'check_if_no_id');


        if ($action=='edit') {
            $row = DB::GetRow('SELECT field, type, visible, required, param, filter FROM '.$this->tab.'_field WHERE field=%s',array($field));
            $form->setDefaults($row);
            $form->addElement('static', 'select_data_type', $this->t('Data Type'), $row['type']);
            $selected_data= $row['type'];
        } else {
            $form->addElement('select', 'select_data_type', $this->t('Data Type'), $data_type);
            $selected_data= $form->exportValue('select_data_type');
            $form->setDefaults(array('visible'=>1));
        }
        switch($selected_data) {
            case 'text':
                if ($action=='edit')
                    $form->addElement('static', 'text_length', $this->t('Length'), $row['param']);
                else {
                    $form->addElement('text', 'text_length', $this->t('Length'));
                    $form->addRule('text_length', $this->t('Field required'), 'required');
                    $form->addRule('text_length', $this->t('Must be a number greater than 0.'), 'regex', '/^[1-9][0-9]*$/');
                }
                break;
        }
        $form->addElement('checkbox', 'visible', $this->t('Table view'));
        $form->addElement('checkbox', 'required', $this->t('Required'));
        $form->addElement('checkbox', 'filter', $this->t('Filter enabled'));

        $ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->ht('OK'));
        $cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->ht('Cancel'), $this->create_back_href());
        $form->addGroup(array($ok_b, $cancel_b));

        if ($form->validate()) {
            $data = $form->exportValues();
            $data['field'] = trim($data['field']);
            if ($action=='add')
                $field = $data['field'];
            $id = preg_replace('/[^a-z0-9]/','_',strtolower($field));
            $new_id = preg_replace('/[^a-z0-9]/','_',strtolower($data['field']));
            if (preg_match('/^[a-z0-9_]*$/',$id)==0) trigger_error('Invalid column name: '.$field);
            if (preg_match('/^[a-z0-9_]*$/',$new_id)==0) trigger_error('Invalid new column name: '.$data['field']);
            if ($action=='add') {
                $id = $new_id;
                if (in_array($data['select_data_type'], array('time','timestamp','currency','integer')))
                    $style = $data['select_data_type'];
                else
                    $style = '';
                Utils_RecordBrowserCommon::new_record_field($this->tab, $data['field'], $data['select_data_type'], 0, 0, isset($data['text_length'])?$data['text_length']:'', $style);
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
                    $type = DB::GetOne('SELECT type FROM '.$this->tab.'_field WHERE field=%s', array($field));
                    $param = DB::GetOne('SELECT param FROM '.$this->tab.'_field WHERE field=%s', array($field));
                    DB::RenameColumn($this->tab.'_data_1', 'f_'.$id, 'f_'.$new_id, Utils_RecordBrowserCommon::actual_db_type($type, $param));
                }
            }
            DB::Execute('UPDATE '.$this->tab.'_field SET field=%s, visible=%d, required=%d, filter=%d WHERE field=%s',
                        array($data['field'], $data['visible'], $data['required'], $data['filter'], $field));
            DB::Execute('UPDATE '.$this->tab.'_edit_history_data SET field=%s WHERE field=%s',
                        array($new_id, $id));
            DB::CompleteTrans();
            $this->init(true, true);
            return false;
        }
        $form->display();
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
        print('<b>'.$this->t('The following changes were applied to this record while you were editing it.<br>Please revise this data and make sure to keep this record most accurate.').'</b><br>');
        $gb_cha = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__changes');
        $table_columns_changes = array( array('name'=>$this->t('Date'), 'width'=>1, 'wrapmode'=>'nowrap'),
                                        array('name'=>$this->t('Username'), 'width'=>1, 'wrapmode'=>'nowrap'),
                                        array('name'=>$this->t('Field'), 'width'=>1, 'wrapmode'=>'nowrap'),
                                        array('name'=>$this->t('Old value'), 'width'=>1, 'wrapmode'=>'nowrap'),
                                        array('name'=>$this->t('New value'), 'width'=>1, 'wrapmode'=>'nowrap'));
        $gb_cha->set_table_columns( $table_columns_changes );

        $created = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
        $created['created_by_login'] = Base_UserCommon::get_user_login($created['created_by']);
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
                    $row['edited_by']!==null?Base_UserCommon::get_user_login($row['edited_by']):'',
                    $field_hash[$k],
                    $old,
                    $new
                );
            }
        }
        $theme = $this->init_module('Base/Theme');
        $theme->assign('table',$this->get_html_of_module($gb_cha));
        $theme->assign('label',$this->t('Recent Changes'));
        $theme->display('View_history');
    }
    public function view_edit_history($id){
        if ($this->is_back())
            return $this->back();
        $this->init();
        $gb_cur = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__current');
        $gb_cha = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__changes');
        $gb_ori = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__original');

        $table_columns = array( array('name'=>$this->t('Field'), 'width'=>1, 'wrapmode'=>'nowrap'),
                                array('name'=>$this->t('Value'), 'width'=>1, 'wrapmode'=>'nowrap'));
        $table_columns_changes = array( array('name'=>$this->t('Date'), 'width'=>1, 'wrapmode'=>'nowrap'),
                                        array('name'=>$this->t('Username'), 'width'=>1, 'wrapmode'=>'nowrap'),
                                        array('name'=>$this->t('Field'), 'width'=>1, 'wrapmode'=>'nowrap'),
                                        array('name'=>$this->t('Old value'), 'width'=>1, 'wrapmode'=>'nowrap'),
                                        array('name'=>$this->t('New value'), 'width'=>1, 'wrapmode'=>'nowrap'));

        $gb_cur->set_table_columns( $table_columns );
        $gb_ori->set_table_columns( $table_columns );
        $gb_cha->set_table_columns( $table_columns_changes );

        $created = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
        $access = $this->get_access('view', $created);
        $created['created_by_login'] = Base_UserCommon::get_user_login($created['created_by']);
        $field_hash = array();
        $edited = DB::GetRow('SELECT ul.login, c.edited_on FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->tab.'_id=%d ORDER BY edited_on DESC',array($id));
//      if (!isset($edited['login']))
//          return;
        $gb_cur->add_row($this->t('Edited by'), $edited['login']);
        $gb_cur->add_row($this->t('Edited on'), Base_RegionalSettingsCommon::time2reg($edited['edited_on']));
        foreach($this->table_rows as $field => $args) {
            if (!$access[$args['id']]) continue;
            $field_hash[$args['id']] = $field;
            $val = $this->get_val($field, $created, false, $args);
            if ($created[$args['id']] !== '') $gb_cur->add_row($this->t($field), $val);
        }

        $ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->tab.'_id=%d ORDER BY edited_on DESC, id DESC',array($id));
        while ($row = $ret->FetchRow()) {
            $changed = array();
            $ret2 = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
            while($row2 = $ret2->FetchRow()) {
                if ($row2['field']!='id' && !$access[$row2['field']]) continue;
                $changed[$row2['field']] = $row2['old_value'];
                $last_row = $row2;
            }
            foreach($changed as $k=>$v) {
                if ($k=='id') $gb_cha->add_row($row['edited_on'], $row['edited_by']!==null?Base_UserCommon::get_user_login($row['edited_by']):'', '<b>'.$last_row['old_value'].'</b>', '', '');
                else {
                    if (!isset($field_hash[$k])) continue;
                    $new = $this->get_val($field_hash[$k], $created, false, $this->table_rows[$field_hash[$k]]);
                    if ($this->table_rows[$field_hash[$k]]['type']=='multiselect') $v = Utils_RecordBrowserCommon::decode_multi($v);
                    $created[$k] = $v;
                    $old = $this->get_val($field_hash[$k], $created, false, $this->table_rows[$field_hash[$k]]);
                    $gb_cha->add_row(
                        Base_RegionalSettingsCommon::time2reg($row['edited_on']),
                        $row['edited_by']!==null?Base_UserCommon::get_user_login($row['edited_by']):'',
                        $this->t($field_hash[$k]),
                        $old,
                        $new
                    );
                }
            }
        }
        $gb_ori->add_row($this->t('Created by'), $created['created_by_login']);
        $gb_ori->add_row($this->t('Created on'), Base_RegionalSettingsCommon::time2reg($created['created_on']));
        foreach($this->table_rows as $field => $args) {
            if (!$access[$args['id']]) continue;
            $val = $this->get_val($field, $created, false, $args);
            if ($created[$args['id']] !== '') $gb_ori->add_row($this->t($field), $val);
        }
        $theme = $this->init_module('Base/Theme');
        $theme->assign('table',$this->get_html_of_module($gb_cur));
        $theme->assign('label',$this->t('Current Record'));
        $theme->display('View_history');
        $theme = $this->init_module('Base/Theme');
        $theme->assign('table',$this->get_html_of_module($gb_cha));
        $theme->assign('label',$this->t('Changes History'));
        $theme->display('View_history');
        $theme = $this->init_module('Base/Theme');
        $theme->assign('table',$this->get_html_of_module($gb_ori));
        $theme->assign('label',$this->t('Original Record'));
        $theme->display('View_history');
        Base_ActionBarCommon::add('back','Back',$this->create_back_href());
        return true;
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
    public function set_filters_defaults($arg){
		if(!$this->isset_module_variable('def_filter')) {
			$r = array();
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
        if ($this->amount_of_records>=250) {
            $theme->assign('disabled', '_disabled');
            $theme->assign('select_all', array('js'=>'', 'label'=>$this->t('Select all')));
            $theme->assign('deselect_all', array('js'=>'', 'label'=>$this->t('Deselect all')));
        } else {
            load_js('modules/Utils/RecordBrowser/RecordPicker/select_all.js');
            $theme->assign('select_all', array('js'=>'RecordPicker_select_all(1,\''.$this->get_path().'\',\''.Base_LangCommon::ts('Utils/RecordBrowser','processing...').'\');', 'label'=>$this->t('Select all')));
            $theme->assign('deselect_all', array('js'=>'RecordPicker_select_all(0,\''.$this->get_path().'\',\''.Base_LangCommon::ts('Utils/RecordBrowser','processing...').'\');', 'label'=>$this->t('Deselect all')));
        }
        load_js('modules/Utils/RecordBrowser/rpicker.js');

        $rpicker_ind = $this->get_module_variable('rpicker_ind');
        foreach($rpicker_ind as $v) {
            eval_js('rpicker_init(\''.$element.'\','.$v.')');
        }
        $theme->display('Record_picker');
    }
    public function recordpicker_fs($crits, $cols, $order, $filters, $path) {
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
        if ($this->amount_of_records>=250) {
            $theme->assign('disabled', '_disabled');
            $theme->assign('select_all', array('js'=>'', 'label'=>$this->t('Select all')));
            $theme->assign('deselect_all', array('js'=>'', 'label'=>$this->t('Deselect all')));
        } else {
            load_js('modules/Utils/RecordBrowser/RecordPickerFS/select_all.js');
            $theme->assign('select_all', array('js'=>'RecordPicker_select_all(1,\''.$this->get_path().'\',\''.Base_LangCommon::ts('Utils/RecordBrowser','processing...').'\');', 'label'=>$this->t('Select all')));
            $theme->assign('deselect_all', array('js'=>'RecordPicker_select_all(0,\''.$this->get_path().'\',\''.Base_LangCommon::ts('Utils/RecordBrowser','processing...').'\');', 'label'=>$this->t('Deselect all')));
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
        $ret = DB::Execute('SELECT tab FROM recordbrowser_table_properties');
        $form = $this->init_module('Libs/QuickForm');
        $opts = array();
        $first = false;
        while ($row=$ret->FetchRow()) {
            if (!$first) $first = $row['tab'];
            $opts[$row['tab']] = ucfirst(preg_replace('/[^a-z0-9]/','_',$row['tab']));
        }
        $form->addElement('select', 'recordset', $this->t('Record Set'), $opts, array('onchange'=>$form->get_submit_form_js()));
        if ($form->validate()) {
            $tab = $form->exportValue('recordset');
            $this->set_module_variable('admin_browse_recordset', $tab);
        }
        $tab = $this->get_module_variable('admin_browse_recordset', $first);
        $form->setDefaults(array('recordset'=>$tab));
        $form->display();
        if ($tab) $this->record_management($tab);
    }
    public function record_management($table){
        $rb = $this->init_module('Utils/RecordBrowser',$table,$table);
        $this->display_module($rb, null, 'administrator_panel');
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
    public function set_crm_filter($field){
        $this->filter_field = $field;
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
        $cut = array();
        $callbacks = array();
        foreach($cols as $k=>$v) {
            if (isset($v['cut'])) $cut[] = $v['cut'];
            else $cut[] = -1;
            if (isset($v['callback'])) $callbacks[] = $v['callback'];
            else $callbacks[] = null;
            if (is_array($v)) {
                $arr = array('name'=>$this->t($field_hash[$v['field']]), 'width'=>$v['width']);
                $cols[$k] = $v['field'];
            } else {
                $arr = array('name'=>$this->t($field_hash[$v]));
                $cols[$k] = $v;
            }
            if (isset($v['label'])) $arr['name'] = $v['label'];
            $arr['wrapmode'] = 'nowrap';
            $header[] = $arr;
        }
        $gb->set_table_columns($header);

        $clean_order = array();
        foreach($order as $k=>$v) {
            $clean_order[] = array('column'=>$field_hash[$k],'order'=>$field_hash[$k],'direction'=>$v);
        }
        if ($limit!=null && !isset($conf['force_limit'])) {
            $limit = array('offset'=>0, 'numrows'=>$limit);
            $records_qty = Utils_RecordBrowserCommon::get_records_count($this->tab, $crits);
            if ($records_qty>$limit['numrows']) {
                if ($this->get_module_variable('no_limit_in_mini_view',false)) {
                    $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs($this->t('Display first %d records', array($limit['numrows']))).' '.$this->create_callback_href(array($this, 'set_no_limit_in_mini_view'), array(false)).'><img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','show_some.png').'" border="0"></a>';
                    $limit = null;
                } else {
                    print($this->t('Displaying %s of %s records', array($limit['numrows'], $records_qty)));
                    $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs($this->t('Display all records')).' '.$this->create_callback_href(array($this, 'set_no_limit_in_mini_view'), array(true)).'><img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','show_all.png').'" border="0"></a>';
                }
            }
        }
        $records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, array(), $clean_order, $limit);
        $records = Utils_RecordBrowserCommon::format_long_text_array($this->tab,$records);
        foreach($records as $v) {
            $gb_row = $gb->get_new_row();
            $arr = array();
            foreach($cols as $k=>$w) {
                if (!isset($callbacks[$k])) $s = $this->get_val($field_hash[$w], $v, false, $this->table_rows[$field_hash[$w]]);
                else $s = call_user_func($callbacks[$k], $v);
                $arr[] = Utils_RecordBrowserCommon::cut_string($s, $cut[$k]);
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
            if (isset($conf['actions_delete']) && $conf['actions_delete']) if ($this->get_access('delete',$v)) $gb_row->add_action($this->create_confirm_callback_href($this->t('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $v['id'])),'Delete');
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

    public function search_by_id_form($label) {
        $message = '';
        $form = $this->init_module('Libs/QuickForm');
        $theme = $this->init_module('Base/Theme');
        $form->addElement('text', 'record_id', $label);
        $form->addRule('record_id', 'Must be a number', 'numeric');
        $form->addRule('record_id', 'Field required', 'required');
        $ret = false;
        if ($form->validate()) {
            $id = $form->exportValue('record_id');
            if (!is_numeric($id)) trigger_error('Invalid id',E_USER_ERROR);
            $r = Utils_RecordBrowserCommon::get_record($this->tab,$id);
            if (!$r || empty($r)) $message = $this->t('There is no such record').'<br>';
            else if (!$r[':active']) $message = $this->t('This record was deleted from the system').'<br>';
            else {
                $x = ModuleManager::get_instance('/Base_Box|0');
                if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
                $x->push_main('Utils/RecordBrowser','view_entry',array('view', $id),array($this->tab));
                return;
            }
            $ret = true;
        }
        $form->assign_theme('form', $theme);
        $theme->assign('message', $message);
        $theme->display('search_by_id');
        return $ret;
    }

}
?>
