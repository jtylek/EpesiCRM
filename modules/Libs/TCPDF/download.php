<?php
/**
 * Download file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage tcpdf
 */
if(!isset($_REQUEST['id']) || !isset($_REQUEST['pdf']) || !isset($_REQUEST['filename'])) die('Invalid usage');
$id = $_REQUEST['id'];
$pdf_id = $_REQUEST['pdf'];
$filename = $_REQUEST['filename'];

define('CID', $id);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');

$fn = Module::static_get_module_variable($pdf_id,'pdf',null);

if (headers_sent())
    die('Some data has already been output to browser, can\'t send PDF file');
if ($fn===null)
	die('Invalid link');
if (!file_exists($fn))
	die('This file is no longer available, please refresh the page and download the file again');
$buffer = file_get_contents($fn);
header('Content-Type: application/pdf');
header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachement; filename="'.$filename.'"');
echo $buffer;
?>
