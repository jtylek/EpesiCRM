<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2016, Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-Filters
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Filters extends Module {
	private $rb_obj;
	private $tab;
	private $filter_field_crits;
	private $active_filters = array();
	private $custom_filters = array();
	private $standard_filter_elements = array();
	private $external_filter_elements = array();
	private $values = array();
	private $clear_filters = false;
	private $table_rows;
	private $form;
	private $crits = array();
	private static $empty_value = '__NULL__';
	
	private static function empty_option() {
		return array(self::$empty_value=>'---');
	}
	
	public function construct($rb_obj, $filter_field_crits = array(), $custom_filters = array()) {
		if (!($rb_obj instanceof Utils_RecordBrowser))
			trigger_error('Cannot construct filters, $rb_obj must be instance of Utils_RecordBrowser:' . $this->get_path() . '.', E_USER_ERROR);
				
		$this->rb_obj = $rb_obj;
		$this->tab = $rb_obj->tab;
		$this->filter_field_crits = $filter_field_crits;
		$this->custom_filters = $custom_filters;
		$this->table_rows = Utils_RecordBrowserCommon::init($this->tab);
	}

	public function get_filters_html($clear_filters = false, $filters_set = array(), $filter_group_id='') {
		if (!$this->get_access('browse')) return;
	
		$this->clear_filters = $clear_filters;
	
		$this->process_filters($filters_set);
	
		$filter_group_elements = $this->get_filter_elements();
	
		if (!$filter_group_elements) return;
	
		$filter_group = array(
				'id' => $filter_group_id,
				'elements' => $filter_group_elements,
				'show' => array('attrs'=>'onclick="Utils_RecordBrowser_Filters.show(\''.$this->tab.'\',\''.$filter_group_id.'\');" id="show_filter_b_'.$filter_group_id.'"','label'=>__('Show filters')),
				'hide' => array('attrs'=>'onclick="Utils_RecordBrowser_Filters.hide(\''.$this->tab.'\',\''.$filter_group_id.'\');" id="hide_filter_b_'.$filter_group_id.'"','label'=>__('Hide filters')),
				'visible' => $this->get_filters_visibility()
		);
	
		load_js($this->get_module_dir() . 'js/filters.js');
	
		$theme = $this->init_module(Base_Theme::module_name());
		$this->form->assign_theme('form', $theme);
	
		$theme->assign('filter_group', $filter_group);
	
		Base_ThemeCommon::load_css($this->get_type());
		
		return array(
				'controls' => $theme->get_html('controls'),
				'elements' => $theme->get_html('elements')
		);
	}
	
	public function get_filters_visibility() {
		$ret = Utils_RecordBrowser_FiltersCommon::get_filters_visibility($this->tab);

		if (!$this->saving_filters_enabled()) {
			if (!$this->isset_module_variable('filters_defaults')) {
				$this->set_module_variable('filters_defaults', $this->crits);
			} elseif ($this->crits != $this->get_module_variable('filters_defaults')) {
				$ret = true;
			}
		}

		return $ret? true: false;
	}
	
	protected function process_filters($filters_set) {
		$this->init_active_filters($filters_set);
		
		$this->set_standard_filter_values();
		
		$this->process_external_filters();
		
		$this->set_standard_filter_crits();

		$this->save_filter_values();
	}
	
	protected function set_standard_filter_values() {
		$this->form = $this->init_module(Libs_QuickForm::module_name(), null, $this->tab.'filters');
		
		$this->standard_filter_elements = array();
		foreach ($this->active_filters as $filter_name) {
			$filter_id = self::get_filter_id($filter_name);
			$element_id = self::get_element_id($filter_id);
		
			if ($this->set_custom_filter($filter_name, $element_id)) continue;
		
			$filter_label = $this->get_filter_label($filter_name);
			$field_type = self::get_field_type($filter_name);
			$desc = $this->table_rows[$filter_name];

			switch ($field_type) {
				case 'timestamp':
				case 'date':
					$this->form->addElement('datepicker', $element_id.'__from', $filter_label.' ('.__('From').')', array('label'=>false));
					$this->form->addElement('datepicker', $element_id.'__to', $filter_label.' ('.__('To').')', array('label'=>false));
					$this->standard_filter_elements[] = $element_id.'__from';
					$this->standard_filter_elements[] = $element_id.'__to';
					break;
					 
				case 'currency':
				case 'float':
				case 'integer':
				case 'autonumber':
					$this->form->addElement('text', $element_id.'__from', $filter_label.' ('.__('From').')', array('label'=>false));
					$this->form->addElement('text', $element_id.'__to', $filter_label.' ('.__('To').')', array('label'=>false));
					$this->form->addRule($element_id.'__from',__('Only numbers are allowed.'),'numeric');
					$this->form->addRule($element_id.'__to',__('Only numbers are allowed.'),'numeric');
					$this->standard_filter_elements[] = $element_id.'__from';
					$this->standard_filter_elements[] = $element_id.'__to';
					if ($field_type == 'currency') {
						$select_options = Utils_CurrencyFieldCommon::get_currencies();
						if (count($select_options) > 1) {
							$select_options = self::empty_option() + $select_options;
							$this->form->addElement('select', $element_id.'__currency', $filter_label.' ('.__('Currency').')', $select_options);
							$this->standard_filter_elements[] = $element_id.'__currency';
						}
					}
					break;
					 
				case 'checkbox':
					$select_options = self::empty_option() + array(''=>__('No'), 1=>__('Yes'));
					$this->form->addElement('select', $element_id, $filter_label, $select_options);
					
					$this->standard_filter_elements[] = $element_id;
					break;
		
				case 'commondata':
					$parts = explode('::', $desc['param']['array_id']);
					$array_id = array_shift($parts);
					$select_options = Utils_CommonDataCommon::get_translated_array($array_id, $desc['param']['order']);
					$made_of_parts = false;
					while (!empty($parts)) {
						$made_of_parts = true;
						array_shift($parts);
						$next_arr = array();
						foreach ($select_options as $k=>$v) {
							$next = Utils_CommonDataCommon::get_translated_array($array_id.'/'.$k, $desc['param']['order']);
							foreach ($next as $k2=>$v2)
								$next_arr[$k.'/'.$k2] = $v.' / '.$v2;
						}
						$select_options = $next_arr;
					}
					if ($made_of_parts) natcasesort($select_options);

					$select_options = self::empty_option() + $select_options;
					
					$this->form->addElement('select', $element_id, $filter_label, $select_options);
					
					$this->standard_filter_elements[] = $element_id;
					break;
		
				case 'select':
				case 'multiselect':
					$autoselect = false;
					$select_options = array();
		
					$param = Utils_RecordBrowserCommon::decode_select_param($desc['param']);
					$multi_adv_params = Utils_RecordBrowserCommon::call_select_adv_params_callback($param['adv_params_callback']);
					$format_callback = $multi_adv_params['format_callback'];
		
					if ($param['single_tab'] == '__COMMON__') {
						if (empty($param['array_id'])) continue;
						$select_options = Utils_CommonDataCommon::get_translated_tree($param['array_id'], $param['order']);
					} else {
						$crits = array();
						if (isset($this->filter_field_crits[$desc['id']])) {
							$crits = $this->filter_field_crits[$desc['id']];
						} else {
							$crits = Utils_RecordBrowserCommon::get_select_tab_crits($param); //tab_crits
						}

						$autoselect = true;

						if ($param['single_tab']) {
							Utils_RecordBrowserCommon::check_table_name($param['single_tab']);
							
							$tab = $param['single_tab'];
							
							//--->temporary use to cover bug in CRM_Contacts module where crits and adv_param_callbacks are in reversed order
							if ($tab == 'contact' && $this->rb_obj->get_QFfield_callback($filter_name) == 'CRM_ContactsCommon::QFfield_contact') {
								$crits_callback = $param['adv_params_callback'];
								$param['adv_params_callback'] = $param['crits_callback'];
								$param['crits_callback'] = $crits_callback;
								$multi_adv_params = Utils_RecordBrowserCommon::call_select_adv_params_callback($param['adv_params_callback']);
								$format_callback = $multi_adv_params['format_callback'];
								$crits = call_user_func($crits_callback, false);
							}
							///--->end temporary use. to be removed when bug fixed
							
							$crits = isset($crits[$tab])? $crits[$tab]: $crits; //in case tab_crits
							
							$qty = Utils_RecordBrowserCommon::get_records_count($tab, $crits);
							
							if ($qty <= Utils_RecordBrowserCommon::$options_limit) {
								$autoselect = false;
								
								$records = Utils_RecordBrowserCommon::get_records($tab, $crits);
								foreach ($records as $id=>$r) {
									$select_options[$id] = Utils_RecordBrowserCommon::call_select_item_format_callback($format_callback, $id, array($this->tab, $crits, $format_callback, $param));
								}
								natcasesort($select_options);
							}

							if ($tab == 'contact') $select_options = array($this->rb_obj->crm_perspective_default()=>'['.__('Perspective').']')+$select_options;
						}
					}
		
					$select_options = self::empty_option() + $select_options;
					
					if ($autoselect) {
						$this->form->addElement('autoselect', $element_id, $filter_label, $select_options, array(array('Utils_RecordBrowserCommon', 'automulti_suggestbox'), array($this->tab, $crits, $format_callback, $param)), $format_callback);
						$this->form->setDefaults(array($element_id=>self::$empty_value));
					} else {
						$this->form->addElement('select', $element_id, $filter_label, $select_options);
					}
		
					$this->standard_filter_elements[] = $element_id;
					break;
				default:
					continue;
			}
		}
		$this->form->addElement('submit', 'submit', __('Show'));
		
		if ($this->clear_filters) {
			$this->save_filters(array());
			print('<span style="display:none;">'.microtime(true).'</span>');
		}

		$this->form->setDefaults($this->get_saved_filters());
		
		$this->values = $this->form->exportValues();	
	}	

	protected function process_external_filters() {
		$external_filters = array();
		$filter_crits = array();
	
		$ret = DB::Execute('SELECT * FROM recordbrowser_browse_mode_definitions WHERE tab=%s', array($this->tab));
		while ($row = $ret->FetchRow()) {
			$m = $this->init_module($row['module']);
			$this->display_module($m, array(& $this->form, & $external_filters, & $this->values, & $filter_crits, $this->rb_obj), $row['func']);
		}
	
		$this->external_filter_elements = array_map(array($this->get_type(), 'get_element_id'), $external_filters);
		$this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $filter_crits);
	}
	
	protected function set_standard_filter_crits() {
		$filter_crits = array();
		
		foreach ($this->active_filters as $filter_name) {			
			$filter_id = self::get_filter_id($filter_name);
			$element_id = self::get_element_id($filter_id);
			
			if (in_array($element_id, $this->external_filter_elements)) continue;
			
			$desc = $this->table_rows[$filter_name];
		
			if ($this->set_custom_filter_crits($filter_name, $element_id)) continue;
			
			$field_type = self::get_field_type($filter_name);
				
			switch ($field_type) {
				case 'timestamp':
				case 'date':
					if (isset($this->values[$element_id.'__from']) && $this->values[$element_id.'__from'])
						$filter_crits['>='.$filter_id] = $this->values[$element_id.'__from'].' 00:00:00';
					if (isset($this->values[$element_id.'__to']) && $this->values[$element_id.'__to'])
						$filter_crits['<='.$filter_id] = $this->values[$element_id.'__to'].' 23:59:59';
					break;
			
				case 'currency':
				case 'float':
				case 'integer':
				case 'autonumber':
					if (isset($this->values[$element_id.'__currency']) && $this->values[$element_id.'__currency'] != self::$empty_value)
						$filter_crits["~$filter_id"] = "%\\_\\_" . $this->values[$element_id.'__currency'];
					if (isset($this->values[$element_id.'__from']) && $this->values[$element_id.'__from'] !== '')
						$filter_crits[">=$filter_id"] = floatval($this->values[$element_id.'__from']);
					if (isset($this->values[$element_id.'__to']) && $this->values[$element_id.'__to'] !== '')
						$filter_crits["<=$filter_id"] = floatval($this->values[$element_id.'__to']);
					break;

				case 'commondata':
					if (!isset($this->values[$element_id]))
						$this->values[$element_id]=self::$empty_value;
						
					if ($this->values[$element_id]!==self::$empty_value) {
						$values = explode('/', $this->values[$element_id]);
						$param = explode('::', $desc['param']['array_id']);
						array_shift($param);
						$param[] = $filter_id;
						foreach ($values as $v)
							$filter_crits[self::get_filter_id(array_shift($param))] = $v;
					}
					break;

				default:
					if (!isset($this->values[$element_id]) || ($field_type=='select' && $this->values[$element_id]==='')) 
						$this->values[$element_id]=self::$empty_value;
						
					if ($this->values[$element_id]!==self::$empty_value) 						
						$filter_crits[$filter_id] = $this->values[$element_id];
					break;
			}
		}

		$this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $filter_crits);
	}
	
	protected function init_active_filters($filters_set = array()) {
		$access = $this->get_access('view');
		
		$this->active_filters = array();
		foreach ($this->table_rows as $field_name=>$desc) {
			$field_id = $desc['id'];
			if (!isset($access[$field_id]) || !$access[$field_id]) continue;
			
			if ((!isset($filters_set[$field_id]) && $desc['filter']) || (isset($filters_set[$field_id]) && $filters_set[$field_id])) {
				$this->active_filters[] = $field_name;
				if (isset($filters_set[$field_id])) {
					unset($filters_set[$field_id]);
				}
			}		
		}

		return $this->active_filters;
	}
	
	protected function set_custom_filter($filter_name, $element_id) {
		$filter_id = self::get_filter_id($filter_name);
		
		if (!isset($this->custom_filters[$filter_id])) return false;
		
		$f = $this->custom_filters[$filter_id];
		
		if (!isset($f['label'])) $f['label'] = $this->get_filter_label($filter_name);
		if (!isset($f['args'])) $f['args'] = null;
		if (!isset($f['args_2'])) $f['args_2'] = null;
		if (!isset($f['args_3'])) $f['args_3'] = null;
		
		$this->form->addElement($f['type'], $element_id, $f['label'], $f['args'], $f['args_2'], $f['args_3']);
		
		$this->standard_filter_elements[] = $element_id;
		
		return true;
	}
	
	protected function set_custom_filter_crits($filter_name, $element_id) {
		$filter_id = self::get_filter_id($filter_name);
	
		if (!isset($this->custom_filters[$filter_id])) return false;
		
		if (!isset($this->values[$element_id])) {
			if ($this->custom_filters[$filter_id]['type']!='autoselect')
				$this->values[$element_id]=self::$empty_value;
			else
				$this->values[$element_id]='';
		}
		
		$custom_filter_crits = array();
		if (isset($this->custom_filters[$filter_id]['trans'][$this->values[$element_id]])) {
			$custom_filter_crits = $this->custom_filters[$filter_id]['trans'][$this->values[$element_id]];
		} elseif (isset($this->custom_filters[$filter_id]['trans_callback'])) {
			$custom_filter_crits = call_user_func($this->custom_filters[$filter_id]['trans_callback'], $this->values[$element_id], $filter_id);
		}
				
		$this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $custom_filter_crits);
				
		return true;
	}
	
	protected function get_filter_elements() {
		return array_merge($this->standard_filter_elements, $this->external_filter_elements);
	}
	
	protected function save_filter_values() {
		foreach ($this->values as $k=>$v) {
			$c = str_replace('filter__','',$k);
			if (isset($this->custom_filters[$c]) && $this->custom_filters[$c]['type']=='checkbox' && $v===self::$empty_value) 
				unset($this->values[$k]);
		}
			
		$permanent_save = isset($this->values['submited']) && $this->values['submited'];
		unset($this->values['submited']);
		unset($this->values['submit']);
			
		$this->save_filters($this->values, $permanent_save);
	}
	
	protected function save_filters($def_filter, $permanent_save = true) {
		$this->rb_obj->set_filters($def_filter);
	
		if ($this->saving_filters_enabled()) {
			Base_User_SettingsCommon::save($this->get_type(), $this->tab . '_filters', $def_filter);
		}
	}
	
	protected function get_saved_filters() {
		$defaults = $this->rb_obj->get_filters();
		if ($this->saving_filters_enabled()) {
			$saved_filters = Base_User_SettingsCommon::get($this->get_type(), $this->tab . '_filters');
			if ($saved_filters) {
				$defaults = $saved_filters;
			}
		}
		return $defaults;
	}
	
	protected function saving_filters_enabled() {
		return Base_User_SettingsCommon::get($this->get_type(), 'save_filters');
	}
	
	public function get_field_type($filter_name) {
		$desc = $this->table_rows[$filter_name];
		
		return ($desc['type'] == 'calculated' && $desc['param'] != '')? $desc['style']: $desc['type'];
	}
	
	public function get_filter_label($filter_name) {
		$desc = $this->table_rows[$filter_name];
		
		$ret = !empty($desc['caption'])? $desc['caption']: $desc['name'];
		
		return _V($ret);
	}
	
	public static function get_filter_id($filter_name) {
		return Utils_RecordBrowserCommon::get_field_id($filter_name);
	}
	
	public static function get_element_id($filter_id) {
		return 'filter__' . $filter_id;
	}
	
	protected function get_access($action) {
		return Utils_RecordBrowserCommon::get_access($this->tab, $action);
	}
	
	public function get_crits() {
		return $this->crits;
	}
}

?>
