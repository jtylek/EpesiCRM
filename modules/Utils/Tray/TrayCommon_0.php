<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-tray
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TrayCommon extends ModuleCommon {
	public static $tray_cols=array(2=>2,3=>3,4=>4,5=>5,6=>6);
	public static $tray_layout=array('checkered'=>'Checkered','white'=>'White');
	private static $tmp_trays;

	public static function menu() {
		if(Base_AclCommon::check_permission('Dashboard')) {
			$tray_settings = Utils_TrayCommon::get_trays();
			if (count($tray_settings)>0)
			return array(_M('Tray')=>array('__icon__'=>'pile_small.png'));
		}
		return array();
	}

	public static function applet_caption() {
		return __('Tray');
	}

	public static function caption() {
		return __('Tray');
	}

	public static function applet_info() {
		return __('Displays overview of pending items.');
	}

	public static function applet_settings() {
		return array(
		array(
		'name'=>'title','label'=>__('Title'),'type'=>'text','default'=>__('Tray'),
		'rule'=>array(array('message'=>__('Field required'), 'type'=>'required'))),
		array(
		'name'=>'max_trays','label'=>__('Tray Limit'),'type'=>'select','values'=>array('__NULL__'=>__('No Limit'),4=>4,6=>6,8=>8,10=>10),'default'=>6),
		array(
		'name'=>'max_slots','label'=>__('Slots Limit'),'type'=>'select','values'=>array('__NULL__'=>__('No Limit'),2=>2,3=>3,4=>4,5=>5,6=>6),'default'=>3),
		);
	}

	public static function get_trays() {
		static $trays;
		static $user;
		if(!isset($trays) || $user!=Acl::get_user()) {
			$user = Acl::get_user();
			$trays = ModuleManager::call_common_methods('tray',false);
		}
		return $trays;
	}

	public static function are_filters_changed($tray_slot_filters) {
		$filtered = false;
		foreach ($_REQUEST as $id=>$value) {
			if (stripos($id, 'filter__')!==false) {
				$filtered = true;
				break;
			}
		}

		if (!$filtered) return false;

		$filter_changed = false;
		foreach ($tray_slot_filters as $id=>$value) {
			if ($_REQUEST['filter__'.$id]!= $value) {
				$filter_changed=true;
				break;
			}
		}

		return $filter_changed;
	}

	public static function get_tray($tab, $tray_settings) {
		if (!isset($tab) || !isset($tray_settings['__title__']) || !isset($tray_settings['__slots__'])) return array();

		$slot_defs = self::get_slots($tab, $tray_settings);
		$weight = isset($tray_settings['__weight__'])? $tray_settings['__weight__']: 0;

		return array('__title__'=>$tray_settings['__title__'], '__weight__'=> $weight, '__slots__'=>$slot_defs);
	}

	public static function get_slots($tab, $tray_settings) {
		if (!isset($tray_settings['__title__']) || !isset($tray_settings['__slots__'])) return array();

		$ret = array();
		foreach ($tray_settings['__slots__'] as $slot) {
			if (!isset($slot['__name__'])) continue;

			$crits = self::get_slot_crits($slot, $tray_settings);
			$slot_id = Utils_RecordBrowserCommon::get_field_id($slot['__name__']);

			$ret[$slot_id] = $slot + array('__id__'=>$slot_id, '__count__'=>Utils_RecordBrowserCommon::get_records_count($tab, $crits) ,'__crits__'=>$crits);
		}

		return $ret;
	}

	public static function get_slot_crits($slot, $tray_settings) {
		$crits = array();
		$trans_callbacks = isset($tray_settings['__trans_callbacks__'])? $tray_settings['__trans_callbacks__']:null;

		$record_filters = isset($slot['__filters__']) ? $slot['__filters__'] : array();

		foreach ($record_filters as $field=>$val) {
			$trans_callback = null;
			if (isset($trans_callbacks[$field])) {
				$trans_callback = is_array($trans_callbacks[$field])? implode('::', $trans_callbacks[$field]): $trans_callbacks[$field];
			}

			$record_crits = is_callable($trans_callback)? call_user_func($trans_callback, $val, $field): array($field=>$val);

			$crits += is_array($record_crits)? $record_crits: array();
		}

		foreach ($crits as $k=>$c) if ($c==='__PERSPECTIVE__') {
			$crits[$k] = explode(',',trim(CRM_FiltersCommon::get(),'()'));
			if (isset($crits[$k][0]) && $crits[$k][0]=='') unset($crits[$k]);
		}

		return $crits;
	}

	public static function sort_tray_cmp($a, $b) {
		$aw = isset(self::$tmp_trays[$a]['__weight__']) ? self::$tmp_trays[$a]['__weight__']:0;
		$bw = isset(self::$tmp_trays[$b]['__weight__']) ? self::$tmp_trays[$b]['__weight__']:0;
		if(!isset($aw) || !is_numeric($aw)) $aw=0;
		if(!isset($bw) || !is_numeric($bw)) $bw=0;
		if($aw==$bw)
		return strcasecmp($a, $b);
		return $aw-$bw;
	}

	public static function sort_trays(& $trays, $include_slots = true) {
		self::$tmp_trays = $trays;
		uksort($trays, array('Utils_TrayCommon','sort_tray_cmp'));

		foreach($trays as &$t) {
			if(is_array($t) && array_key_exists('__slots__',$t) && $include_slots) {
				self::sort_trays($t['__slots__']);
			}
		}
		unset($trays['__weight__']);
	}

	public static function user_settings(){
		return array(__('Tray settings')=>array(
		array('name'=>'tray_cols','label'=>__('Tray Columns'),'type'=>'select','values'=>Utils_TrayCommon::$tray_cols,'default'=>3),
		array('name'=>'tray_layout','label'=>__('Tray Layout'),'type'=>'select','values'=>Utils_TrayCommon::$tray_layout,'default'=>'checkered')));
	}

	//////////////////////////
	// mobile devices
	public static function mobile_menu() {
		if(!Acl::is_user())
		return array();
		return array(__('Tray')=>array('func'=>'mobile_tray','color'=>'blue'));
	}

	public static function mobile_tray() {
		require_once('modules/Utils/Tray/mobile.php');
	}

	public static function mobile_tray_rb($tab, $crits=array(), $sort = array(), $cols = array()) {
		Utils_RecordBrowserCommon::mobile_rb($tab, $crits, $sort, $cols);
	}
}

?>