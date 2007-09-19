<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage leightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

Base_ThemeCommon::load_css('Libs/Leightbox','default',false);
load_js('modules/Libs/Leightbox/leightbox.js');
eval_js('updateLbList()');

?>