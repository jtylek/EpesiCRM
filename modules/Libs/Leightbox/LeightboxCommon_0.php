<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Base_ThemeCommon::load_css('Libs/Leightbox','default',false);
eval_js_once('wait_while_null(\'Prototype\',\'load_js(\\\'modules/Libs/Leightbox/leightbox.js\\\')\')');
eval_js('wait_while_null(\'updateLbList\',\'updateLbList()\')');

?>