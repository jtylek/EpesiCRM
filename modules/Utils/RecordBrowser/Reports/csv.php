<?php
/**
 * Download file
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-libs
 * @subpackage tcpdf
 */
if(!isset($_REQUEST['id']) || !isset($_REQUEST['p']) || !isset($_REQUEST['filename'])) die('Invalid usage');
$id = $_REQUEST['id'];
$p = $_REQUEST['p'];
$filename = $_REQUEST['filename'];

define('CID', $id);
define('READ_ONLY_SESSION',true);
require_once('../../../../include.php');

$csv = Module::static_get_module_variable($p,'csv',null);

if (headers_sent())
    die('Some data has already been output to browser, can\'t send PDF file');
if ($csv===null)
	die('Invalid link');
header('Content-Type: text/csv');
//header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachment;filename="'.$filename.'.csv"');

$fp = fopen('php://output', 'w');
foreach($csv as $array)
    fputcsv($fp, $array);
fclose($fp);