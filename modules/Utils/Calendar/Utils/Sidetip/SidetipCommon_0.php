<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Utils_SidetipCommon extends ModuleCommon {
	public static function load() {
		Base_ThemeCommon::load_css('CRM_Calendar_Utils_Sidetip');
		load_js('modules/CRM/Calendar/Utils/Sidetip/sidetip.js');
		eval_js('CRM_Clendar_Utils_Sidetip.reset()');
	}
	public static function create( $activator, $anchor, $text, $style = 'horizontal' ) {
		eval_js('CRM_Clendar_Utils_Sidetip.create("'.$activator.'", "'.$anchor.'", "'.addslashes($text).'", "'.$style.'")');
	}
	public static function create_all() {
		eval_js('CRM_Clendar_Utils_Sidetip.create_all()');
	}
}
?>
