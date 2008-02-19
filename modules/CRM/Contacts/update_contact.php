<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$ret = '';
$values = $_POST['values'];
foreach($values as $v) {
	$ret = $v;
	break;
}
$contacts = CRM_ContactsCommon::get_contacts(array('company_name'=>array($ret)));

$res = array();
foreach($contacts as $k=>$v){
	$res[$v['id']] = CRM_ContactsCommon::contact_format_default($v, true);
}
asort($res);
print(json_encode($res));
?>