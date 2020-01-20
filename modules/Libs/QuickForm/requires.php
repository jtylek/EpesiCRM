<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$dir = dirname(__FILE__);
ob_start();
$ret = @readfile('HTML/QuickForm.php',true);
ob_get_clean();
if($ret===false) //more efficient... less invalid requests by php, but not work with QuickForm installed in pear
	ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.$dir.'/3.2.14-php7');
else
	ini_set('include_path',$dir.'/3.2.14-php7'.PATH_SEPARATOR.ini_get('include_path'));

require_once('HTML/QuickForm.php');
?>
