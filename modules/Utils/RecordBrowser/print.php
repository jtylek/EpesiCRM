<?php
/**
 * Print recordset
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
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

if (!isset($_SESSION['client']['utils_recordbrowser'][$key])) {
    die('Invalid request');
}

$crits = $_SESSION['client']['utils_recordbrowser'][$key]['crits'];
$cols = $_SESSION['client']['utils_recordbrowser'][$key]['cols'];
$order = $_SESSION['client']['utils_recordbrowser'][$key]['order'];
$admin = $_SESSION['client']['utils_recordbrowser'][$key]['admin'];
$tab = $_SESSION['client']['utils_recordbrowser'][$key]['tab'];
$more_table_properties = $_SESSION['client']['utils_recordbrowser'][$key]['more_table_properties'];
$limit = $_SESSION['client']['utils_recordbrowser'][$key]['limit'];

ModuleManager::load_modules();
if (!Utils_RecordBrowserCommon::get_access($tab, 'print') && !Base_AclCommon::i_am_admin())
	die('Access denied');

set_time_limit(0);

$rb = ModuleManager::new_instance('Utils_RecordBrowser', null, 'print_rb');
$rb->construct($tab);
$rb->set_inline_display();
$rb->set_header_properties($more_table_properties);
$rb->disable_pagination();

ob_start();
$rb->show_data($crits, $cols, $order, $admin, false, true, $limit);
$html = ob_get_clean();

$limit_info = '';
if (is_array($limit)) {
    $offset = $limit['offset'];
    $per_page = $limit['numrows'];
    $start = $offset + 1;
    $end = $offset + $per_page;
    $total = Utils_RecordBrowserCommon::get_records_count($tab, $crits, $admin, $order);
    if ($end > $total)
        $end = $total;
    $limit_info = __('Records %s to %s of %s', array($start, $end, $total)) . "\n";
}

$tcpdf = Libs_TCPDFCommon::new_pdf();

$filters = Utils_RecordBrowserCommon::crits_to_words($tab, $crits, false);
$filters = str_replace(' and ', "\n", $filters);
$filters = str_replace(' is equal to', ':', $filters);

$subject = $limit_info . $filters;
Libs_TCPDFCommon::prepare_header($tcpdf, _V(DB::GetOne('SELECT caption FROM recordbrowser_table_properties WHERE tab=%s', array($tab))), $subject, false);
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
