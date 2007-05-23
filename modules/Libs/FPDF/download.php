<?php
$id = $_REQUEST['id'];
$pdf_id = $_REQUEST['pdf'];
$filename = $_REQUEST['filename'];
if(!isset($id) || !isset($pdf_id)) die('Invalid usage');

session_start();
$buffer = $_SESSION['cl'.$id]['__module_vars__'][$pdf_id]['pdf'];

header('Content-Type: application/pdf');
if(headers_sent())
    die('Some data has already been output to browser, can\'t send PDF file');
header('Content-Length: '.strlen($buffer));
header('Content-disposition: inline; filename="'.$filename.'"');
echo $buffer;
?>
