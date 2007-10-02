<?php
/**
 * Download file
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage fpdf
 */
$id = $_REQUEST['id'];
$pdf_id = $_REQUEST['pdf'];
$filename = $_REQUEST['filename'];
if(!isset($id) || !isset($pdf_id)) die('Invalid usage');

require_once('../../../include.php');

$buffer = Module::static_get_module_variable($pdf_id,'pdf',$id);
header('Content-Type: application/pdf');
if(headers_sent())
    die('Some data has already been output to browser, can\'t send PDF file');
header('Content-Length: '.strlen($buffer));
header('Content-disposition: inline; filename="'.$filename.'"');
echo $buffer;
?>
