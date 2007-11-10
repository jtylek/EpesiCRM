<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Utils_SidetipCommon extends ModuleCommon {
	public static function load() {
		load_js('modules/CRM/Calendar/Utils/Sidetip/sidetip.js');
		eval_js('CRM_Clendar_Utils_Sidetip.reset()');
	}
	public static function create_for( $id, $text, $style = 'horizontal' ) {
		eval_js('CRM_Clendar_Utils_Sidetip.create_for("'.$id.'", "'.addslashes($text).'", "'.$style.'")');
	}
	public static function create_for_all() {
		eval_js('CRM_Clendar_Utils_Sidetip.create_for_all()');
	}
}
?>
