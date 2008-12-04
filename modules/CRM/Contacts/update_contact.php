<?php
/**
 * CRM Contacts class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts
 */
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
$callback = explode('::', $params['format']);
if (!is_array($_POST['defaults'])) {
	if ($_POST['defaults']!='') $_POST['defaults'] = array($_POST['defaults']); else $_POST['defaults']=array();
} 
$ext_rec = array_flip($_POST['defaults']);
foreach($contacts as $k=>$v){
	$res[$v['id']] = call_user_func($callback, $v, true);
	if (isset($_POST['defaults'])) unset($ext_rec[$v['id']]); 
}
foreach($ext_rec as $k=>$v) {
	$c = CRM_ContactsCommon::get_contact($k);
	$res[$k] = call_user_func($callback, $c, true);
}
natcasesort($res);
if (!isset($params['required']) || !$params['required'])
	$res = array(''=>'---')+$res;
print(json_encode($res));
?>