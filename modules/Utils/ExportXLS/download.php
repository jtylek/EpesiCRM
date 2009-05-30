<?php
/**
 * Download file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage exportxls
 */
if(!isset($_REQUEST['id']) || !isset($_REQUEST['xls']) || !isset($_REQUEST['args']) || !isset($_REQUEST['filename'])) die('Invalid usage');
$id = $_REQUEST['id'];
$args = $_REQUEST['args'];
$xls_id = $_REQUEST['xls'];
$filename = $_REQUEST['filename'];

define('CID', $id);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');

$fn = Module::static_get_module_variable($xls_id,'callback',null);

if (headers_sent())
    die('Some data has already been output to browser, can\'t send PDF file');
if ($fn===null)
	die('Invalid link');
ModuleManager::load_modules();
if (!is_callable($fn))
	die('Invalid callback');
$buffer = call_user_func_array($fn, $args);
header('Content-Type: application/xls');
header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachement; filename="'.$filename.'"');
header('Content-Transfer-Encoding: binary');
echo $buffer;
?>
