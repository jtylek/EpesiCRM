<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$ret = '';
$values = $_POST['values'];
foreach($values as $v) {
	if ($ret!='') {
		$ret = $v;
		break;
	}
	$ret = $v;
}

$contact = CRM_ContactsCommon::get_contact($ret);

$res = array();
$i = 1;
foreach(array('Mobile Phone', 'Work Phone', 'Home Phone') as $v) {
	$id = strtolower(str_replace(' ','_',$v));
	if ($contact[$id]) $res[$ret.'::'.$i] = '['.Base_LangCommon::ts('CRM/PhoneCall',$v).'] '.$contact[$id];
	$i++; 
}

print(json_encode($res));
?>