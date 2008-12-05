<?php
/**
 * Codepress editor
 * This module uses CodePress editor released under
 * GNU LESSER GENERAL PUBLIC LICENSE Version 2.1
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-libs
 * @subpackage codepress
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

HTML_Quickform::registerElementType('codepress','modules/Libs/Codepress/HTML_Quickform_codepress_0.php'
                                            ,'HTML_Quickform_codepress');
load_js('modules/Libs/Codepress/0.9.6/codepress.js');
eval_js_once('document.observe("e:load", function(){CodePress.run();})');
Libs_QuickFormCommon::add_on_submit_action('CodePress.update(this)');

?>