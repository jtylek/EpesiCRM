<?php
$ret = '';
$values = $_POST['values'];
foreach($values as $v) {
	$ret .= $v;
}

print(json_encode(array('x'=>$ret.'x','y'=>$ret.'y')));
?>