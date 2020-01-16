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
    private $switch_to_addon = null;
    private $additional_caption = '';
    private $enable_export = false;
	private $search_calculated_callback = false;
	private $fields_in_tabs = array();
	private $hide_tab = array();
    private $jump_to_new_record = false;
    private $expandable_rows = true;
    public $action = 'Browsing'; // _M('Browsing');
    public $custom_defaults = array();
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
    private $additional_actions_methods = array();
    private $filter_crits = array();
    private $disabled = array('search'=>false, 'browse_mode'=>false, 'watchdog'=>false, 'quickjump'=>false, 'filters'=>false, 'headline'=>false, 'actions'=>false, 'fav'=>false, 'pdf'=>false, 'export'=>false, 'pagination'=>false);
    private $force_order;
    private $clipboard_pattern = false;
    private $show_add_in_table = false;
    private $data_gb = null;
    public $view_fields_permission;
    public $form = null;
    public $tab;
    public $grid = null;
    private $fixed_columns_class = array('Utils_RecordBrowser__favs', 'Utils_RecordBrowser__watchdog');
    private $include_tab_in_id = false;

	public function new_button($type, $label, $href) {
		if ($this->fullscreen_table)
			Base_ActionBarCommon::add($type, $label, $href);
		else {
			if (!file_exists($type))
				$type = Base_ThemeCommon::get_template_file(Base_ActionBar::module_name(), 'icons/'.$type.'.png');
			$this->more_add_button_stuff .= '<a class="record_browser_button" id="Base_ActionBar" '.$href.'>'.'<img src="'.$type.'">'.
				'<div style="display:inline-block;position: relative;top:-8px;">'.$label.'</div>'.
				'</a>';
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
    
    public function get_crits() {
    	return $this->crits;
    }

    public function get_final_crits() {
        if (!$this->displayed()) trigger_error('You need to call display_module() before calling get_final_crits() method.', E_USER_ERROR);
        return $this->get_module_variable('crits_stuff');
    }

    public function enable_export($arg) {
        $this->enable_export = $arg;
    }
    
    public function set_caption($caption) {
    	$this->caption = $caption;
    }
    
    public function set_icon($icon) {
    	if (!$icon) return;
    	
    	if (is_array($icon)) {
    		$icon = array_values($icon);
    		$icon = Base_ThemeCommon::get_template_file($icon[0], isset($icon[1])? $icon[1]: null);
    	}
    	
    	$this->icon = $icon;
    }

    public function set_additional_caption($arg) {
        $this->additional_caption = $arg;
    }

    public function set_jump_to_new_record($arg = true) {
        $this->jump_to_new_record = $arg;
    }

    /**
     * @param string $ar Field name
     *
     * @deprecated
     * @return callable Display callback
     */
    public function get_display_method($ar) {
        return $this->get_display_callback($ar);
    }

    public function get_display_callback($ar) {
        return isset($this->display_callback_table[$ar])?$this->display_callback_table[$ar]:null;
    }

    /**
     * @param string $field Field name
     *
     * @deprecated
     * @return callable QFfield callback
     */
    public function get_qffield_method($field)
    {
        return $this->get_QFfield_callback($field);
    }

    public function get_QFfield_callback($field) {
        return isset($this->QFfield_callback_table[$field]) ? $this->QFfield_callback_table[$field] : null;
    }

    public function set_additional_actions_method($callback) {
        $this->additional_actions_methods[] = $callback;
    }

    private function call_additional_actions_methods($row, $gb_row)
    {
        foreach ($this->additional_actions_methods as $callback) {
            if (is_callable($callback)) {
                call_user_func($callback, $row, $gb_row, $this);
            }
        }
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

    public function set_expandable_rows($bool)
    {
        $this->expandable_rows = $bool;
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
    public function disable_pagination($arg=true) {$this->disabled['pagination'] = $arg;}
    public function disable_add_button() {$this->add_button = false;}

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

 	public function construct($tab = null, $special = false) {
		Utils_RecordBrowserCommon::$options_limit = Base_User_SettingsCommon::get('Utils_RecordBrowser','enable_autocomplete');
        if (!$special)
			self::$rb_obj = $this;
        $this->tab = & $this->get_module_variable('tab', $tab);
        if ($this->tab!==null) Utils_RecordBrowserCommon::check_table_name($this->tab);
		load_js($this->get_module_dir() . 'main.js');
    }

    public function init($admin=false, $force=false) {
        if($this->tab=='__RECORDSETS__' || preg_match('/,/',$this->tab)) $params=array('','',0,0,0);
        else $params = DB::GetRow('SELECT caption, icon, recent, favorites, full_history FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
        if ($params==false) trigger_error('There is no such recordSet as '.$this->tab.'.', E_USER_ERROR);
        list($caption,$icon,$this->recent,$this->favorites,$this->full_history) = $params;
        $this->favorites &= !$this->disabled['fav'];
        $this->watchdog = Utils_WatchdogCommon::category_exists($this->tab) && !$this->disabled['watchdog'];
        $this->clipboard_pattern = Utils_RecordBrowserCommon::get_clipboard_pattern($this->tab);

        //If Caption or icon not specified assign default values
        $this->caption = $this->caption?: $caption?: 'Record Browser';
        $this->icon = $this->icon?: Base_ThemeCommon::get_template_file($icon)?: Base_ThemeCommon::get_template_file('Base_ActionBar','icons/settings.png');

        $this->table_rows = Utils_RecordBrowserCommon::init($this->tab, $admin, $force);
        $this->requires = array();
        $this->display_callback_table = array();
        $this->QFfield_callback_table = array();
        if($this->tab=='__RECORDSETS__' || preg_match('/,/',$this->tab)) return;
        $ret = DB::Execute('SELECT * FROM '.$this->tab.'_callback');
        while ($row = $ret->FetchRow())
            if ($row['freezed']==1) $this->display_callback_table[$row['field']] = $row['callback'];
            else $this->QFfield_callback_table[$row['field']] = $row['callback'];
    }

    public function check_for_jump() {
        $x = Utils_RecordBrowserCommon::check_for_jump();
        if($x)
            self::$browsed_records = $this->get_module_variable('set_browsed_records',null);
        return $x;
    }
	
	public function add_note_button_href($key=null) {
        return Utils_RecordBrowserCommon::create_new_record_href('utils_attachment',array('permission'=>'0','local'=>$key,'func'=>serialize(array('Utils_RecordBrowserCommon','create_default_linked_label')),'args'=>serialize(explode('/',$key))));
	}
	
	public function add_note_button($key=null) {
		$href = $this->add_note_button_href($key);
		return '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Note')).' '.$href.'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_Attachment','icon_small.png').'"></a>';
	}
    // BODY //////////////////////////////////////////////////////////////////////////////////////////////////////
    public function body($def_order=array(), $crits=array(), $cols=array(), $filters_set=array()) {
		Base_HelpCommon::screen_name('browse_'.$this->tab);
        unset($_SESSION['client']['recordbrowser']['admin_access']);
        if ($this->check_for_jump()) return;
        $this->fullscreen_table=true;
        $this->init();
        $this->jump_to_new_record = true;
        if ($this->get_access('browse')===false) {
            print(__('You are not authorised to browse this data.'));
            return;
        }
        if ($this->watchdog) Utils_WatchdogCommon::add_actionbar_change_subscription_button($this->tab);
        $this->is_on_main_page = true;

        $this->data_gb = $this->init_module(Utils_GenericBrowser::module_name(), null, $this->tab);

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

        $this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $crits);

        $theme = $this->init_module(Base_Theme::module_name());
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
                $this->browse_mode = $this->get_module_variable('browse_mode', Base_User_SettingsCommon::get(Utils_RecordBrowser::module_name(),$this->tab.'_default_view'));
                if (!$this->browse_mode) $this->browse_mode='all';
                if (($this->browse_mode=='recent' && $this->recent==0) || ($this->browse_mode=='favorites' && !$this->favorites)) $this->set_module_variable('browse_mode', $this->browse_mode='all');
                $form = $this->init_module(Libs_QuickForm::module_name());
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
        if (!$this->disabled['headline']) $theme->assign('caption', _V($this->caption).($this->additional_caption?' - '.$this->additional_caption:'').($this->get_jump_to_id_button()));
        $theme->assign('icon', $this->icon);
        $theme->display('Browsing_records');
    }
    public function switch_view($mode){
        Base_User_SettingsCommon::save(Utils_RecordBrowser::module_name(),$this->tab.'_default_view',$mode);
        $this->browse_mode = $mode;
        $this->changed_view = true;
        $this->set_module_variable('browse_mode', $mode);
    }

    //////////////////////////////////////////////////////////////////////////////////////////
    public function show_filters($filters_set = array(), $f_id='') {
    	if (!$this->data_gb) $this->data_gb = $this->init_module(Utils_GenericBrowser::module_name(), null, $this->tab);
    	
    	$filter_module = $this->init_module(Utils_RecordBrowser_Filters::module_name(), array($this, $this->filter_crits, $this->custom_filters), $this->tab . 'filters');
    	
    	$ret = $filter_module->get_filters_html($this->data_gb->show_all(), $filters_set, $f_id);

    	$this->crits = $filter_module->get_crits();
    	
    	return $ret;
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function navigate($func){
        $args = func_get_args();
        array_shift($args);
        Base_BoxCommon::push_module(Utils_RecordBrowser::module_name(),$func,$args,array(self::$clone_result!==null?self::$clone_tab:$this->tab),md5($this->get_path()).'_r');
        $this->navigation_executed = true;
        return false;
    }
    public function back(){
    	Base_BoxCommon::pop_main();
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function show_data($crits = array(), $cols = array(), $order = array(), $admin = false, $special = false, $pdf = false, $limit = null) {
		$this->help('RecordBrowser','main');
		if (Utils_RecordBrowserCommon::$admin_access) $admin = true;
        if (isset($_SESSION['client']['recordbrowser']['admin_access'])) Utils_RecordBrowserCommon::$admin_access = true;
        if (self::$clone_result!==null && $this->jump_to_new_record) {
            if (is_numeric(self::$clone_result)) $this->navigate('view_entry', 'view', self::$clone_result);
            $clone_result = self::$clone_result;
            self::$clone_result = null;
            if ($clone_result!='canceled') return;
        }
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
        else $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, $this->tab);

        if(!$pdf) $gb->set_expandable($this->expandable_rows);
        
        if($pdf) $gb->set_resizable_columns(false);
        else $gb->set_fixed_columns_class($this->fixed_columns_class);

        if ($special) {
            $gb_per_page = Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'per_page');
            $gb->set_per_page(Base_User_SettingsCommon::get(Utils_RecordBrowser_RecordPicker::module_name(),'per_page'));
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
                $fav = array('name'=>'&nbsp;', 'width'=>'24px', 'attrs'=>'class="Utils_RecordBrowser__favs"');
                if (!isset($this->force_order)) $fav['order'] = ':Fav';
                $table_columns[] = $fav;
            }
            if (!$pdf && !$admin && $this->watchdog)
                $table_columns[] = array('name'=>'', 'width'=>'24px', 'attrs'=>'class="Utils_RecordBrowser__watchdog"');
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
            if (!$pdf
                && !isset($this->force_order)
                && $this->browse_mode!='recent'
                && $args['type']!=='multiselect'
                && ($args['type']!=='calculated' || $args['param']!='')
                && $args['type']!=='hidden'
                && (!isset($args['ref_table']) || $args['ref_table'] != '__RECORDSETS__' && !preg_match('/,/',$this->tab))
            ) $arr['order'] = $field;
            if ($args['type']=='checkbox' || (($args['type']=='date' || $args['type']=='timestamp' || $args['type']=='time') && !$this->add_in_table) || $args['type']=='commondata') {
                $arr['wrapmode'] = 'nowrap';
                $arr['width'] = 50;
            } else {
                $arr['width'] = 100;
			}
            $arr['name'] = _V($arr['name']); // ****** Translate field name for table header
            if (isset($this->more_table_properties[$args['id']])) {
                foreach (array('name','wrapmode','width','display','order') as $v) if (isset($this->more_table_properties[$args['id']][$v])) {
                    if (is_numeric($this->more_table_properties[$args['id']][$v]) && $v=='width') $this->more_table_properties[$args['id']][$v] = $this->more_table_properties[$args['id']][$v]*10;
                    $arr[$v] = $this->more_table_properties[$args['id']][$v];
                }
            }
            $each = array();
            if (!$pdf && $quickjump!=='' && $args['name']===$quickjump) $each[] = 'quickjump';
            if (!$pdf && !$this->disabled['search']) $each[] = 'search';
            foreach ($each as $e) {
                if ($args['type']=='text' || $args['type']=='currency' || $args['type'] == 'autonumber' || $args['type'] == 'date' || ($args['type']=='calculated' && preg_match('/^[a-z]+(\([0-9]+\))?$/i',$args['param'])!==0)) $arr[$e] = $args['id'];
                if ($args['type'] == 'long text' && $gb->is_adv_search_on()) $arr[$e] = $args['id'];
                if ($args['type'] == 'date') $arr['search_type'] = 'datepicker';
                if (isset($args['ref_field']) && $args['ref_field']) $arr[$e] = $args['id'];
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
		if (empty($table_columns)) {
			print('Invalid view, no fields to display');
			return;
		}

		$gb->set_table_columns( $table_columns );
		
		if (!$pdf) {
			$clean_order = array();
			foreach ($order as $k => $v) {
                if ($k[0] == ':') {
                    $clean_order[$k] = $v;
                    continue;
                }
				if(!in_array($k,$query_cols)) continue;
				if (isset($this->more_table_properties[$k]) && isset($this->more_table_properties[$k]['name'])) {
                    $key = $this->more_table_properties[$k]['name'];
                } else {
                    $key = isset($hash[$k]) ? $hash[$k] : $k;
                    $key = _V($this->table_rows[$key]['name']);
                }
   				$clean_order[$key] = $v;
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
				elseif (isset($args['ref_field']) && $args['ref_field']) $k = $k.'['.Utils_RecordBrowserCommon::get_field_id($args['ref_field']).']';
                if ($k[0]=='"') { // quickjump case
                    $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, array('~' . $k => $v));
                    continue;
                }
                if (is_array($v)) $v = $v[0];
                $v = explode(' ', $v);
                foreach ($v as $w) {
                    if ($w === '') continue;
                    $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, array('~'.$k =>"%$w%"));
				}
            }
        } else {
            // New experimental search using search index!
            /*
            $isearch = $gb->get_module_variable('search');
            $keyword = isset($isearch['__keyword__']) ? $isearch['__keyword__'] : '';
            if ($keyword) {
//             TODO: use all indexed columns to search
                $search_cols = array_column($table_columns, 'search');
                $search_result = new Utils_RecordBrowser_Search($this->tab, $search_cols);
                $search_res = $search_result->get_crits($keyword, true);
            }
            */
            $search_var = $gb->get_module_variable('search');
            $search_text = isset($search_var['__keyword__']) ? $search_var['__keyword__'] : '';
            $search_words = explode(' ', $search_text);
            foreach ($search_words as $word) {
                if ($word === '') continue;
                $search_part = new Utils_RecordBrowser_Crits();
                foreach ($search as $search_col => $search_col_val) {
                    if ($search_col[0] == '"') continue; // remove quickjump
                    $args = $this->table_rows[$hash[trim($search_col, '(|')]];
                    if ($args['commondata']) $search_col = $search_col.'[]';
                    elseif (isset($args['ref_field']) && $args['ref_field']) $search_col = $search_col.'['.Utils_RecordBrowserCommon::get_field_id($args['ref_field']).']';
                    $search_part = Utils_RecordBrowserCommon::merge_crits($search_part, array('~'.$search_col =>"%$word%"), true);
                }
                $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, $search_part);
            }
            // add quickjump
            if ($gb->get_module_variable('quickjump') && $gb->get_module_variable('quickjump_to')) {
                $search_res = Utils_RecordBrowserCommon::merge_crits($search_res, array(
                    $gb->get_module_variable('quickjump') => DB::qstr($gb->get_module_variable('quickjump_to').'%')
                ));
            }
        }

        if (!$pdf) $order = $gb->get_order();
        $crits = Utils_RecordBrowserCommon::merge_crits($crits, $search_res);
        if ($this->browse_mode == 'favorites') {
            $crits = Utils_RecordBrowserCommon::merge_crits($crits, array(':Fav' => true));
        }
        if ($this->browse_mode == 'watchdog') {
            $crits = Utils_RecordBrowserCommon::merge_crits($crits, array(':Sub' => true));
        }
        if ($this->browse_mode == 'recent') {
            $crits = Utils_RecordBrowserCommon::merge_crits($crits, array(':Recent' => true));
            $order = array(':Visited_on' => 'DESC');
        }

        if ($admin && !$pdf) {
            $order = array(':Edited_on'=>'DESC');
            $form = $this->init_module(Libs_QuickForm::module_name(), null, $this->tab.'_admin_filter');
            $form->addElement('select', 'show_records', __('Show records'), array(0=>'['.__('All').']',1=>'['.__('All active').']',2=>'['.__('All deactivated').']'), array('onchange'=>$form->get_submit_form_js()));
            $f = $this->get_module_variable('admin_filter', 0);
            $form->setDefaults(array('show_records'=>$f));
            $admin_filter = $form->exportValue('show_records');
            $this->set_module_variable('admin_filter', $admin_filter);
            switch($admin_filter) {
                case 0: Utils_RecordBrowserCommon::$admin_filter = '';
                    break;
                case 1: Utils_RecordBrowserCommon::$admin_filter = '<tab>.active=1 AND ';
                    break;
                case 2: Utils_RecordBrowserCommon::$admin_filter = '<tab>.active=0 AND ';
                    break;
            }
            $form->display_as_row();
        }
        if (isset($this->force_order)) $order = $this->force_order;
        if (!$order) $order = array();

        $this->amount_of_records = Utils_RecordBrowserCommon::get_records_count($this->tab, $crits, $admin, $order);

        if ($limit === null && !$this->disabled['pagination'])
            $limit = $gb->get_limit($this->amount_of_records);

		if (!$this->disabled['pdf'] && !$pdf && $this->get_access('print')) {
            $limited_print_records = 200;
            $limited_print = ($this->amount_of_records >= $limited_print_records);
            $print_limit = $limited_print ? $limit : null;
            $key = md5(serialize($this->tab).serialize($crits).serialize($cols).serialize($order).serialize($admin).serialize($print_limit));
            $_SESSION['client']['utils_recordbrowser'][$key] = array(
                'tab'=>$this->tab,
                'crits'=>$crits,
                'cols'=>$cols,
                'order'=>$order,
                'admin'=>$admin,
                'more_table_properties'=>$this->more_table_properties,
                'limit' => $print_limit,
            );
            $print_href = 'href="modules/Utils/RecordBrowser/print.php?'.http_build_query(array('key'=>$key, 'cid'=>CID)).'" target="_blank"';
            $print_tooltip_text = $limited_print ?
                __('Due to more than %d records, you are allowed to print current view', array($limited_print_records)) :
                __('Print all records');
            $print_tooltip = Utils_TooltipCommon::open_tag_attrs($print_tooltip_text, false);
            $this->new_button('print', __('Print'), "$print_href $print_tooltip");
	}
         
        $records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, array(), $order, $limit, $admin);
        if(!$records) {
            $last_offset = $this->get_module_variable('last_offset');
            while(!$records) {
                if($last_offset>$limit['offset'] && ($limit['offset']-$limit['numrows'])>=0)
                    $limit['offset'] -= $limit['numrows'];
                elseif(($limit['offset']+$limit['numrows'])<$this->amount_of_records)
                    $limit['offset'] += $limit['numrows'];
                else break;
                $gb->set_module_variable('offset',$limit['offset']);
                $limit = $gb->get_limit($this->amount_of_records);
                $records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, array(), $order, $limit, $admin);
            }
        }
        $this->set_module_variable('last_offset',$limit['offset']);

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

        $grid_enabled = $this->grid===null?Base_User_SettingsCommon::get(Utils_RecordBrowser::module_name(),'grid'):$this->grid;
        if ($grid_enabled) load_js('modules/Utils/RecordBrowser/grid.js');

        $this->view_fields_permission = $this->get_access('add', $this->custom_defaults);
        if (!$pdf && !$special && $this->add_in_table && $this->view_fields_permission) {
            $form = $this->init_module(Libs_QuickForm::module_name(),null, 'add_in_table__'.$this->tab);
            $form_name = $form->get_name();
        } else $form_name = '';

        $column_access = array_fill(0, count($query_cols), false);
       	if (!$records) {
        	$record_access_fields = $this->get_access('view');
        	if (is_array($record_access_fields)) {
        		$column_access = array_keys(array_merge(array_flip($query_cols), $record_access_fields));
        	}
        	elseif ($record_access_fields === true) {
        		$column_access = array_fill(0, count($query_cols), true);
        	}        	
        }

        $data_rows_offset = 0;
        foreach ($records as $row) {
            if ($this->browse_mode!='recent' && isset($limit)) {
                self::$browsed_records['records'][$row['id']] = $i;
                $i++;
            }
            $row = Utils_RecordBrowserCommon::record_processing($this->tab, $row, 'browse');
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
                $row_id = $this->include_tab_in_id? $this->tab . '/' . $row['id']: $row['id'];
                $formated_name = Utils_RecordBrowserCommon::create_default_linked_label($this->tab, $row['id'], true);
                $formated_name = htmlspecialchars(strip_tags($formated_name));
                $row_data = array('<input type="checkbox" id="leightbox_rpicker_' . $element . '_' . $row_id . '" formated_name="' . $formated_name . '" />');
                $rpicker_ind[] = $row_id;
            }
            $r_access = $this->get_access('view', $row);
            $data_rows_offset = count($row_data);
            foreach($query_cols as $k=>$argsid) {
				if (!$r_access || !$r_access[$argsid]) {
					$row_data[] = '';
					continue;
				}
				$column_access[$k] = true;

                $field = $hash[$argsid];
                $args = $this->table_rows[$field];
                $value = $this->get_val($field, $row, ($special || $pdf), $args);
                if (strip_tags($value)=='') $value .= '&nbsp;';
                if ($args['style']=='currency' || $args['style']=='number') $value = array('style'=>'text-align:right;','value'=>$value);
                if ($grid_enabled && !in_array($args['type'], array('calculated','multiselect','commondata'))) {
                    $table = '<table class="Utils_RecordBrowser__grid_table" style="width:100%" cellpadding="0" cellspacing="0" border="0"><tr><td id="grid_form_field_'.$argsid.'_'.$row['id'].'" style="display:none;">Loading...</td><td id="grid_value_field_'.$argsid.'_'.$row['id'].'">';
                    $ed_icon = '</td><td style="min-width:18px;width:18px;padding:0px;margin:0px;">'.
                                '<span id="grid_edit_'.$argsid.'_'.$row['id'].'" style="float:right;display:none;"><a href="javascript:void(0);" onclick="grid_enable_field_edit(\''.$argsid.'\','.$row['id'].',\''.$this->tab.'\',\''.$form_name.'\');"><img border="0" src="'.Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'edit.png').'"></a></span>'.
                                '<span id="grid_save_'.$argsid.'_'.$row['id'].'" style="float:right;display:none;"><a href="javascript:void(0);" onclick="grid_submit_field(\''.$argsid.'\','.$row['id'].',\''.$this->tab.'\');"><img border="0" src="'.Base_ThemeCommon::get_template_file(Utils_RecordBrowser::module_name(), 'save_grid.png').'"></a></span>'.
                                '</td></tr></table>';

/*                  $table = '<span id="grid_form_field_'.$argsid.'_'.$row['id'].'" style="display:none;">Loading...</span><span id="grid_value_field_'.$argsid.'_'.$row['id'].'">';
                    $ed_icon = '</span>'.
                                '<span id="grid_edit_'.$argsid.'_'.$row['id'].'" style="float:right;display:none;"><a href="javascript:void(0);" onclick="grid_enable_field_edit(\''.$argsid.'\','.$row['id'].',\''.$this->tab.'\',\''.$form_name.'\');"><img border="0" src="'.Base_ThemeCommon::get_template_file(Utils_GenericBrowser::getName(), 'edit.png').'"></a></span>'.
                                '<span id="grid_save_'.$argsid.'_'.$row['id'].'" style="float:right;display:none;"><a href="javascript:void(0);" onclick="grid_submit_field(\''.$argsid.'\','.$row['id'].',\''.$this->tab.'\');"><img border="0" src="'.Base_ThemeCommon::get_template_file(Utils_RecordBrowser::getName(), 'save_grid.png').'"></a></span>';*/


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
                    $value['overflow_box'] = false;
					$value['attrs'] = $attrs.' style="border:1px solid black;"';
					$value['value'] = '&nbsp;'.$value['value'].'&nbsp;';
				}
                $row_data[] = $value;
            }

            $gb_row->add_data_array($row_data);
            if (!$pdf && $this->disabled['actions']!==true) {
                if ($this->disabled['actions']===false) $da = array();
                else $da = array_flip($this->disabled['actions']);
                if (!$special) {
                    if (!isset($da['view'])) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'view', $row['id'])),__('View'), null, 'view');
					if (!isset($da['edit'])) {
						if ($this->get_access('edit',$row)) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'edit',$row['id'])),__('Edit'), null, 'edit');
						else $gb_row->add_action('',__('Edit'),__('You don\'t have permission to edit this record.'),'edit',0,true);
					}
                    if ($admin) {
                        if (!$row[':active']) $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],true)),__('Activate'), null, 'active-off');
                        else $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],false)),__('Deactivate'), null, 'active-on');
                        $info = Utils_RecordBrowserCommon::get_record_info($this->tab, $row['id']);
                        if ($info['edited_on']===null) $gb_row->add_action('',__('This record was never edited'),null,'history_inactive');
                        else $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_edit_history', $row['id'])),__('View edit history'),null,'history');
                    } else {
						if (!isset($da['delete'])) {
                            if ($this->get_access('delete',$row)) $gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this record?'),array($this,'delete_record'),array($row['id'], false)),__('Delete'), null, 'delete');
                            else $gb_row->add_action('',__('Delete'),__('You don\'t have permission to delete this record'),'delete',0,true);
                        }
					}
                }
                if (!isset($da['info'])) $gb_row->add_info(($this->browse_mode=='recent'?'<b>'.__('Visited on: %s', array($row['visited_on'])).'</b><br>':'').Utils_RecordBrowserCommon::get_html_record_info($this->tab, isset($info)?$info:$row['id']));
                $this->call_additional_actions_methods($row, $gb_row);
            }
        }
        if (!$special && $this->add_in_table && $this->view_fields_permission) {

            $visible_cols = array();
            foreach($this->table_rows as $field => $args){
                if ((!$args['visible'] && (!isset($cols[$args['id']]) || $cols[$args['id']] === false))) continue;
                if (isset($cols[$args['id']]) && $cols[$args['id']] === false) continue;
                $visible_cols[$args['id']] = true;
            }

			self::$last_record = $this->record = $this->custom_defaults = Utils_RecordBrowserCommon::record_processing($this->tab, $this->custom_defaults, 'adding');

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
            Base_User_SettingsCommon::save(Utils_RecordBrowser_RecordPicker::module_name(),'per_page',$gb->get_module_variable('per_page'));
            Base_User_SettingsCommon::save(Utils_GenericBrowser::module_name(),'per_page',$gb_per_page);
            return $ret;
        }
		if ($pdf) {
			$gb->absolute_width(true);
			$args = array(Base_ThemeCommon::get_template_filename('Utils_GenericBrowser','pdf'));
		} else $args = array();
        if(!$this->add_in_table) {
            foreach ($column_access as $k => $access) {
                if (!$access) {
                    $gb->set_column_display($k + $data_rows_offset, false);
                }
            }
        }
		$this->display_module($gb, $args);
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function delete_record($id, $pop_main = true) {
        Utils_RecordBrowserCommon::delete_record($this->tab, $id);
        if ($pop_main) {
            return $this->back();
        }
    }
    public function clone_record($id) {
        if (self::$clone_result!==null) {
            if (is_numeric(self::$clone_result)) {
                Utils_RecordBrowserCommon::record_processing($this->tab, self::$clone_result, 'cloned', $id);
                Utils_RecordBrowserCommon::new_record_history($this->tab,self::$clone_result,'CLONED '.$id);
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
        if(isset($_REQUEST['switch_to_addon'])) {
	        $this->switch_to_addon = $this->get_module_variable('switch_to_addon',$_REQUEST['switch_to_addon']);
	        unset($_REQUEST['switch_to_addon']);
        }
        return $this->view_entry($mode, $id, $defaults, $show_actions);
    }
    public function view_entry($mode='view', $id = null, $defaults = array(), $show_actions=true) {
		Base_HelpCommon::screen_name('rb_'.$mode.'_'.$this->tab);
        if (isset($_SESSION['client']['recordbrowser']['admin_access'])) Utils_RecordBrowserCommon::$admin_access = true;
        self::$mode = $mode;
        if ($this->navigation_executed) {
            $this->navigation_executed = false;
            return true;
        }
        if ($this->check_for_jump()) return;
        $theme = $this->init_module(Base_Theme::module_name());
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
			$id = isset($this->record['id'])? intVal($this->record['id']): null;
		}
		if ($id===0) $id = null;
        if ($id!==null && is_numeric($id)) Utils_WatchdogCommon::notified($this->tab,$id);

        if($mode=='add') {
            foreach ($defaults as $k=>$v)
                $this->custom_defaults[$k] = $v;
            foreach($this->table_rows as $field => $desc)
                if (!isset($this->custom_defaults[$desc['id']]))
					$this->custom_defaults[$desc['id']] = $desc['type'] == 'multiselect' ? array() : '';
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
                    //Utils_ShortcutCommon::add(array('esc'), 'function(){'.$this->create_back_href_js().'}');
                }
                return true;
            }
        }
        if ($mode=='add' && (!$access || !$this->view_fields_permission)) {
            $msg = !$access ?
                __('You don\'t have permission to perform this action.')
                : __('You can\'t see any of the records fields.');
            print($msg);
			if ($show_actions===true || (is_array($show_actions) && (!isset($show_actions['back']) || $show_actions['back']))) {
				Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
				//Utils_ShortcutCommon::add(array('esc'), 'function(){'.$this->create_back_href_js().'}');
			}
			return true;
		}

        if($mode == 'add' || $mode == 'edit') {
            $theme -> assign('click2fill', '<div id="c2fBox"></div>');
            load_js('modules/Utils/RecordBrowser/click2fill.js');
            eval_js('initc2f("'.__('Scan/Edit').'","'.__('Paste data here with Ctrl-v, click button below, then click on separated words in specific order and click in text field where you want put those words. They will replace text in that field.').'")');
            Base_ActionBarCommon::add('clone', __('Click 2 Fill'), 'href="javascript:void(0)" onclick="c2f()"');
        }

//        if ($mode!='add' && !$this->record[':active'] && !Base_AclCommon::i_am_admin()) return $this->back();

        $tb = $this->init_module(Utils_TabbedBrowser::module_name(), null, 'recordbrowser_addons/'.$this->tab.'/'.$id);
		if ($mode=='history') $tb->set_inline_display();
        self::$tab_param = $tb->get_path();

        $form = $this->init_module(Libs_QuickForm::module_name(),null, $mode.'/'.$this->tab.'/'.$id);
        if(Base_User_SettingsCommon::get($this->get_type(), 'confirm_leave') && ($mode == 'add' || $mode == 'edit'))
        	$form->set_confirm_leave_page();
        
        $this->form = $form;

        if($mode!='add')
            Utils_RecordBrowserCommon::add_recent_entry($this->tab, Acl::get_user(),$id);

		$dp = Utils_RecordBrowserCommon::record_processing($this->tab, $mode!='add'?$this->record:$this->custom_defaults, ($mode=='view' || $mode=='history')?'view':$mode.'ing');
		if($dp===false) return false;
		if (is_array($dp))
			$defaults = $this->custom_defaults = self::$last_record = $this->record = $dp;

        if (self::$last_record===null) self::$last_record = $defaults;
        if($mode=='add')
            $form->setDefaults($defaults);

        switch ($mode) {
            case 'add':     $this->action = _M('New record'); break;
            case 'edit':    $this->action = _M('Edit record'); break;
            case 'view':    $this->action = _M('View record'); break;
            case 'history':    $this->action = _M('Record history view'); break;
        }

        $this->prepare_view_entry_details($this->record, $mode=='history'?'view':$mode, $id, $form);

        if ($mode==='edit' || $mode==='add')
            foreach($this->table_rows as $desc) {
                if (!$access[$desc['id']])
                    $form->freeze($desc['id']);
            }
        if ($form->exportValue('submited') && $form->validate()) {
        	$values = array_merge($form->exportValues(), Utils_FileUpload_Dropzone::export_values($form));

			foreach ($defaults as $k=>$v) {
				if (!isset($values[$k]) && ($this->view_fields_permission === false
                        || (isset($this->view_fields_permission[$k]) && !$this->view_fields_permission[$k]))) $values[$k] = $v;
				if (isset($access[$k]) && !$access[$k]) $values[$k] = $v;
			}
			foreach ($this->table_rows as $desc) {
				if ($desc['type']=='checkbox' && !isset($values[$desc['id']])) $values[$desc['id']]=0;
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
                if($this->get_access('print',$this->record)) {
                    /** @var Base_Print_Printer $printer */
                    $printer = Utils_RecordBrowserCommon::get_printer($this->tab);
                    if ($printer) {
                        Base_ActionBarCommon::add('print', __('Print'), $printer->get_href(array('tab' => $this->tab, 'record_id' => $this->record['id'])));
                    }
                }
                if ($show_actions===true || (is_array($show_actions) && (!isset($show_actions['back']) || $show_actions['back'])))
                    Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
            } elseif($mode!='history') {
                Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
                Base_ActionBarCommon::add('delete', __('Cancel'), $this->create_back_href());
            }
            //Utils_ShortcutCommon::add(array('esc'), 'function(){'.$this->create_back_href_js().'}');
        }

        if ($mode!='add') {
            $theme -> assign('info_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(Utils_RecordBrowserCommon::get_html_record_info($this->tab, $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'" /></a>');

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
					$record = Utils_RecordBrowserCommon::get_record($this->tab, $id);
					/* for every field name store its value */
					$data = Utils_RecordBrowserCommon::get_record_vals($this->tab, $record, true, array_column($this->table_rows, 'id'));

					$text = Utils_RecordBrowserCommon::replace_clipboard_pattern($this->clipboard_pattern, array_filter($data));
					
					load_js($this->get_module_dir() . 'selecttext.js');
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
        $tab_counter = 0;
		$additional_tabs = 0;
		$default_tab = 0;
        while ($row) {
            $row = $ret->FetchRow();
            if ($row) $pos = $row['position'];
            else $pos = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field WHERE active=1')+1;

            $valid_page = false;
			$hide_page = ($mode=='view' && Base_User_SettingsCommon::get(Utils_RecordBrowser::module_name(),'hide_empty'));
            foreach($this->table_rows as $desc) {
                if (!isset($data[$desc['id']]) || $data[$desc['id']]['type']=='hidden') continue;
                if ($desc['position'] >= $last_page && ($pos+1 == -1 || $desc['position'] < $pos+1)) {
                    $valid_page = true;
					if ($hide_page && !$this->field_is_empty($this->record, $desc['id'])) $hide_page = false;
                    break;
                }
            }
            if ($valid_page && $pos - $last_page>1 && !isset($this->hide_tab[$label])) {
                $translated_label = _V($label);
                $tb->set_tab($translated_label, array($this, 'view_entry_details'), array($last_page, $pos + 1, $data, null, false, $cols, _V($label)), $js); // TRSL
				if ($hide_page) {
					eval_js('$("'.$tb->get_tab_id(_V($label)).'").style.display="none";');
					if ($default_tab === $tab_counter) $default_tab = $tab_counter + 1;
				} else
					$additional_tabs++;

				$tab_counter++;
			}
            $cols = $row['param'];
            $last_page = $pos;
            if ($row) $label = $row['field'];
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
                    if (!isset($result['icon'])) $result['icon']='';
                    $row['icon'] = $result['icon'];
                } else {
					if ($mode=='add' || $mode=='edit') continue;
					$labels = explode('#',$row['label']);
					foreach($labels as $i=>$label) $labels[$i] = _V($label); // translate labels from database
					$row['label'] = implode('#',$labels);
				}
                $mod_id = md5(serialize($row));
				if (method_exists($row['module'].'Common',$row['func'].'_access') && !call_user_func(array($row['module'].'Common',$row['func'].'_access'), $this->record, $this)) continue;
                $addons_mod[$mod_id] = $this->init_module($row['module']);
                if (!method_exists($addons_mod[$mod_id],$row['func'])) $tb->set_tab($row['label'],array($this, 'broken_addon'), array(), $js);
                else {
                	$tb->set_tab($row['label'],array($this, 'display_module'), array(& $addons_mod[$mod_id], array($this->record, $this), $row['func']), $js);
                	if (isset($row['icon']) && $row['icon']) $tb->tab_icon($row['label'], $row['icon']);
                }                
                $tab_counter++;
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
            $tb->switch_tab($this->switch_to_addon);
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

    public function view_entry_details($from, $to, $form_data, $theme=null, $main_page = false, $cols = 2, $tab_label = null){
        if ($theme==null) $theme = $this->init_module(Base_Theme::module_name());
        $fields = array();
        $longfields = array();

        foreach($this->table_rows as $desc) {
            if (!isset($form_data[$desc['id']]) || $form_data[$desc['id']]['type']=='hidden') continue;
            if ($desc['position'] >= $from && ($to == -1 || $desc['position'] < $to)) {
				if ($tab_label) $this->fields_in_tabs[$tab_label][] = $desc['id'];
                
				$opts = $this->get_field_display_options($desc, $form_data);
				
				if (!$opts) continue;
				
                if ($desc['type']<>'long text') $fields[$desc['id']] = $opts; else $longfields[$desc['id']] = $opts;
            }
        }
        if ($cols==0) $cols=2;
        $theme->assign('fields', $fields);
        $theme->assign('cols', $cols);
        $theme->assign('longfields', $longfields);
        $theme->assign('action', self::$mode=='history'?'view':self::$mode);
        $theme->assign('form_data', $form_data);
        $theme->assign('required_note', __('Indicates required fields.'));

        $theme->assign('caption',_V($this->caption) . $this->get_jump_to_id_button());
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
    
    public function get_field_display_options($desc, $form_data = array()) {
    	/** @var Base_Theme $ftheme */
    	static $ftheme;
    	
    	$field_form_data = isset($form_data[$desc['id']])? $form_data[$desc['id']]: array();

    	$default_field_form_data = array('label'=>'', 'html'=>'', 'error'=>null, 'frozen'=>false);
    	$field_form_data = array_merge($default_field_form_data, $field_form_data);
    	
    	$help = isset($desc['help']) && $desc['help']? array(
    			'icon' => Base_ThemeCommon::get_icon('info'), 
    			'text' => Utils_TooltipCommon::open_tag_attrs(_V($desc['help']), false))
    		: false;
    	
    	$ret = array('label'=>$field_form_data['label'],
    			'element'=>$desc['id'],
    			'advanced'=>isset($this->advanced[$desc['id']])?$this->advanced[$desc['id']]:'',
    			'html'=>$field_form_data['html'],
    			'style'=>$desc['style'].($field_form_data['frozen']?' frozen':''),
    			'error'=>$field_form_data['error'],
    			'required'=>isset($desc['required'])? $desc['required']: null,
    			'type'=>$desc['type'],
    			'help' => $help);
    	
    	if (!$ftheme)
    		$ftheme = $this->init_module(Base_Theme::module_name());

    	$ftheme->assign('f', $ret);
    	$ftheme->assign('form_data', $form_data);
    	$ftheme->assign('action', self::$mode);
    	
    	$default_field_template = self::module_name() . '/single_field';
    	
    	$field_template = $desc['template']?: $default_field_template;    	
    	$field_template = is_callable($field_template)? call_user_func($field_template, $desc['id'], self::$mode): $field_template;
    	
    	if (!$field_template) return false;
    	
    	$ret['full_field'] = $ftheme->get_html($field_template, true);

    	return $ret;
    }

    public function check_new_record_access($data) {
		$ret = array();
        if (is_array(Utils_RecordBrowser::$last_record))
		    foreach (Utils_RecordBrowser::$last_record as $k=>$v) if (!isset($data[$k])) $data[$k] = $v;
		$access = Utils_RecordBrowser_Access::create($this->tab,'add');
		if ($access->isFullGrant()) return [];
		if ($access->isFullDeny()) {
			$fields = array_keys($data);
			$first_field = reset($fields);
			return array($first_field=>__('Access denied'));
		}
        $required_crits = array();
		foreach($access->getRuleCrits() as $crits) {
		    $problems = array();
            if (!Utils_RecordBrowserCommon::check_record_against_crits($this->tab, $data, $crits, $problems)) {
                foreach ($problems as $c) {
                    if ($c instanceof Utils_RecordBrowser_CritsSingle) {
                        list($f, $subf) = Utils_RecordBrowser_CritsSingle::parse_subfield($c->get_field());
                        $ret[$f] = __('Invalid value');
                    }
                }
                $required_crits[] = Utils_RecordBrowserCommon::crits_to_words($this->tab, $crits);
            }
            if($problems) continue;
            return array();
	   	}
    	if (!$required_crits) return array();
    	
        /** @var Base_Theme $th */
        $th = $this->init_module(Base_Theme::module_name());
        $th->assign('crits', $required_crits);
        $th->display('required_crits_to_add');
		return $ret;
    }
    private static function sort_by_processing_order($f1, $f2)
    {
        return $f1['processing_order'] > $f2['processing_order'];
    }
    public function prepare_view_entry_details($record, $mode, $id, $form, $visible_cols = null, $for_grid=false){
	if ($mode == 'add')
	    $form->addFormRule(array($this, 'check_new_record_access'));
        $fields_by_processing_order = $this->table_rows;
        uasort($fields_by_processing_order, array(__CLASS__, 'sort_by_processing_order'));
        foreach($fields_by_processing_order as $field => $desc){
            // check permissions
            if ($this->view_fields_permission === false ||
                (isset($this->view_fields_permission[$desc['id']])
                 && !$this->view_fields_permission[$desc['id']])) continue;
            // check visible cols
            if ($visible_cols!==null && !isset($visible_cols[$desc['id']])) continue;
            // set default value to '' if not set at all
            if (!isset($record[$desc['id']])) $record[$desc['id']] = '';
            if ($for_grid) {
                $nk = '__grid_'.$desc['id'];
                $record[$nk] = $record[$desc['id']];
                $desc['id'] = $nk;
            }
            if ($desc['type']=='hidden') {
                $form->addElement('hidden', $desc['id']);
                $form->setDefaults(array($desc['id']=>$record[$desc['id']]));
                continue;
            }
            // is set then hide empty fields that are not checkboxes
			if ($mode == 'view' && $desc['type'] != 'checkbox' && Base_User_SettingsCommon::get(Utils_RecordBrowser::module_name(),'hide_empty') && $this->field_is_empty($record, $desc['id'])) {
				eval_js('var e=$("_'.$desc['id'].'__data");if(e)e.up("tr").style.display="none";');
			}
            // translate label and put it into span with id
            $label = '<span id="_'.$desc['id'].'__label">'._V($desc['name']).'</span>'; // TRSL
            if (isset($this->QFfield_callback_table[$field])) {
				//$label = Utils_RecordBrowserCommon::get_field_tooltip($label, $args['type']);
                $ff = $this->QFfield_callback_table[$field];
            } else {
                $ff = Utils_RecordBrowserCommon::get_default_QFfield_callback($desc['type']);
            }

            Utils_RecordBrowserCommon::call_QFfield_callback($ff,$form, $desc['id'], $label, $mode, $mode=='add'?(isset($this->custom_defaults[$desc['id']])?$this->custom_defaults[$desc['id']]:''):$record[$desc['id']], $desc, $this, $this->display_callback_table);

            if ($desc['required']) {
                $el = $form->getElement($desc['id']);
                if (!$form->isError($el)) {
                    if ($el->getType() != 'static') {
                        $form->addRule($desc['id'], __('Field required'), 'required');
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
        $tb = $this->init_module(Utils_TabbedBrowser::module_name());
		
		$tabs = array(
		array(
			'access'=>'fields',
			'func'=>array($this, 'setup_loader'),
			'label'=>__('Manage Fields'),
			'args'=>array()
		),
        array(
            'access'=>'records',
            'func'=>array($this, 'show_data'),
            'label'=>__('Manage Records'),
            'args'=>array(array(), array(), array(), Base_AdminCommon::get_access('Utils_RecordBrowser', 'records')==2)
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
			'access'=>'settings',
			'func'=>array($this, 'settings'),
			'label'=>__('Settings'),
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
    
    public function settings() {
        $full_access = Base_AdminCommon::get_access('Utils_RecordBrowser', 'settings')==2;
        
        $form = $this->init_module(Libs_QuickForm::module_name());
        $r = DB::GetRow('SELECT caption,description_fields,favorites,recent,full_history,jump_to_id,search_include,search_priority FROM recordbrowser_table_properties WHERE tab=%s',array($this->tab));
        $form->addElement('text', 'caption', __('Caption'));
        $callback = Utils_RecordBrowserCommon::get_description_callback($this->tab);
        if ($callback) {
            echo '<div style="color:red; padding: 1em;">' . __('Description Fields take precedence over callback. Leave them empty to use callback') . '</div>';
            $form->addElement('static', '', __('Description Callback'), implode('::', $callback))->freeze();
        }
        $form->addElement('text', 'description_fields', __('Description Fields'), array('placeholder' => __('Comma separated list of field names')));
        $form->addElement('select', 'favorites', __('Favorites'), array(__('No'), __('Yes')));
        $recent_values = array(0 => __('No'));
        foreach (array(5, 10, 15, 20, 25) as $rv) { $recent_values[$rv] = "$rv " . __('Records') ; }
        $form->addElement('select', 'recent', __('Recent'), $recent_values);
        $form->addElement('select', 'full_history', __('History'), array(__('No'), __('Yes')));
        $form->addElement('select', 'jump_to_id', __('Jump to ID'), array(__('No'), __('Yes')));
        $form->addElement('select', 'search_include', __('Search'), array(__('Exclude'), __('Include by default'), __('Include optional')));
        $form->addElement('select', 'search_priority', __('Search priority'), array(-2=>__('Lowest'),-1=>__('Low'), 0=>__('Default'), 1=>__('High'), 2=>__('Highest')));
        
	if ($full_access) {
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
	} else {
		$form->freeze();
	}
        if($r) $form->setDefaults($r);
        $form->display_as_column();
        if ($full_access) {
            $clear_index_href = $this->create_confirm_callback_href(__('Are you sure?'), array($this, 'clear_search_index'), array($this->tab));
            echo "<a $clear_index_href>" . __('Clear search index') . "</a>";
            if ($form->validate()) {
                DB::Execute('UPDATE recordbrowser_table_properties SET caption=%s,description_fields=%s,favorites=%b,recent=%d,full_history=%b,jump_to_id=%b,search_include=%d,search_priority=%d WHERE tab=%s',
                            array($form->exportValue('caption'), $form->exportValue('description_fields'), $form->exportValue('favorites'), $form->exportValue('recent'), $form->exportValue('full_history'), $form->exportValue('jump_to_id'), $form->exportValue('search_include'), $form->exportValue('search_priority'), $this->tab));
            }
        }
    }

    public function clear_search_index($tab)
    {
        $ret = Utils_RecordBrowserCommon::clear_search_index($tab);
        if ($ret) {
            Base_StatusBarCommon::message(__('Index cleared for this table. Indexing again - it may take some time.'));
        }
    }

    public function manage_addons() {
		$full_access = Base_AdminCommon::get_access('Utils_RecordBrowser', 'addons')==2;

        $gb = $this->init_module(Utils_GenericBrowser::module_name(),'manage_addons'.$this->tab, 'manage_addons'.$this->tab);
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
        $max_p = DB::GetOne('SELECT MAX(processing_order) FROM '.$this->tab.'_field');
        $num = 1;
        do {
            $num++;
            $x = DB::GetOne('SELECT position FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field = %s', array('Details '.$num));
        } while ($x!==false && $x!==null);
        DB::Execute('INSERT INTO '.$this->tab.'_field (field, type, extra, position, processing_order) VALUES(%s, \'page_split\', 1, %d, %d)', array('Details '.$num, $max_f+1, $max_p+1));
        DB::CompleteTrans();
    }
    public function delete_page($id) {
        DB::StartTrans();
        $p = DB::GetOne('SELECT position FROM '.$this->tab.'_field WHERE field=%s', array($id));
        $po = DB::GetOne('SELECT processing_order FROM '.$this->tab.'_field WHERE field=%s', array($id));
        DB::Execute('UPDATE '.$this->tab.'_field SET position = position-1 WHERE position > %d', array($p));
        DB::Execute('UPDATE '.$this->tab.'_field SET processing_order = processing_order-1 WHERE processing_order > %d', array($po));
        DB::Execute('DELETE FROM '.$this->tab.'_field WHERE field=%s', array($id));
        DB::CompleteTrans();
    }
    public function edit_page($id) {
        if ($this->is_back())
            return false;
        $this->init();
        $form = $this->init_module(Libs_QuickForm::module_name(), null, 'edit_page');

        $form->addElement('header', null, __('Edit page properties'));
        $form->addElement('text', 'label', __('Label'));
        $this->current_field = $id;
        $form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', $this);
        $form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', $this);
        $form->addRule('label', __('Field required'), 'required');
        $form->addRule('label', __('Field or Page with this name already exists.'), 'check_if_column_exists');
        $form->addRule('label', __('Only letters, numbers and space are allowed.'), 'regex', '/^[a-zA-Z ]*$/');
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
        $form = $this->init_module(Libs_QuickForm::module_name());
        $r = Utils_RecordBrowserCommon::get_clipboard_pattern($this->tab, true);
        $form->addElement('select', 'enable', __('Enable'), array(__('No'), __('Yes')));
        $info = '<b>'.__('This is an html pattern. All html tags are allowed.').'<br/>'.__('Use &lt;pre&gt; some text &lt;/pre&gt; to generate text identical as you typed it.').'<br/><br/>'.__('Conditional use:').'<br/>'.__('%%{lorem {keyword} ipsum {keyword2}}').'<br/>'.__('lorem ipsum will be shown only when at least one of keywords has a value. Nested conditions are allowed.').'<br/><br/>'.__('Normal use:').'<br/>'.__('%%{{keyword}}').'<br/><br/>'.__('Keywords').':<br/></b>';
        foreach($this->table_rows as $name=>$desc) {
        	$info .= '<b>'.$desc['id'].'</b> - '.$name.', ';
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
        $form->display_as_column();
        if ($full_access && $form->validate()) {
            $enable = $form->exportValue('enable');
            $pattern = $form->exportValue('pattern');
            Utils_RecordBrowserCommon::set_clipboard_pattern($this->tab, $pattern, $enable, true);
        }
    }
    public function setup_loader() {
        if (isset($_REQUEST['field_pos'])) {
            list($field, $position) = $_REQUEST['field_pos'];
            // adjust position
            $position += 2;
            Utils_RecordBrowserCommon::change_field_position($this->tab, $field, $position);
        }
        $this->init(true);
        $action = $this->get_module_variable_or_unique_href_variable('setup_action', 'show');
        $subject = $this->get_module_variable_or_unique_href_variable('subject', 'regular');
		
		$full_access = Base_AdminCommon::get_access('Utils_RecordBrowser', 'fields')==2;

		if ($full_access) {
			Base_ActionBarCommon::add('add',__('New field'),$this->create_callback_href(array($this, 'view_field')));
			Base_ActionBarCommon::add('add',__('New page'),$this->create_callback_href(array($this, 'new_page')));
		}
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'fields');
        $gb->set_table_columns(array(
            array('name'=>__('Field'), 'width'=>20),
            array('name'=>__('Caption'), 'width'=>20),
            array('name'=>__('Help Message'), 'width'=>12),
            array('name'=>__('Type'), 'width'=>10),
            array('name'=>__('Table view'), 'width'=>5),
            array('name'=>__('Tooltip'), 'width'=>5),
            array('name'=>__('Required'), 'width'=>5),
            array('name'=>__('Filter'), 'width'=>5),
            array('name'=>__('Export'), 'width'=>5),
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
                if ($field != 'General') {
                    $gb_row->add_action('class="move-handle"','Move', __('Drag to change field position'), 'move-up-down');
                    $gb_row->set_attrs("field_name=\"$field\" class=\"sortable\"");
                }
			}
            switch ($args['type']) {
				case 'text':
					$args['param'] = __('Length').' '.$args['param'];
					break;
				case 'select':
				case 'multiselect':
					$reg = explode(';', $args['param']);
					$rege = explode('::', $reg[0]);
					if ($rege[0]=='__COMMON__') {
						$args['param'] = Utils_RecordBrowserCommon::decode_commondata_param((isset($rege[2]) ? $rege[2] : '') . '__' . $rege[1]);
						$args['type'] = 'multiselect';
					} elseif ($rege[0]=='__RECORDSETS__') {
						$param = __('Source').': Record Sets'.'<br/>';
						$param .= __('Crits callback').': '.(!isset($reg[1]) || $reg[1]=='::'?'---':$reg[1]);
						$args['param'] = $param;
						break;
					} else {
						$param = __('Source').': Record Set'.'<br/>';
						$param .= __('Recordset').': '.Utils_RecordBrowserCommon::get_caption($rege[0]).' ('.$rege[0].')<br/>';
						$fs = explode('|', isset($rege[1])?$rege[1]:'');
						foreach ($fs as $k=>$v)
							$fs[$k] = _V($v); // ****** RecordBrowser - field name
						$param .= __('Related field(s)').': '.(implode(', ',$fs)).'<br/>';
						$param .= __('Crits callback').': '.(!isset($reg[1]) || $reg[1]=='::'?'---':$reg[1]);
						$args['param'] = $param;
						break;
					}
				case 'commondata':
					if ($args['type']=='commondata') $args['type'] = 'select';
					$param = __('Source').': CommonData'.'<br/>';
					$param .= __('Table').': '.$args['param']['array_id'].'<br/>';
					$param .= __('Order by').': '._V(ucfirst($args['param']['order']));
					$args['param'] = $param;
					break;
                case 'time':
                case 'timestamp':
                    $interval = $args['param'] ? $args['param'] : __('Default');
                    $args['param'] = __('Minutes Interval') . ': ' . $interval;
                    break;
				default:
					$args['param'] = '';
			}
			$types = array(
				'hidden'=>__('Hidden'),
				'calculated'=>__('Calculated'),
				'currency'=>__('Currency'),
				'checkbox'=>__('Checkbox'),
				'date'=>__('Date'),
				'integer'=>__('Integer'),
				'float'=>__('Float'),
				'text'=>__('Text'),
				'long text'=>__('Long text'),
				'select'=>__('Select field'),
				'multiselect'=>__('Multiselect field'),
                'file'=>__('File')
			);
            if ($args['type'] == 'page_split')
                    $gb_row->add_data(
                        array('style'=>'background-color: #DFDFFF;', 'value'=>$field),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>$args['name']),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>__('Page Split')),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
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
                        $callback = $display_callbacbacks[$field];
                        if(preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)::([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/',$callback,$match)) {
                            if(!is_callable(array($match[1],$match[2]))) $d_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
                        } elseif(preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/',$callback,$match)) {
                            if(!is_callable($match[1])) $d_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
                        } else
                            $d_c = '<b>PHP</b>';
                        $d_c = Utils_TooltipCommon::create($d_c, $callback, false);
                    } else $d_c = '';
                    if (isset($QFfield_callbacbacks[$field])) {
                        $callback = $QFfield_callbacbacks[$field];
                        $QF_c = '<b>Yes</b>';
                        if(preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)::([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/',$callback,$match)) {
                            if(!is_callable(array($match[1],$match[2]))) $QF_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
                        } elseif(preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/',$callback,$match)) {
                            if(!is_callable($match[1])) $QF_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
                        } else
                            $QF_c = '<b>PHP</b>';
                        $QF_c = Utils_TooltipCommon::create($QF_c, $callback, false);
                    } else $QF_c = '';
                    $gb_row->add_data(
                        $field,
                        $args['name'],
                        $args['help'],
                        isset($types[$args['type']])?$types[$args['type']]:$args['type'],
                        $args['visible']?'<b>'.__('Yes').'</b>':__('No'),
                        $args['tooltip']?'<b>'.__('Yes').'</b>':__('No'),
                        $args['required']?'<b>'.__('Yes').'</b>':__('No'),
                        $args['filter']?'<b>'.__('Yes').'</b>':__('No'),
                        $args['export']?'<b>'.__('Yes').'</b>':__('No'),
                        is_array($args['param'])?serialize($args['param']):$args['param'],
						$d_c,
						$QF_c
                    );
				}
        }
        $this->display_module($gb);

        // sorting
        load_js($this->get_module_dir() . 'sort_fields.js');
        $table_md5 = md5($gb->get_path());
        eval_js("rb_admin_sort_fields_init(\"$table_md5\")");
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function set_field_active($field, $set=true) {
        DB::Execute('UPDATE '.$this->tab.'_field SET active=%d WHERE field=%s',array($set?1:0,$field));
        return false;
    } //submit_delete_field
    //////////////////////////////////////////////////////////////////////////////////////////
	private $admin_field_mode = '';
	private $admin_field_type = '';
	private $admin_field_name = '';
	private $admin_field = '';
    public function view_field($action = 'add', $field = null) {
        if (!$action) $action = 'add';
        if ($this->is_back()) return false;
        if ($this->check_for_jump()) return;
        $data_type = array(
        	null=>'---',
            'autonumber'=>__('Autonumber'),
            'currency'=>__('Currency'),
            'checkbox'=>__('Checkbox'),
            'date'=>__('Date'),
            'time' => __('Time'),
            'timestamp' => __('Timestamp'),
            'integer'=>__('Integer'),
            'float'=>__('Float'),
            'text'=>__('Text'),
            'long text'=>__('Long text'),
            'select'=>__('Select field'),
            'calculated'=>__('Calculated'),
            'file'=>__('File')
	
        );
        natcasesort($data_type);

        $form = $this->init_module(Libs_QuickForm::module_name());

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
        $form->addRule('field', __('Invalid field name.'), 'check_if_no_id');

        $form->addElement('text', 'caption', __('Caption'), array('maxlength'=>255, 'placeholder' => __('Leave empty to use default label')));

        if ($action=='edit') {
            $row = DB::GetRow('SELECT field, caption, type, visible, required, param, filter, export, tooltip, extra, position, help, template FROM '.$this->tab.'_field WHERE field=%s',array($field));
			switch ($row['type']) {
				case 'select':
				case 'multiselect':
					$row['select_data_type'] = 'select';
					$row['select_type'] = $row['type'];
					$param = Utils_RecordBrowserCommon::decode_select_param($row['param']);
					if ($param['single_tab']=='__COMMON__') {
						$row['data_source'] = 'commondata';
						$order = $param['order'];
                        if (strlen($order) <= 1) $order = $order ? 'key' : 'value';
						$row['order_by'] = $order;
						$row['commondata_table'] = $param['array_id'];
					} else {
                        $row['label_field'] = implode(',', $param['cols']);
						$row['data_source'] = 'rset';
						$row['rset'] = $param['select_tabs'];
					}
					break;
				case 'commondata':
					$row['select_data_type'] = 'select';
					$row['select_type'] = 'select';
					$row['data_source'] = 'commondata';
					$param = Utils_RecordBrowserCommon::decode_commondata_param($row['param']);
					$form->setDefaults(array('order_by'=>$param['order'], 'commondata_table'=>$param['array_id']));
					break;
                case 'autonumber':
                    $row['select_data_type'] = 'autonumber';
                    Utils_RecordBrowserCommon::decode_autonumber_param($row['param'], $autonumber_prefix, $autonumber_pad_length, $autonumber_pad_mask);
                    $row['autonumber_prefix'] = $autonumber_prefix;
                    $row['autonumber_pad_length'] = $autonumber_pad_length;
                    $row['autonumber_pad_mask'] = $autonumber_pad_mask;
                    break;
				case 'text':
                    $row['select_data_type'] = $row['type'];
					$row['text_length'] = $row['param'];
                    break;
                case 'time':
                case 'timestamp':
                    $row['select_data_type'] = $row['type'];
                    $row['minute_increment'] = $row['param'];
                    break;
				default:
					$row['select_data_type'] = $row['type'];
					if (!isset($data_type[$row['type']]))
						$data_type[$row['type']] = _V(ucfirst($row['type'])); // ****** - field type
			}
			if (!isset($row['rset'])) $row['rset'] = array('contact');
			if (!isset($row['data_source'])) $row['data_source'] = 'commondata';
            $form->setDefaults($row);
            $selected_data = $row['type'];
			$this->admin_field_type = $row['select_data_type'];
			$this->admin_field = $row;
        } else {
            $selected_data = $form->exportValue('select_data_type');
            $form->setDefaults(array('visible'=>1,
                'autonumber_prefix'=>'#',
                'autonumber_pad_length'=>'6',
                'autonumber_pad_mask'=>'0'));
        }
		$this->admin_field_mode = $action;
		$this->admin_field_name = $field;
		
		$form->addElement('select', 'select_data_type', __('Data Type'), $data_type, array('id'=>'select_data_type'));

		$form->addElement('text', 'text_length', __('Maximum Length'), array('id'=>'length'));
        $minute_increment_values = array(1=>1,2=>2,5=>5,10=>10,15=>15,20=>20,30=>30,60=>__('Full hours'));
		$form->addElement('select', 'minute_increment', __('Minutes Interval'), $minute_increment_values, array('id'=>'minute_increment'));

		$form->addElement('select', 'data_source', __('Source of Data'), array('rset'=>__('Recordset'), 'commondata'=>__('CommonData')), array('id'=>'data_source'));
		$form->addElement('select', 'select_type', __('Type'), array('select'=>__('Single value selection'), 'multiselect'=>__('Multiple values selection')), array('id'=>'select_type'));
		$form->addElement('select', 'order_by', __('Order by'), array('key'=>__('Key'), 'value'=>__('Value'), 'position' => __('Position')), array('id'=>'order_by'));
		$form->addElement('text', 'commondata_table', __('CommonData table'), array('id'=>'commondata_table'));

		$tables = Utils_RecordBrowserCommon::list_installed_recordsets();
		asort($tables);
		$form->addElement('multiselect', 'rset', '<span id="rset_label">'.__('Recordset').'</span>', $tables, array('id'=>'rset'));
		$form->addElement('text', 'label_field', __('Related field(s)'), array('id'=>'label_field'));

		$form->addFormRule(array($this, 'check_field_definitions'));

		$form->addElement('checkbox', 'visible', __('Table view'));
		$form->addElement('checkbox', 'tooltip', __('Tooltip view'));
		$form->addElement('checkbox', 'required', __('Required'), null, array('id'=>'required'));
		$form->addElement('checkbox', 'filter', __('Filter enabled'), null, array('id' => 'filter'));
		$form->addElement('checkbox', 'export', __('Export'));
        
        $form->addElement('text', 'autonumber_prefix', __('Prefix string'), array('id' => 'autonumber_prefix'));
        $form->addRule('autonumber_prefix', __('Double underscore is not allowed'), 'callback', array('Utils_RecordBrowser', 'qf_rule_without_double_underscore'));
        $form->addElement('text', 'autonumber_pad_length', __('Pad length'), array('id' => 'autonumber_pad_length'));
        $form->addRule('autonumber_pad_length', __('Only integer numbers are allowed.'), 'regex', '/^[0-9]*$/');
        $form->addElement('text', 'autonumber_pad_mask', __('Pad character'), array('id' => 'autonumber_pad_mask'));
        $form->addRule('autonumber_pad_mask', __('Double underscore is not allowed'), 'callback', array('Utils_RecordBrowser', 'qf_rule_without_double_underscore'));

        $ck = $form->addElement('ckeditor', 'help', __('Help Message'));
        $ck->setFCKProps(null, null, false);

		$form->addElement('checkbox', 'advanced', __('Edit advanced properties'), null, array('id'=>'advanced'));
        $icon = '<img src="' . Base_ThemeCommon::get_icon('info') . '" alt="info">';
        $txt = 'Callback returning the template or template file to use for the field';
        $form->addElement('textarea', 'template', __('Field template') . Utils_TooltipCommon::create($icon, $txt, false), array('maxlength'=>16000, 'style'=>'width:97%', 'id'=>'template'));
        $txt = '<ul><li>&lt;Class name&gt;::&ltmethod name&gt</li><li>&ltfunction name&gt</li><li>PHP:<br />- $record (array)<br />- $links_not_recommended (bool)<br />- $field (array)<br />return "value to display";</li></ul>';
		$form->addElement('textarea', 'display_callback', __('Value display function') . Utils_TooltipCommon::create($icon, $txt, false), array('maxlength'=>16000, 'style'=>'width:97%', 'id'=>'display_callback'));
        $txt = '<ul><li>&lt;Class name&gt;::&ltmethod name&gt</li><li>&ltfunction name&gt</li><li>PHP:<br />- $form (QuickForm object)<br />- $field (string)<br />- $label (string)<br />- $mode (string)<br />- $default (mixed)<br />- $desc (array)<br />- $rb_obj (RB object)<br />- $display_callback_table (array)</li></ul>';
		$form->addElement('textarea', 'QFfield_callback', __('Field generator function') . Utils_TooltipCommon::create($icon, $txt, false), array('maxlength'=>16000, 'style'=>'width:97%', 'id'=>'QFfield_callback'));
		
        if ($action=='edit') {
			$form->freeze('field');
			$form->freeze('select_data_type');
			$form->freeze('data_source');
			$form->freeze('rset');
		}
		
		if ($action=='edit') {
			$display_callbacback = DB::GetOne('SELECT callback FROM '.$this->tab.'_callback WHERE freezed=1 AND field=%s', array($field));
			$QFfield_callbacback = DB::GetOne('SELECT callback FROM '.$this->tab.'_callback WHERE freezed=0 AND field=%s', array($field));
			$form->setDefaults(array('display_callback'=>$display_callbacback));
			$form->setDefaults(array('QFfield_callback'=>$QFfield_callbacback));
		}

        if ($form->validate()) {
            $data = $form->exportValues();
            $data['caption'] = trim($data['caption']);
            $data['field'] = trim($data['field']);
            $data['template'] = trim($data['template']);
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
                case 'autonumber':
                    $data['required'] = false;
                    $data['filter'] = false;
                    $param = Utils_RecordBrowserCommon::encode_autonumber_param(
                            $data['autonumber_prefix'],
                            $data['autonumber_pad_length'],
                            $data['autonumber_pad_mask']);
                    // delete field and add again later to generate values
                    if ($action != 'add') {
                        Utils_RecordBrowserCommon::delete_record_field($this->tab, $field);
                        $action = 'add';
                        $field = $data['field'];
                    }
                    break;
				case 'checkbox': 
				case 'calculated': 
							$data['required'] = false;
							break;
				case 'text': if ($action=='add') $param = $data['text_length'];
							else {
								if ($data['text_length']<$row['param']) trigger_error('Invalid field length', E_USER_ERROR);
								$param = $data['text_length'];
								if ($data['text_length']!=$row['param']) {
									if(DB::is_postgresql())
										DB::Execute('ALTER TABLE '.$this->tab.'_data_1 ALTER COLUMN f_'.$id.' TYPE VARCHAR('.$param.')');
									else
										DB::Execute('ALTER TABLE '.$this->tab.'_data_1 MODIFY f_'.$id.' VARCHAR('.$param.')');
								}
							}
							break;
				case 'select':
							if ($data['data_source']=='commondata') {
								if ($data['select_type']=='select') {
									$param = Utils_RecordBrowserCommon::encode_commondata_param(array('order'=>$data['order_by'], 'array_id'=>$data['commondata_table']));
									$data['select_data_type'] = 'commondata';
								} else {
									$param = '__COMMON__::'.$data['commondata_table'].'::'.$data['order_by'];
									$data['select_data_type'] = 'multiselect';
								}
							} else {
								$data['select_data_type'] = $data['select_type'];
								if (!isset($row) || !isset($row['param'])) $row['param'] = ';::';
								$props = explode(';', $row['param']);
                                $change_param = false;
								if($data['rset']) {
								    $fs = explode(',', $data['label_field']);
								    if($data['label_field']) foreach($data['rset'] as $rset) {
        								$ret = $this->detranslate_field_names($rset, $fs);
	        							if (!empty($ret)) trigger_error('Invalid fields: '.implode(',',$fs));
	        						    }
	        						    $data['rset'] = implode(',',$data['rset']);
	        						    $data['label_field'] = implode('|',$fs);
                                    $change_param = true;
								} else if ($action == 'add') {
								    $data['rset'] = '__RECORDSETS__';
								    $data['label_field'] = '';
                                    $change_param = true;
								}
                                if ($change_param) {
                                    $props[0] = $data['rset'].'::'.$data['label_field'];
                                    $param = implode(';', $props);
                                } else {
                                    $param = $row['param'];
                                }
							}
							if (isset($row) && isset($row['type']) && $row['type']=='multiselect' && $data['select_type']=='select') {
								$ret = DB::Execute('SELECT id, f_'.$id.' AS v FROM '.$this->tab.'_data_1 WHERE f_'.$id.' IS NOT NULL');
								while ($rr = $ret->FetchRow()) {
									$v = Utils_RecordBrowserCommon::decode_multi($rr['v']);
									$v = array_pop($v);
									DB::Execute('UPDATE '.$this->tab.'_data_1 SET f_'.$id.'=%s WHERE id=%d', array($v, $rr['id']));
								}
							}
							if (isset($row) && isset($row['type'])  && $row['type']!='multiselect' && $data['select_type']=='multiselect') {
								if(DB::is_postgresql())
									DB::Execute('ALTER TABLE '.$this->tab.'_data_1 ALTER COLUMN f_'.$id.' TYPE TEXT');
								else
									DB::Execute('ALTER TABLE '.$this->tab.'_data_1 MODIFY f_'.$id.' TEXT');
								$ret = DB::Execute('SELECT id, f_'.$id.' AS v FROM '.$this->tab.'_data_1 WHERE f_'.$id.' IS NOT NULL');
								while ($rr = $ret->FetchRow()) {
									$v = Utils_RecordBrowserCommon::encode_multi($rr['v']);
									DB::Execute('UPDATE '.$this->tab.'_data_1 SET f_'.$id.'=%s WHERE id=%d', array($v, $rr['id']));
								}
							}
							break;
                case 'time':
                case 'timestamp':
                    $param = $data['minute_increment'];
                    break;
				default:	if (isset($row) && isset($row['param']))
								$param = $row['param'];
							break;
			}
            if ($action=='add') {
                $id = $new_id;
                if (in_array($data['select_data_type'], array('time','timestamp','currency','integer')))
                    $style = $data['select_data_type'];
                else
                    $style = '';
                $new_field_data = array('name' => $data['field'], 'type' => $data['select_data_type'], 'param' => $param, 'style' => $style);
                if (isset($this->admin_field['position']) && $this->admin_field['position']) {
                    $new_field_data['position'] = (int) $this->admin_field['position'];
                }
                Utils_RecordBrowserCommon::new_record_field($this->tab, $new_field_data);
            }
            if(!isset($data['visible']) || $data['visible'] == '') $data['visible'] = 0;
            if(!isset($data['required']) || $data['required'] == '') $data['required'] = 0;
            if(!isset($data['filter']) || $data['filter'] == '') $data['filter'] = 0;
            if(!isset($data['export']) || $data['export'] == '') $data['export'] = 0;
            if(!isset($data['tooltip']) || $data['tooltip'] == '') $data['tooltip'] = 0;

            foreach($data as $key=>$val)
                if (is_string($val) && $key != 'help' && $key != 'QFfield_callback' && $key != 'display_callback') $data[$key] = htmlspecialchars($val);

/*            DB::StartTrans();
            if ($id!=$new_id) {
                Utils_RecordBrowserCommon::check_table_name($this->tab);
                if(DB::is_postgresql())
                    DB::Execute('ALTER TABLE '.$this->tab.'_data_1 RENAME COLUMN f_'.$id.' TO f_'.$new_id);
                else {
                    $old_param = DB::GetOne('SELECT param FROM '.$this->tab.'_field WHERE field=%s', array($field));
                    DB::RenameColumn($this->tab.'_data_1', 'f_'.$id, 'f_'.$new_id, Utils_RecordBrowserCommon::actual_db_type($type, $old_param));
                }
            }*/
            DB::Execute('UPDATE '.$this->tab.'_field SET caption=%s, param=%s, type=%s, field=%s, visible=%d, required=%d, filter=%d, export=%d, tooltip=%d, help=%s, template=%s WHERE field=%s',
                        array($data['caption'], $param, $data['select_data_type'], $data['field'], $data['visible'], $data['required'], $data['filter'], $data['export'], $data['tooltip'], $data['help'], $data['template'], $field));
/*            DB::Execute('UPDATE '.$this->tab.'_edit_history_data SET field=%s WHERE field=%s',
                        array($new_id, $id));
            DB::CompleteTrans();*/
			
			DB::Execute('DELETE FROM '.$this->tab.'_callback WHERE freezed=1 AND field=%s', array($field));
			if ($data['display_callback'])
				DB::Execute('INSERT INTO '.$this->tab.'_callback (callback,freezed,field) VALUES (%s,1,%s)', array($data['display_callback'], $data['field']));
				
			DB::Execute('DELETE FROM '.$this->tab.'_callback WHERE freezed=0 AND field=%s', array($field));
			if ($data['QFfield_callback'])
				DB::Execute('INSERT INTO '.$this->tab.'_callback (callback,freezed,field) VALUES (%s,0,%s)', array($data['QFfield_callback'], $data['field']));
			
            $this->init(true, true);
            return false;
        }
        $form->display_as_column();

        $autohide_mapping = array(
        		'select_data_type' => array(
		        		array('values'=>'text',
		        				'mode'=>'show',
		        				'fields'=>array('length')
		        		),
		        		array('values'=>'select',
		        				'mode'=>'show',
		        				'fields'=>array('data_source', 'select_type', 'commondata_table', 'order_by', 'rset_label', 'label_field')
		        		),
		        		array('values'=>'autonumber',
		        				'mode'=>'show',
		        				'fields'=>array('autonumber_prefix', 'autonumber_pad_length', 'autonumber_pad_mask')
		        		),
		        		array('values'=>array('time', 'timestamp'),
		        				'mode'=>'show',
		        				'fields'=>array('minute_increment')
		        		),
		        		array('values'=>array('checkbox', 'autonumber'),
		        				'mode'=>'hide',
		        				'fields'=>array('required')
		        		),
		    	),
	        	'data_source' => array(
		        		array('values'=>'rset',
		        				'mode'=>'show',
		        				'fields'=>array('rset_label', 'label_field')
		        		),
		        		array('values'=>'commondata',
		        				'mode'=>'show',
		        				'fields'=>array('commondata_table', 'order_by')
		        		),
		        ),
	        	'advanced' => array(
			        	array('values'=>1,
			        			'mode'=>'show',
			        			'fields'=>array('template', 'display_callback', 'QFfield_callback'),
			        			'confirm'=>__('Changing these settings may often cause system unstability. Are you sure you want to see advanced settings?')
			        	)
			        )
        );
        
        $row['advanced'] = 0;
        
        foreach ($autohide_mapping as $control_field=>$map) {
        	$form->autohide_fields($control_field, isset($row[$control_field])? $row[$control_field]:null, $map);
        }

		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		Base_ActionBarCommon::add('back', __('Cancel'), $this->create_back_href());
		
        return true;
    }
    
    public static function qf_rule_without_double_underscore($str) {
        return strpos($str, '__') === false;
    }
	
	public function check_field_definitions($data) {
		$ret = array();
		
		if ($this->admin_field_mode=='edit') 
			$type = $this->admin_field_type;
		else
			$type = $data['select_data_type'];

		if ($type == 'text') {
			$last = $this->admin_field_name?DB::GetOne('SELECT param FROM '.$this->tab.'_field WHERE field=%s', array($this->admin_field_name)):1;
			if ($data['text_length']<$last) $ret['text_length'] = __('Must be a number greater or equal %d', array($last));
			if ($data['text_length']>255) $ret['text_length'] = __('Must be a number no greater than %d', array(255));
			if (!is_numeric($data['text_length'])) $ret['text_length'] = __('Must be a number');
			if ($data['text_length']=='') $ret['text_length'] = __('Field required');
		}
		if ($type == 'select') {
			if (!isset($data['data_source'])) $data['data_source'] = $this->admin_field['data_source'];
			if (!isset($data['rset'])) $data['rset'] = $this->admin_field['rset'];
			if (!is_array($data['rset'])) $data['rset'] = array_filter(explode('__SEP__', $data['rset'])); // data from multiselect field passed in raw format here
			if ($data['data_source']=='commondata' && $data['commondata_table']=='') $ret['commondata_table'] = __('Field required');
			if ($data['data_source']=='rset') {
				if ($data['label_field']!='') {
				    $fs = explode(',', $data['label_field']);
				    foreach($data['rset'] as $rset)
				        $ret = $ret + $this->detranslate_field_names($rset, $fs);
				}
			}
			if ($this->admin_field_mode=='edit' && $data['select_type']=='select' && $this->admin_field['select_type']=='multiselect') {
				$count = DB::GetOne('SELECT COUNT(*) FROM '.$this->tab.'_data_1 WHERE f_'.Utils_RecordBrowserCommon::get_field_id($this->admin_field['field']).' '.DB::like().' %s', array('%_\_\__%'));
				if ($count!=0) {
					$ret['select_type'] = __('Cannot change type');
					print('<span class="important_notice">'.__('Following records have more than one value stored in this field, making type change impossible:'));
					$recs = DB::GetCol('SELECT id FROM '.$this->tab.'_data_1 WHERE f_'.Utils_RecordBrowserCommon::get_field_id($this->admin_field['field']).' '.DB::like().' %s', array('%_\_\__%'));
					foreach ($recs as $r)
						print('<br/>'.Utils_RecordBrowserCommon::create_default_linked_label($this->tab, $r, false, false));
					print('</span>');
				}
			}
		}

        $show_php_embedding = false;
        foreach (array('QFfield_callback', 'display_callback') as $ff) {
            if (isset($data[$ff]) && $data[$ff]) {
                $callback_func = Utils_RecordBrowserCommon::callback_check_function($data[$ff], true);
                if ($callback_func) {
                    if (!is_callable($callback_func)) {
                        $ret[$ff] = __('Invalid callback');
                    }
                } elseif (!defined('ALLOW_PHP_EMBEDDING') || !ALLOW_PHP_EMBEDDING) {
                    $ret[$ff] = __('Using PHP code is blocked');
                    $show_php_embedding = true;
                }
            }
        }
        if ($show_php_embedding) {
            print(__('Using PHP code in application is currently disabled. Please edit file %s and add following line:', array(DATA_DIR . '/config.php'))) . '<br>';
            print("<pre>define('ALLOW_PHP_EMBEDDING', 1);</pre>");
        }
            
		return $ret;
	}
	
	public function detranslate_field_names($rset, & $fs) {
		Utils_RecordBrowserCommon::check_table_name($rset);
		$fields = DB::GetAssoc('SELECT field, field FROM '.$rset.'_field WHERE type!=%s AND field!=%s AND type!=%s ORDER BY position', array('page_split', 'id', 'hidden'));
		foreach ($fields as $k=>$f)
			$fields[_V($f)] = $f; // ****** RecordBrowser - field name
		
		$ret = array();
		foreach ($fs as $k=>$f) {
			$f = trim($f);
            $fs[$k] = $f;
			if (isset($fields[$f]) && $f==$fields[$f]) continue;
			if (isset($fields[$f])) {
				$fs[$k] = $fields[$f];
				continue;
			}
			$ret['label_field'] = __('Field not found: %s', array($f));
		}
		return $ret;
	}
	
    public function check_if_no_id($arg){
        return !preg_match('/^[iI][dD]$/',$arg);
    }
    public function check_if_column_exists($arg){
        $this->init(true);
        if (strtolower($arg)==strtolower($this->current_field)) return true;
        foreach($this->table_rows as $desc)
        	if (strtolower($desc['name']) == strtolower($arg))
                return false;
        return true;
    }
    public function dirty_read_changes($id, $time_from) {
        print('<b>'.__('The following changes were applied to this record while you were editing it.').'<br/>'.__('Please revise this data and make sure to keep this record most accurate.').'</b><br>');
        $gb_cha = $this->init_module(Utils_GenericBrowser::module_name(), null, $this->tab.'__changes');
        $table_columns_changes = array( array('name'=>__('Date'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Username'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Field'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Old value'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('New value'), 'width'=>10, 'wrapmode'=>'nowrap'));
        $gb_cha->set_table_columns( $table_columns_changes );

        $created = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
        $field_hash = array();
        foreach($this->table_rows as $field => $desc)
        	$field_hash[$desc['id']] = $field;
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
        $theme = $this->init_module(Base_Theme::module_name());
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
        $gb_cha = $this->init_module(Utils_GenericBrowser::module_name(), null, $this->tab.'__changes');
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
        foreach($this->table_rows as $field => $desc)
        	$field_hash[$desc['id']] = $field;

        $ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->tab.'_id=%d ORDER BY edited_on DESC, id DESC',array($id));
		$dates_select = array();
		$tb_path = escapeJS($tb->get_path());
        while ($row = $ret->FetchRow()) {
			$user = Base_UserCommon::get_user_label($row['edited_by']);
			$date_and_time = Base_RegionalSettingsCommon::time2reg($row['edited_on']);
            $changed = array();
            $ret2 = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
            while($row2 = $ret2->FetchRow()) {
                if ($row2['field']!='id' && (!isset($access[$row2['field']]) || !$access[$row2['field']])) continue;
                $changed[$row2['field']] = $row2['old_value'];
                $last_row = $row2;
                $dates_select[$row['edited_on']] = $date_and_time;
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
                        _V($this->table_rows[$field_hash[$k]]['name']), // TRSL
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
		foreach($this->table_rows as $field => $desc) {
			if (!$access[$desc['id']]) continue;
			$val = $this->get_val($field, $created, false, $desc);
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
    public function set_filters_defaults($arg, $merge = false, $overwrite = false) {
        if (!$overwrite && $this->isset_module_variable('def_filter')) return;
        if (!$merge) $this->set_filters(array());
        $f = $this->get_filters();
        if(is_array($arg)) {
            foreach ($arg as $k => $v) {
                if (!array_key_exists($k, $f) || $overwrite) {
                    $f[$k] = $v;
                }
            }
        }
        $this->set_filters($f);
    }
    public function set_filters($filters, $merge = false, $override_saved = false) {
        $current_filters = $merge ? $this->get_filters($override_saved) : array();
        $filters = array_merge($current_filters, $filters);
        if ($override_saved) {
            $this->set_module_variable('def_filter_over', $filters);
        } else {
            $this->set_module_variable('def_filter', $filters);
        }
    }
    public function get_filters($override_saved = false) {
        $filter_var = $override_saved ? 'def_filter_over' : 'def_filter';
    	return $this->get_module_variable($filter_var, array());
    }
    public function set_default_order($arg){
        foreach ($arg as $k=>$v)
            $this->default_order[$k] = $v;
    }
    public function force_order($arg){
        $this->force_order = $arg;
    }
    public function caption(){
        return $this->caption . ': ' . _V($this->action);
    }
    public function recordpicker($element, $format, $crits=array(), $cols=array(), $order=array(), $filters=array(), $select_form = '') {
        $this->init();
        $this->set_module_variable('element',$element);
        $this->set_module_variable('format_func',$format);
        $theme = $this->init_module(Base_Theme::module_name());
        Base_ThemeCommon::load_css($this->get_type(),'Browsing_records');
        $theme->assign('filters', $this->show_filters($filters, $element));
        $theme->assign('disabled', '');
        $theme->assign('select_form', $select_form);
        $this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $crits);
        $this->include_tab_in_id = $select_form? true: false;
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
            eval_js('rpicker_init(\''.$element.'\',\''.$v.'\')');
        }
        $theme->display('Record_picker');
    }
    public function recordpicker_fs($crits, $cols, $order, $filters, $path) {
		self::$browsed_records = array();
        $this->init();
        $theme = $this->init_module(Base_Theme::module_name());
        Base_ThemeCommon::load_css($this->get_type(),'Browsing_records');
        $this->set_module_variable('rp_fs_path',$path);
        $selected = Module::static_get_module_variable($path,'selected',array());
        $theme->assign('filters', $this->show_filters($filters));
        $theme->assign('disabled', '');
        $this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $crits);
        $theme->assign('table', $this->show_data($this->crits, $cols, $order, false, true));
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
        if (isset(self::$browsed_records['records'])) {
            foreach(self::$browsed_records['records'] as $id=>$i) {
                eval_js('rpicker_fs_init('.$id.','.(isset($selected[$id]) && $selected[$id]?1:0).',\''.$this->get_path().'\')');
            }
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

        $form = $this->init_module(Libs_QuickForm::module_name(), null, 'pick_recordset');
        $opts = Utils_RecordBrowserCommon::list_installed_recordsets('%caption (%tab)');
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
        $custom_recordsets_module = 'Utils/RecordBrowser/CustomRecordsets';
        if (ModuleManager::is_installed($custom_recordsets_module) >= 0) {
            $href = $this->create_callback_href(array('Base_BoxCommon', 'push_module'), array($custom_recordsets_module, 'admin'));
            Base_ActionBarCommon::add('settings', __('Custom Recordsets'), $href);
        }
    }
    public function record_management($table){
		$this->tab = $table;
		$this->administrator_panel();
    }

    public function enable_quick_new_records($button = true, $force_show = null) {
        $this->add_in_table = true;
		$href = 'href="javascript:void(0);" onclick="$(\'add_in_table_row\').style.display=($(\'add_in_table_row\').style.display==\'none\'?\'\':\'none\');if(focus_on_field)if($(focus_on_field))focus_by_id(focus_on_field);"';
        if ($button) $this->add_button = $href;
        if ($force_show===null) $this->show_add_in_table = Base_User_SettingsCommon::get('Utils_RecordBrowser','add_in_table_shown');
        else $this->show_add_in_table = $force_show;
        if ($this->get_module_variable('force_add_in_table_after_submit', false)) {
            $this->show_add_in_table = true;
            $this->set_module_variable('force_add_in_table_after_submit', false);
        }
        Utils_ShortcutCommon::add(array('Ctrl','S'), 'function(){if (jq("#add_in_table_row").is(":visible")) jq("input[name=submit_qanr]").click();}');
		return $href;
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
        $gb = $this->init_module(Utils_GenericBrowser::module_name(),$this->tab,$this->tab);
        $field_hash = array();
        foreach($this->table_rows as $field => $desc)
        	$field_hash[$desc['id']] = $field;
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
        $gb->set_fixed_columns_class($this->fixed_columns_class);

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
                else $s = call_user_func($callbacks[$k], $v, false, $this->table_rows[$field_hash[$w]],$this->tab);
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
            if (isset($conf['actions_delete']) && $conf['actions_delete']) if ($this->get_access('delete',$v)) $gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this record?'),array($this,'delete_record'),array($v['id'], false)),'Delete');
            if (isset($conf['actions_history']) && $conf['actions_history']) {
                $r_info = Utils_RecordBrowserCommon::get_record_info($this->tab, $v['id']);
                if ($r_info['edited_on']===null) $gb_row->add_action('','This record was never edited',null,'history_inactive');
                else $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_edit_history', $v['id'])),'View edit history',null,'history');
            }
            $this->call_additional_actions_methods($v, $gb_row);
        }
        $this->display_module($gb);
    }
	
	public function get_jump_to_id_button() {
        $jump_to_id = DB::GetOne('SELECT jump_to_id FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
        if (!$jump_to_id) {
            return '';
        }
		$link = Module::create_href_js(Utils_RecordBrowserCommon::get_record_href_array($this->tab, '__ID__'));
		if (isset($_REQUEST['__jump_to_RB_record'])) Base_StatusBarCommon::message(__('Record not found'), 'warning');
		$link = str_replace('__ID__', '\'+this.value+\'', $link);
		return ' <a '.Utils_TooltipCommon::open_tag_attrs(__('Jump to record by ID')).' href="javascript:void(0);" onclick="jump_to_record_id(\''.$this->tab.'\')"><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','jump_to.png').'"></a><input type="text" id="jump_to_record_input" style="display:none;width:50px;" onkeypress="if(event.keyCode==13)'.$link.'">';
	}

    public function search_by_id_form($label) {
        $message = '';
        $form = $this->init_module(Libs_QuickForm::module_name());
        $theme = $this->init_module(Base_Theme::module_name());
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
                Base_BoxCommon::push_module(Utils_RecordBrowser::module_name(),'view_entry',array('view', $id),array($this->tab));
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
        $gb = $this->init_module(Utils_GenericBrowser::module_name(),'permissions_'.$this->tab, 'permissions_'.$this->tab);
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
		foreach ($this->table_rows as $desc)
			$all_fields[$desc['id']] = $desc['name'];
		$actions = $this->get_permission_actions();
		$rules = array();
		while ($row = $ret->FetchRow()) {
			if (!isset($clearance[$row['id']])) $clearance[$row['id']] = array();
			if (!isset($fields[$row['id']])) $fields[$row['id']] = array();
			$action = $actions[$row['action']];
			$crits = Utils_RecordBrowserCommon::parse_access_crits($row['crits'], true);
            $crits_text = Utils_RecordBrowserCommon::crits_to_words($this->tab, $crits);
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

			$props = $c_all_fields?($c_all_fields-$c_fields)/$c_all_fields:0;
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
						$gb_row->add_action($this->create_callback_href(array($this, 'edit_permissions_rule'), array($id, true)), 'copy', __('Clone rule'), Base_ThemeCommon::get_template_file(Utils_Attachment::module_name(),'copy_small.png'));
						$gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this rule?'), array($this, 'delete_permissions_rule'), array($id)), 'delete', 'Delete');
				}
		}
		if (Base_AdminCommon::get_access('Utils_RecordBrowser', 'permissions')==2) 
			Base_ActionBarCommon::add('add',__('Add new rule'), $this->create_callback_href(array($this, 'edit_permissions_rule'), array(null)));
		Base_ThemeCommon::load_css('Utils_RecordBrowser', 'edit_permissions');
		$this->display_access_callback_descriptions();
		$this->display_module($gb);
		eval_js('utils_recordbrowser__crits_initialized = false;');
	}
	public function display_access_callback_descriptions() {
		$callbacks = Utils_RecordBrowserCommon::get_custom_access_callbacks($this->tab);
	
		if (!$callbacks) return;
	
		$output = '<div class="crits_callback_info"><b>' . __('The recordset has access crits callbacks active. Final permisions depend on the result of the callbacks:') . '</b>';
		$output .= '<ul>';
	
		foreach ($callbacks as $callback) {
			$output .= '<li><b>' . $callback . '</b>: ';
				
			try {
				list($class_name, $method_name) = explode('::', $callback);
					
				$class = new ReflectionClass($class_name);
				$docblock  = new \phpDocumentor\Reflection\DocBlock($class->getMethod($method_name));
					
				$output .= '<span class="description">' . $docblock->getShortDescription() . '<br />' . $docblock->getLongDescription()->getContents() . '</span>';
			} catch (Exception $e) {
			}
				
			$output .= '</li>';
		}
	
		$output .= '</ul></div>';
	
		print($output);
	}
	public function delete_permissions_rule($id) {
		Utils_RecordBrowserCommon::delete_access($this->tab, $id);
		return false;
	}
	
	public function edit_permissions_rule($id = null, $clone = false) {
		if (Base_AdminCommon::get_access('Utils_RecordBrowser', 'permissions')!=2) return false;
        if ($this->is_back()) {
            return false;
		}
		load_js('modules/Utils/RecordBrowser/edit_permissions.js');
		$all_clearances = array(''=>'---')+array_flip(Base_AclCommon::get_clearance(true));
		$all_fields = array();
		$this->init();
		foreach ($this->table_rows as $k=>$desc)
			$all_fields[$desc['id']] = $k;

		$form = $this->init_module('Libs_QuickForm');
		$theme = $this->init_module('Base_Theme');
		
		$counts = array(
			'clearance'=>5,
		);
		
		$actions = $this->get_permission_actions();
		$form->addElement('select', 'action', __('Action'), $actions);

		for ($i=0; $i<$counts['clearance']; $i++)
			$form->addElement('select', 'clearance_'.$i, __('Clearance'), $all_clearances);

		$defaults = array();
		$form->addElement('multiselect', 'blocked_fields', null, $all_fields);

		$theme->assign('labels', array(
			'and' => '<span class="joint">'.__('and').'</span>',
			'or' => '<span class="joint">'.__('or').'</span>',
			'caption' => $id?__('Edit permission rule'):__('Add permission rule'),
			'clearance' => __('Clearance requried'),
			'fields' => __('Field permissions'),
			'crits' => __('Criteria required'),
			'add_clearance' => __('Add clearance'),
			'add_or' => __('Add criteria (or)'),
			'add_and' => __('Add criteria (and)')
 		));
		$current_clearance = 0;
        $crits = array();
		if ($id!==null && $this->tab!='__RECORDSETS__' && !preg_match('/,/',$this->tab)) {
			$row = DB::GetRow('SELECT * FROM '.$this->tab.'_access AS acs WHERE id=%d', array($id));
			
			$defaults['action'] = $row['action'];
			$crits = Utils_RecordBrowserCommon::unserialize_crits($row['crits']);
            if (is_array($crits)) {
                $crits = Utils_RecordBrowser_Crits::from_array($crits);
            }
			
			$i = 0;
			$tmp = DB::GetAll('SELECT * FROM '.$this->tab.'_access_clearance AS acs WHERE rule_id=%d', array($id));
			foreach ($tmp as $t) {
				$defaults['clearance_'.$i] = $t['clearance'];
				$i++;
			}
			$current_clearance += $i-1;

			$defaults['blocked_fields'] = DB::GetCol('SELECT block_field FROM '.$this->tab.'_access_fields AS acs WHERE rule_id=%d', array($id));
		}
        $form->addElement('crits', 'qb_crits', __('Crits'), $this->tab, $crits);

        $form->setDefaults($defaults);
		
		if ($form->validate()) {
			$vals = $form->exportValues();
			$action = $vals['action'];

			$clearance = array();
			for ($i=0; $i<$counts['clearance']; $i++)
				if ($vals['clearance_'.$i]) $clearance[] = $vals['clearance_'.$i];

            $crits = $vals['qb_crits'];

			if ($id===null || $clone)
				Utils_RecordBrowserCommon::add_access($this->tab, $action, $clearance, $crits, $vals['blocked_fields']);
			else
				Utils_RecordBrowserCommon::update_access($this->tab, $id, $action, $clearance, $crits, $vals['blocked_fields']);
			return false;
		}
		
		$labels_map = array(
			'blocked_fields__from' => __('GRANT'),
			'blocked_fields__to' => __('DENY')
		);
		eval_js('utils_recordbrowser__set_field_access_titles ('.json_encode($labels_map).')');
		eval_js('utils_recordbrowser__init_clearance('.$current_clearance.', '.$counts['clearance'].')');
		eval_js('utils_recordbrowser__crits_initialized = true;');
		
		$form->assign_theme('form', $theme);
		$theme->assign('counts', $counts);
		
		$theme->display('edit_permissions');
		Utils_ShortcutCommon::add(array('Ctrl','S'), 'function(){'.$form->get_submit_form_js().'}');
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
			'export'=>__('Export'),
			'selection'=>__('Selection')
		);
	}
}
?>