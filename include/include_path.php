<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$delimiter = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')?';':':';
$dir = dirname(dirname(__FILE__));
ini_set('include_path',$dir.'/libs'.$delimiter.$dir.$delimiter.ini_get('include_path'));

?>
