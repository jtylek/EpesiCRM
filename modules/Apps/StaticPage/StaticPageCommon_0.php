<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage staticpage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_StaticPageCommon extends ModuleCommon {
	public static function admin_caption() {
		return "Static pages";
	}
}

?>