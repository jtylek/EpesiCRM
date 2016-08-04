<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once('modules/Libs/QuickForm/requires.php');
require_once('modules/Libs/QuickForm/FieldTypes/automulti/automulti.php');
require_once('modules/Libs/QuickForm/FieldTypes/autoselect/autoselect.php');

class Libs_QuickFormCommon extends ModuleCommon {
	private static $on_submit = array();
	
	public static function add_on_submit_action($action) {
		self::$on_submit[] = rtrim($action,';').';';
	}
	
	public static function get_on_submit_actions() {
		$ret = '';
		foreach(self::$on_submit as $t)
			$ret .= $t;
		return $ret;
	}
	
	public static function user_settings() {
		return array(__('Forms')=>array(
			array('name'=>'autoselect_mode', 'label'=>__('Auto-select - display for empty fields'), 'type'=>'select', 'values'=>array(0=>__('Text field'), 1=>__('Select field')), 'default'=>0)
		));
	}
	
	public static function get_error_closing_button() {
		return ' <a href="javascript:void(0);" onclick="this.parentNode.innerHTML=\'\'"><img src="'.Base_ThemeCommon::get_template_file('Libs_QuickForm','close.png').'"></a>';
	}
	
	public static function autohide_fields($field, $hide_mapping) {
		$allowed_modes = array('hide', 'show');
	
		$groups = array();
		foreach ($hide_mapping as $map) {
			if (!isset($map['fields']) || !isset($map['values'])) continue;
	
			$map['mode'] = isset($map['mode'])? $map['mode']: reset($allowed_modes);
			if (!in_array($map['mode'], $allowed_modes)) continue;
	
			$map['fields'] = is_array($map['fields'])? $map['fields']: array($map['fields']);
			$map['fields'] = array_map(function($f){return "#{$f}, #_{$f}__data";}, $map['fields']);
			$map['fields'] = implode(', ', $map['fields']);
	
			$map['values'] = is_array($map['values'])? $map['values']: array($map['values']);
			$map['values'] = array_map(function($v) { if (is_bool($v)) $v = intval($v); return strval($v);}, $map['values']);
	
			$groups[] = $map;
		}
	
		if (empty($groups)) return;
	
		load_js('modules/Libs/QuickForm/autohide_fields.js');
			
		$js_groups = json_encode($groups);
	
		eval_js("
				jq(function(){
					var hide_ctrl = jq('#$field');
					if(hide_ctrl.length==0) return;
					Libs_QuickForm__hide_groups['$field']=$js_groups;
					var observer = new MutationObserver(function(mutations) {
						mutations.forEach(function(mutation) {
							if(mutation.addedNodes.length>0 || mutation.removedNodes.length>0) jq('#'+mutation.target.id).trigger('change');
						})
					});
					hide_ctrl.change(Libs_QuickForm__autohide).trigger('change');
					observer.observe(hide_ctrl.get(0), { childList: true });
				});"
		);
	}
}


?>
