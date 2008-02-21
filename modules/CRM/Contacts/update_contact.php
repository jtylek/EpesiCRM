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
if (isset($_POST['parameters']['no_company']) && $_POST['parameters']['no_company']) {
	foreach($contacts as $k=>$v){
		$res[$v['id']] = CRM_ContactsCommon::contact_format_default($v, true);
	}
} else {
	foreach($contacts as $k=>$v){
		$res[$v['id']] = CRM_ContactsCommon::contact_format_no_company($v, true);
	}
}
asort($res);
if (!isset($_POST['parameters']['required']) || !$_POST['parameters']['required'])
	$res = array(' '=>'--')+$res;
print(json_encode($res));
?>