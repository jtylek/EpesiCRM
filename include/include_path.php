<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$dir = dirname(dirname(__FILE__));
ini_set('include_path',$dir.'/libs'.PATH_SEPARATOR.$dir.PATH_SEPARATOR.ini_get('include_path'));

?>
