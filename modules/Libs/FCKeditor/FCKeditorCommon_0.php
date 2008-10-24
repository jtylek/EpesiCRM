<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage fckeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!MOBILE_DEVICE) {
	HTML_Quickform::registerElementType('fckeditor','modules/Libs/FCKeditor/HTML_Quickform_fckeditor_0.php'
                                            ,'HTML_Quickform_fckeditor');
	load_js('modules/Libs/FCKeditor/onsubmit.js');
	load_css('modules/Libs/FCKeditor/frontend.css');
	Libs_QuickFormCommon::add_on_submit_action('fckeditor_onsubmit(this)');
}
?> 