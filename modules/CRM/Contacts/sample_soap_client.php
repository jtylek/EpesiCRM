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
require_once 'SOAP/Client.php';
$wsdl_url =
  'http://localhost/epesi-trunk/modules/CRM/Contacts/soap.php?wsdl';
$WSDL     = new SOAP_WSDL($wsdl_url);
$client   = $WSDL->getProxy();
$wynik = $client->get_data("admin","admin",0);
if(PEAR::isError($wynik))
	die($wynik->getMessage());
print_r($wynik);
?>

