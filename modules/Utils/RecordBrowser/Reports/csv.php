<?php
/**
 * Download file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.1
 * @Modified by praski
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
ModuleManager::load_modules();
$params = Utils_CommonDataCommon::get_array('System/csv_export_params');
$text_space_indicator   = $params['text_space_indicator'];
$text_space_separator   = $params['text_space_separator'];
$field_separator        = $params['field_separator'];
$charset                = $params['charset'];
$decimal_separator      = $params['decimal_separator'];

$csv = Module::static_get_module_variable($p,'csv',null);

if (headers_sent()){
    die('Some data has already been output to browser, can\'t send PDF file');
}
if ($csv===null){
    die('Invalid link');
}

$end_line_type          = $params['end_line_type'];
switch (strtoupper($end_line_type)){
    case ('LIN'):
    case ('LINUX'):
    case ('UNI'):
    case ('UNIX'):
        $end_line_type = "\n";
        break;
    case ('WIN'):                
    case ('WINDOWS'):
        $end_line_type = "\r\n";
        break;
    case ('MAC'):
    case ('MACINTOSH'):
        $end_line_type = "\r";
        break;
    default:
        $end_line_type = "\n";
        break;
}

header('Content-Type: text/csv');
//header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachment;filename="'.$filename.'.csv"');

$fp = fopen('php://output', 'w');
ob_start(function($buffer) use ($end_line_type,$text_space_indicator,$text_space_separator) {
    return EOLConversion($buffer, $end_line_type,$text_space_indicator,$text_space_separator);
});

foreach($csv as $array){
    $array_converted = array();
    foreach ($array as $str){
        $array_converted[] = UtfToCharset($str,$text_space_indicator,$text_space_separator,$field_separator,$charset);
    }
    fputcsv($fp, $array_converted, $field_separator, htmlspecialchars_decode($text_space_separator));
}
fclose($fp);

function UtfToCharset($str,$text_space_indicator,$text_space_separator,$field_separator,$charset){
    $ret = str_replace(array($field_separator,$text_space_separator,"\r\n","\n","\r"),array(' ','',' ',' ',' '),$str); //We do not want field separator char inside oryginal text and endof line.
    if (strtoupper($charset) <> 'UTF-8'){
        $ret =  charset_conversion($ret,$charset);
    }
    return $ret;
}
function charset_conversion($string,$charset){
    if (function_exists('iconv')) {
        $ret =  iconv('UTF-8', $charset.'//TRANSLIT', $string);
    }else{
        $ret = $string;
    }
    return $ret;
}

function EOLConversion($string, $end_line_type, $text_space_indicator, $text_space_separator) {
    if ($text_space_indicator){ //do not remove space indicator character
        $text_space_separator = '';
    }
    return str_replace(array(PHP_EOL,$text_space_separator), array($end_line_type,''), $string);
}
//PR190323 From oryginal RB CSV export
    function rb_csv_export_format_currency_value($v, $symbol)
    {
        static $currency_decimal_signs = null;
        static $currency_thou_signs;
        if ($currency_decimal_signs === null) {
            $currency_decimal_signs = DB::GetAssoc('SELECT symbol, decimal_sign FROM utils_currency');
            $currency_thou_signs = DB::GetAssoc('SELECT symbol, thousand_sign FROM utils_currency');
        }
        $v = str_replace(array($currency_thou_signs[$symbol],$currency_decimal_signs[$symbol]), array('',$decimal_separator), $v);
        return $v;
    }
