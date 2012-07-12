<?php
/**
 * Download file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage RecordBrowser
 */
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['path']) || !isset($_REQUEST['tab']) || !isset($_REQUEST['admin'])) die('Invalid usage - missing param');
$cid = $_REQUEST['cid'];
$tab = $_REQUEST['tab'];
$admin = $_REQUEST['admin'];
$path = $_REQUEST['path'];

define('CID', $cid);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
$crits = Module::static_get_module_variable($path, 'crits_stuff', null);
$order = Module::static_get_module_variable($path, 'order_stuff', null);
if ($crits===null || $order===null) {
	$crits = $order = array();
}
ModuleManager::load_modules();
if (!Base_AclCommon::i_am_admin())
	die('Invalid usage - access denied');

set_time_limit(0);
$tab_info = Utils_RecordBrowserCommon::init($tab);
$records = Utils_RecordBrowserCommon::get_records($tab, $crits, array(), $order, array(), $admin);

header('Content-Type: text/csv');
//header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachement; filename="'.$tab.'_export_'.date('Y_m_d__h_i_s').'.csv"');
if (headers_sent())
    die('Some data has already been output to browser, can\'t send the file');
$cols = array('Record ID');
foreach ($tab_info as $v) {
	$cols[] = _V($v['name']);
	if ($v['style']=='currency') $cols[] = _V($v['name']).' - '.__('Currency');
}
$f = fopen('php://output','w');
//fwrite($f, "\xEF\xBB\xBF");
fputcsv($f, $cols);
$currency_codes = DB::GetAssoc('SELECT symbol, code FROM utils_currency');

function rb_csv_export_format_currency_value($v, $symbol) {
	static $currency_decimal_signs=null;
	static $currency_thou_signs;
	if ($currency_decimal_signs===null) {
		$currency_decimal_signs = DB::GetAssoc('SELECT symbol, decimal_sign FROM utils_currency');
		$currency_thou_signs = DB::GetAssoc('SELECT symbol, thousand_sign FROM utils_currency');
	}
	$v = str_replace($currency_thou_signs[$symbol], '', $v);
	$v = str_replace($currency_decimal_signs[$symbol], '.', $v);
	return $v;
}

foreach ($records as $r) {
	$rec = array($r['id']);
	foreach ($tab_info as $v) {
		ob_start();
		$val = Utils_RecordBrowserCommon::get_val($tab, str_replace('%%','%',$v['name']), $r, true, $v);
		ob_end_clean();
		$val = str_replace('&nbsp;',' ',htmlspecialchars_decode(strip_tags(preg_replace('/\<[Bb][Rr]\/?\>/',"\n",$val))));
		if ($v['style']=='currency') {
			$val = str_replace('&nbsp;','_',$val);
			$val = explode(';', $val);
			if (isset($val[1])) {
				$final = array();
				foreach ($val as $v) {
					$v = explode('_',$v);
					if (isset($v[1])) 
						$final[] = rb_csv_export_format_currency_value($v[0], $v[1]).' '.$currency_codes[$v[1]];
				} 
				$rec[] = implode('; ',$final);
				$rec[] = '---';
				continue;
			}
			$val = explode('_', $val[0]);
			if (!isset($val[1])) {
				$rec[] = '0';
				$rec[] = '---';
				continue;
			}
			if(isset($currency_codes[$val[0]])) {
				$tmp = $val[1];
				$val[1] = $val[0];
				$val[0] = $tmp;
			} elseif(!isset($currency_codes[$val[0]])) { //there is no currency code? skip parsing
				$rec[] = $val[0];
				$rec[] = $val[1];
				continue;
			}
			$rec[] = rb_csv_export_format_currency_value($val[0],$val[1]);
			$rec[] = isset($currency_codes[$val[1]])?$currency_codes[$val[1]]:$val[1];
		} else {
			$rec[] = $val;
		}
	}
	fputcsv($f, $rec);
}
?>
