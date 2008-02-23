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
foreach($contacts as $k=>$v){
	$res[$v['id']] = call_user_func(explode('::', $params['format']), $v, true);
}
asort($res);
if (!isset($params['required']) || !$params['required'])
	$res = array(''=>'--')+$res;
print(json_encode($res));
?>