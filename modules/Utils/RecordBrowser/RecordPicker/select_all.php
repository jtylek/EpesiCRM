<?php
if(!isset($_POST['select']) || !isset($_POST['tab']) || !isset($_POST['path']) || !isset($_POST['cid']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
require_once('../../../../include.php');
foreach ($_POST as $k=>$v)
	$_POST[$k] = trim($v,'"');
$path = $_POST['path'];
$tab = $_POST['tab'];
$crits = Module::static_get_module_variable($path, 'crits_stuff', null);
$order = Module::static_get_module_variable($path, 'order_stuff', null);
$element = Module::static_get_module_variable($path, 'element', null);
$func = Module::static_get_module_variable($path, 'format_func', null);
$browsed_records = Module::static_get_module_variable($path, 'rpicker_ind', null);
ModuleManager::load_modules();
if ($browsed_records===null || $crits===null || $order===null || $element===null || $func===null) die('alert(\'Invalid usage - variables not set (path - '.$path.', module vars - '.epesi::escapeJS(print_r($_SESSION['client']['__module_vars__'][$path],true)).')\');');

$tab_info = Utils_RecordBrowserCommon::init($tab);
$records = Utils_RecordBrowserCommon::get_records($tab, $crits, array(), $order, array());

$js = '';
$i = 0;
$max = 50;
foreach ($browsed_records as $r) {
	$js .= '$(\'leightbox_rpicker_'.$element.'_'.$r.'\').checked='.($_POST['select']?1:0).';';
}
foreach ($records as $row) {
	$i++;
	if ($i==$max+1) {
		$js .= 'alert(\''.Base_LangCommon::ts('Utils/RecordBrowser','Only first %d records were put in the multiselect element.', array($max)).'\');';
		break;
	}
	$js .= 'rpicker_move(\''.$element.'\','.$row['id'].',\''.(is_callable($func)?htmlspecialchars_decode(strip_tags(call_user_func($func, $row, true))):'').'\','.($_POST['select']?1:0).');';
}
// TODO: checkboxes - mark those
print($js);
?>