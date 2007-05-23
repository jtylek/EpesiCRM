<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

 HTML_Quickform::registerElementType('fckeditor','modules/Libs/FCKeditor/HTML_Quickform_fckeditor_0.php'
                                            ,'HTML_Quickform_fckeditor');
load_js('modules/Libs/FCKeditor/onsubmit.js');
Libs_QuickFormCommon::add_on_submit_action('if(typeof(fckeditor_onsubmit)!=\'undefined\')fckeditor_onsubmit(this)');
?> 