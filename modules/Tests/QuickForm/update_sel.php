<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$ret = '';
$values = $_POST['values'];
foreach($values as $v) {
	$ret .= $v;
}

if(isset($_POST['parameters']->test))
	$ret = $_POST['parameters']->test.$ret;

print(json_encode(array('x'=>$ret.'x','y'=>$ret.'y')));
?>