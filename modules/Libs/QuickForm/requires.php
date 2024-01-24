<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$dir = dirname(__FILE__);
ob_start();
$ret = @readfile('autoload.php',true);
ob_get_clean();
if($ret===false) //more efficient... less invalid requests by php, but not work with QuickForm installed in pear
	ini_set('include_path',ini_get('include_path').	PATH_SEPARATOR.$dir.'/vendor');
else
	ini_set('include_path',$dir.'/vendor'.PATH_SEPARATOR.ini_get('include_path'));

// require_once('HTML/QuickForm.php');
?>
