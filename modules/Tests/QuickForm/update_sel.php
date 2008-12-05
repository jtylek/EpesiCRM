<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$ret = '';
$values = $_POST['values'];
foreach($values as $v) {
	$ret .= $v;
}

if(isset($_POST['parameters']->test))
	$ret = $_POST['parameters']->test.$ret;

//you can use $_POST['defaults'] also... but it's filled only with first request

print(json_encode(array('x'=>$ret.'x','y'=>$ret.'y')));
?>