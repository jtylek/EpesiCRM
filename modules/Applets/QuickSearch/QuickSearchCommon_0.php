<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearchCommon extends ModuleCommon{

	public static function applet_caption() {
    	return __("QuickSearch");

	}

	public static function applet_info() {
    	return __("Quick Search"); //here can be associative array
	}
	
}

?>