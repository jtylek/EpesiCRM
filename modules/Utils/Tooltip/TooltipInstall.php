<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TooltipInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Utils/Tooltip');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/Tooltip');
		return true;
	}
}

?>
