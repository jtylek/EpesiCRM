<?php
$ret = '';
$values = $_POST['values'];
foreach($values as $v) {
	$ret = $v;
	break;
}
$contacts = CRM_ContactsCommon::get_contacts(array('company_name'=>array($ret)));

$res = array();
foreach($contacts as $k=>$v){
	$res[$v['id']] = CRM_ContactsCommon::contact_format_no_company($v, true);
}
print(json_encode($res));
?>