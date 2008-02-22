<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$ret = '';
$values = $_POST['values'];
foreach($values as $v) {
	$ret = $v;
	break;
}
$params = array();
foreach($_POST['parameters'] as $k=>$v) {
	$params[$k] = $v;
}
$contacts = CRM_ContactsCommon::get_contacts(array('company_name'=>array($ret)));

$res = array();
if (isset($params['no_company']) && $params['no_company']) {
	foreach($contacts as $k=>$v){
		$res[$v['id']] = CRM_ContactsCommon::contact_format_no_company($v, true);
	}
} else {
	foreach($contacts as $k=>$v){
		$res[$v['id']] = CRM_ContactsCommon::contact_format_default($v, true);
	}
}
asort($res);
if (!isset($params['required']) || !$params['required'])
	$res = array(''=>'--')+$res;
print(json_encode($res));
?>