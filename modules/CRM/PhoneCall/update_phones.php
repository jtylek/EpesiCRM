<?php
/**
 * CRM Phone Call Class
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage phonecall
 */
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

if ($ret==='') print(json_encode(array()));
else {
	list($r,$id) = explode(':',$ret);
	if ($r=='P') {
		$contact = CRM_ContactsCommon::get_contact($id);	
		$res = array();
		$i = 1;
		foreach(array('mobile_phone'=>__('Mobile Phone'), 'work_phone'=>__('Work Phone'), 'home_phone'=>__('Home Phone')) as $id=>$v) {
			if (isset($contact[$id]) && $contact[$id]) $res[$i] = $v.': '.$contact[$id];
			$i++; 
		}
	} else {
		$company = CRM_ContactsCommon::get_company($id);	
		$res = array(4=>__('Phone').': '.$company['phone']);
	}
	
	print(json_encode($res));
}
?>