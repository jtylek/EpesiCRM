<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$dir = dirname(__FILE__);
ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.$dir.'/3.2.9');

require_once('HTML/QuickForm.php');
?>
