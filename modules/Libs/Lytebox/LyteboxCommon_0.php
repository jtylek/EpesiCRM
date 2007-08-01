<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-libs
 * @subpackage lytebox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

Base_ThemeCommon::load_css('Libs/Lytebox','default',false);
eval_js_once('wait_while_null(\'Prototype\',\'load_js(\\\'modules/Libs/Lytebox/3.10/lytebox.js\\\')\')');
eval_js('wait_while_null(\'initLytebox\',\'initLytebox()\')');

?>