<?php
/**
 * Codepress editor
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package libs-codepress
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

HTML_Quickform::registerElementType('codepress','modules/Libs/Codepress/HTML_Quickform_codepress_0.php'
                                            ,'HTML_Quickform_codepress');
load_js('modules/Libs/Codepress/0.9.6/codepress.js');
eval_js('CodePress.run()');
Libs_QuickFormCommon::add_on_submit_action('CodePress.update(this)');

?>