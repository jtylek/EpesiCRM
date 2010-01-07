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
		foreach(array('Mobile Phone', 'Work Phone', 'Home Phone') as $v) {
			$id = strtolower(str_replace(' ','_',$v));
			if ($contact[$id]) $res[$i] = Base_LangCommon::ts('CRM/PhoneCall',$v).': '.$contact[$id];
			$i++; 
		}
	} else {
		$company = CRM_ContactsCommon::get_company($id);	
		$res = array(4=>Base_LangCommon::ts('CRM/PhoneCall','Phone').': '.$company['phone']);
	}
	
	print(json_encode($res));
}
?>