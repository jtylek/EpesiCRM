<?php
/**
 * ActionBar
 *
 * This class provides action bar component.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage actionbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarCommon extends ModuleCommon {
	private static $icons = array();
    public static $quick_access_shortcuts = false;

	public static $available_icons = array(
			'home'		=> 0,
			'back'		=> 1,
			'report'	=> 2,
			'history'	=> 3,
			'all'		=> 4,
			'favorites'	=> 5,
			'calendar'	=> 6,
			'search'	=> 7,
			'folder'	=> 8,
			'edit'		=> 9,
			'view'		=> 10,
			'add'		=> 11,
			'delete'	=> 12,
			'save'		=> 13,
			// Same sprite position as 'save' - RecordBrowser_0.php's "Export"
			// button used to reuse the 'save' key outright (icon-only reason,
			// looked identical to a real Save action, most visibly under the
			// AdminLTE theme's own separate icon map), so this is a distinct
			// key for that button now, not a distinct sprite: the legacy
			// theme still renders it exactly as before (unaffected by the
			// rename), only the AdminLTE theme's icon_map (Base_ActionBar/
			// theme_adminltedark/default.tpl) actually diverges the two.
			'export'	=> 13,
			// CRM_ContactsCommon's "Copy company data" used the 'edit' key outright
			// (pencil icon, indistinguishable from a real Edit action) - own key now,
			// same sprite position as 'edit' so the legacy theme is unaffected; only
			// the AdminLTE theme's icon_map (Base_ActionBar/theme_adminltedark/
			// default.tpl) points this at a distinct glyph (bi-building).
			'company'	=> 9,
			'print'		=> 14,
			'clone'		=> 15,
			'settings'	=> 16,
			// CRM_ContactsCommon's / Administrator_0's "Log as user" used the
			// 'settings' key outright (gear icon, indistinguishable from a real
			// Settings action) - own key now, same sprite position as 'settings'
			// so the legacy theme is unaffected; only the AdminLTE theme's
			// icon_map (Base_ActionBar/theme_adminltedark/default.tpl) points
			// this at a distinct glyph (bi-person-circle).
			'login-as'	=> 16,
			'scan'		=> 17,
			'filter'	=> 18,
			'retry'		=> 19,
			'send'		=> 20,
			'new-mail'	=> 21,
			'attach'	=> 22,
			'reply'		=> 23,
			'forward'	=> 24);

	public static function add($type, $text, $action, $description=null, $position = 0) {
//		if(!array_key_exists($type,self::$available_icons)) trigger_error('Invalid action '.$type,E_USER_ERROR);
		foreach (self::$icons as $k=>$v) {
			if ($v['icon']==$type && $v['label']==$text) unset(self::$icons[$k]);
		}
		self::$icons[] = array('icon'=>$type,'label'=>$text,'action'=>$action,'description'=>$description,'position'=>$position);
	}

	public static function get() {
		return self::$icons;
	}

	public static function clean() {
		self::$icons = array();
	}
    
    public static function show_quick_access_shortcuts($value = true) {
        self::$quick_access_shortcuts = $value;
    }
}
on_exit(array('Base_ActionBarCommon','clean'));
?>
