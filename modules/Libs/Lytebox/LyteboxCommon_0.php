<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage lytebox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!MOBILE_DEVICE) {
	Base_ThemeCommon::load_css('Libs/Lytebox','default',false);
	load_js('modules/Libs/Lytebox/3.10/lytebox.js');
}

?>