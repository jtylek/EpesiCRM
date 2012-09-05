<?php
/**
 * Print recordset
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage RecordBrowser
 */
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['key'])) die('Invalid usage - missing param');
$cid = $_REQUEST['cid'];
$key = $_REQUEST['key'];

define('CID', $cid);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');

$crits = $_SESSION['client']['utils_recordbrowser'][$key]['crits'];
$cols = $_SESSION['client']['utils_recordbrowser'][$key]['cols'];
$order = $_SESSION['client']['utils_recordbrowser'][$key]['order'];
$admin = $_SESSION['client']['utils_recordbrowser'][$key]['admin'];
$tab = $_SESSION['client']['utils_recordbrowser'][$key]['tab'];
$more_table_properties = $_SESSION['client']['utils_recordbrowser'][$key]['more_table_properties'];

ModuleManager::load_modules();

set_time_limit(0);

$rb = ModuleManager::new_instance('Utils_RecordBrowser', null, 'print_rb');
$rb->construct($tab);
$rb->set_inline_display();
$rb->set_header_properties($more_table_properties);

ob_start();
$rb->show_data($crits, $cols, $order, $admin, false, true);
$html = ob_get_clean();

$tcpdf = Libs_TCPDFCommon::new_pdf();

/*$filters = array();
foreach ($crits as $k=>$v) {
	$field = trim($k,'(|:"~<>=');
	$args = Utils_RecordBrowserCommon::$table_rows[Utils_RecordBrowserCommon::$hash[$field]];
	$val = Utils_RecordBrowserCommon::get_val($tab, $field, array($field=>$v), true, $args);
	$filters[] = $args['name'].': '.$val;
}
$filters = implode('   ', $filters);
$filters = str_replace('&nbsp;',' ',$filters);*/
$filters = implode(' ',Utils_RecordBrowserCommon::crits_to_words($tab, $crits));
$filters = strip_tags($filters);
$filters = str_replace('&nbsp;', ' ', $filters);
$filters = str_replace(' and ', "\n", $filters);
$filters = str_replace(' is equal to', ':', $filters);
	
Libs_TCPDFCommon::prepare_header($tcpdf, _V(DB::GetOne('SELECT caption FROM recordbrowser_table_properties WHERE tab=%s', array($tab))), $filters, false);
Libs_TCPDFCommon::add_page($tcpdf);

Libs_TCPDFCommon::SetFont($tcpdf, Libs_TCPDFCommon::$default_font, '', 6);

$html = Libs_TCPDFCommon::stripHTML(str_replace(array('<br>','&nbsp;'),array('<br/>',' '),$html));
Libs_TCPDFCommon::writeHTML($tcpdf, $html, false);

$buffer = Libs_TCPDFCommon::output($tcpdf);

header('Content-Type: application/pdf');
header('Content-Length: '.strlen($buffer));
header('Content-disposition: inline; filename="recordset_'.$tab.'.pdf"');

print($buffer);

?>
