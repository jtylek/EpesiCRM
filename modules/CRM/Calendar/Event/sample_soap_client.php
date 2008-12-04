<?php
/**
 * Calendar event module
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar-event
 */
require_once 'SOAP/Client.php';
$wsdl_url =
  'http://localhost/epesi-trunk/modules/CRM/Calendar/Event/soap.php?wsdl';
$WSDL     = new SOAP_WSDL($wsdl_url);
$client   = $WSDL->getProxy();
$wynik = $client->get_data("admin","admin",0);
if(PEAR::isError($wynik))
	die($wynik->getMessage());
print_r($wynik);
?>

