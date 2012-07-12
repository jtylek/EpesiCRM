<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage recordbrowser
 */
if (!isset($_POST['id']) || !isset($_POST['tab']) || !isset($_POST['mode']) || !isset($_POST['element']) || !isset($_POST['cid']))
	die('Invalid request: '.print_r($_POST,true));

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if (!Acl::is_user()) die('alert("Unauthorized access");');

$id = json_decode($_POST['id']);
$element = json_decode($_POST['element']);
$tab = json_decode($_POST['tab']);
$mode = json_decode($_POST['mode']);
if (isset($_POST['form_name'])) $form_name = json_decode($_POST['form_name']);
else $form_name = '';

if (!is_numeric($id) || !is_string($element)) 
	die('Invalid request: '.$element.' & '.$id);

if ($mode=='submit') {
	$form = ModuleManager::new_instance('Libs_QuickForm', null, 'grid_form');
	$form->construct(null,'','',null,$form_name);
	$value = json_decode($_POST['value']);
	parse_str(urldecode($value), $output);
	$output['_qf__'.$form->get_name()] = true;
	$_REQUEST = $_GET = $_POST = $output;
}

$form = ModuleManager::new_instance('Libs_QuickForm', null, 'grid_form');
$rb = ModuleManager::new_instance('Utils_RecordBrowser', null, 'grid_rb');
$form->construct(null,'','',null,$form_name);
$rb->construct($tab);
$rb->init();
$record = Utils_RecordBrowserCommon::get_record($tab, $id);
$rb->record = $record;

ob_start();
$rb->view_fields_permission = $rb->get_access('edit', $record);
if(!$rb->view_fields_permission[$element]) {
	ob_end_clean();
	print('alert(\''.__('This field is not editable').'\');');
	print('setTimeout("grid_disable_edit(\''.$element.'\',\''.$id.'\');",100);');
	die();
}
$rb->prepare_view_entry_details($record, 'edit', $id, $form, array($element=>true), true);
$more_html = ob_get_clean();

if ($mode=='submit') {// && $form->validate()) {
	$form->validate();
	$vals = $form->exportValues();
	if (!isset($vals['__grid_'.$element])) trigger_error(print_r($vals,true));
	$value = $vals['__grid_'.$element];
	
	Utils_RecordBrowserCommon::update_record($tab, $id, array($element=>$value));
	$record[$element] = $value;

	$form = ModuleManager::new_instance('Libs_QuickForm', null, 'grid_form');
	$rb = ModuleManager::new_instance('Utils_RecordBrowser', null, 'grid_rb');
	$form->construct();
	$rb->construct($tab);
	$rb->init();
	$record = Utils_RecordBrowserCommon::get_record($tab, $id);
	$record['__grid_'.$element] = $value;
	$rb->record = $record;

	$rb->view_fields_permission = $rb->get_access('view', $record);
	$rb->prepare_view_entry_details($record, 'view', $id, $form, array($element=>true), true);

	$renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty();
	$form->accept($renderer);
	$data = $renderer->toArray();

	$html = $data['__grid_'.$element]['html'];
//	$html = print_r($data,true);
	if ($form_name=='') {
		$html = '<form '.$data['attributes'].'>'.$html.'</form>';
	}

	print('$("grid_value_field_'.$element.'_'.$id.'").innerHTML = \''.Epesi::escapeJS($html).'\';');
	return;
}
ob_start();

//$form->updateAttributes(array('onsubmit'=>'return false;'));

$renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty();
$form->accept($renderer);
$data = $renderer->toArray();
$html = $data['__grid_'.$element]['error'].$data['__grid_'.$element]['html'];
if ($form_name=='') {
	$html = '<form '.$data['attributes'].'>'.$data['hidden'].$html.'</form>';
	$form_name = $form->get_name();
}

$more_html .= ob_get_clean();

$html .= $more_html;

print('$("grid_form_field_'.$element.'_'.$id.'").innerHTML = \''.Epesi::escapeJS($html).'\';');
print('grid_edit_form_name = "'.$form_name.'";');

preg_match_all('/name=\"([^\"]+)\"/', $data['__grid_'.$element]['html'], $matches);
if (isset($matches[1][0])) {
	$v = $matches[1][0];
	$js = 
		'el = document.getElementsByName("'.$v.'")[0];'.
		'if(el){'.
			'if(!el.id)el.id="grid_'.md5($v).'";';
//	if (count($matches[1])==1)
//		$js .= 'Event.observe(el.id,"blur",function(){setTimeout("grid_disable_edit(\''.$element.'\',\''.$id.'\');", 1500);});';
	$js .=
			'focus_by_id(el.id);'.
		'}';
	print($js);
}

/*
$_SESSION['client']['__loaded_jses__'] = array();

$out_js = Epesi::get_jses();
foreach($out_js as $js) {
	print('Epesi.load_js(\''.Epesi::escapeJS($js,false).'\');');
}
*/
print(Epesi::get_eval_jses());
?>
