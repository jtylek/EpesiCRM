<?php
/**
 * This module uses FCKeditor editor released under
 * GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 * FCKeditor - The text editor for Internet - http://www.fckeditor.net
 * Copyright (C) 2003-2007 Frederico Caldeira Knabben
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage fckeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!MOBILE_DEVICE) {
	HTML_Quickform::registerElementType('fckeditor','modules/Libs/FCKeditor/HTML_Quickform_fckeditor_0.php'
                                            ,'HTML_Quickform_fckeditor');
	load_js('modules/Libs/FCKeditor/onsubmit.js');
	load_css('modules/Libs/FCKeditor/frontend.css');
	Libs_QuickFormCommon::add_on_submit_action("if(typeof(__PARENT__fckeditor_onsubmit)!='undefined')__PARENT__fckeditor_onsubmit(this)");
}
?> 