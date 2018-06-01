<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2017, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');
require_once(EPESI_LOCAL_DIR.'/libs/php-fontawesome.php');
class FontAwesome extends Smk_FontAwesome {
    public static function get($class_prefix = 'fa-'){
        return parent::getArray(EPESI_LOCAL_DIR.'/node_modules/font-awesome/css/font-awesome.css',$class_prefix);
    }
}
