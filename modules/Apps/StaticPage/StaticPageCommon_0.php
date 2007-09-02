<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @package apps-static-page
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_StaticPageCommon extends ModuleCommon {
	public static function admin_caption() {
		return "Static pages";
	}
}

?>