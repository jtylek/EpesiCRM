<?php
/**
 * EPESI Compatibility check.
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */
$fullscreen = !defined("_VALID_ACCESS");
!$fullscreen || define("_VALID_ACCESS", true);

define('CID', false);
$config = file_exists('data/config.php');
if ($config) {
	include_once('include.php');
	ModuleManager::create_load_priority_array();
	ModuleManager::create_common_cache();
	ModuleManager::load_modules();
}
if ($config && class_exists('Base_AclCommon') && !Base_AclCommon::i_am_sa()) {
	require_once('admin/Authorization.php');
	$auth = AdminAuthorization::form();
	if ($auth) {
		print($auth);
		die();
	}
}

if (class_exists('Base_LangCommon'))
	Base_LangCommon::update_translations();
if (class_exists('Base_ThemeCommon'))
	Base_ThemeCommon::create_cache();
if (class_exists('ModuleManager'))
	ModuleManager::create_load_priority_array();


$html = '';
$checks = array();

// checking:
// DB - creating tables, selects, locking tables
// Strict Standards
// error display
// file_get_contents() [function.file-get-contents]: URL file-access is disabled in the server configuration
// memory check
// max_execution_time setting (safe mode)
// upload_max_filesize
// Libs: ZIP, curl


// ********************* DATABASE ***********************
if ($config) {
	ob_start();
	$exists = @DB::GetOne('SELECT 1 FROM modules WHERE NOT EXISTS (SELECT 1 FROM test WHERE 1=2)');
	if (!$exists) {
		$create = @DB::CreateTable('test', 'id I4 AUTO KEY', array('constraints'=>''));
		$alter = @DB::Execute('ALTER TABLE test ADD COLUMN field_name INTEGER');
	} else $alter = $create = null;
	$insert = @DB::Execute('INSERT INTO test (id) VALUES (1)');
	$update = @DB::Execute('UPDATE test SET id=1 WHERE id=1');
	if(DATABASE_DRIVER=='mysqlt') {
		$lock = DB::GetOne('SELECT GET_LOCK(%s,%d)',array('test',ini_get('max_execution_time')));
		$lock &= !DB::GetOne('SELECT IS_FREE_LOCK(%s)',array('test'));
		$end_lock = DB::GetOne('SELECT RELEASE_LOCK(%s)',array('test'));
	}
	$delete = @DB::Execute('DELETE FROM test');
	$drop = @DB::DropTable('test');
	ob_end_clean();
	$db_tests = array();
	if ($create===null)
		$db_tests[] = array('label'=>'CREATE permission', 'status'=>'Unknown', 'severity'=>1);
	else
		$db_tests[] = array('label'=>'CREATE permission', 'status'=>$create?'OK':'Failed', 'severity'=>$create?0:2);
	if ($alter===null)
		$db_tests[] = array('label'=>'ALTER permission', 'status'=>'Unknown', 'severity'=>1);
	else
		$db_tests[] = array('label'=>'ALTER permission', 'status'=>$alter?'OK':'Failed', 'severity'=>$alter?0:2);
	$db_tests[] = array('label'=>'INSERT permission', 'status'=>$insert?'OK':'Failed', 'severity'=>$insert?0:2);
	$db_tests[] = array('label'=>'UPDATE permission', 'status'=>$update?'OK':'Failed', 'severity'=>$update?0:2);
	if(DATABASE_DRIVER=='mysqlt')
		$db_tests[] = array('label'=>'LOCK permission', 'status'=>$lock?'OK':'Failed', 'severity'=>$lock?0:2);
	$db_tests[] = array('label'=>'DELETE permission', 'status'=>$delete?'OK':'Failed', 'severity'=>$delete?0:2);
	$db_tests[] = array('label'=>'DROP permission', 'status'=>$drop?'OK':'Failed', 'severity'=>$drop?0:2);

	$checks[] = array('label'=>'Database permissions', 'tests'=>$db_tests, 'solution'=>'http://forum.epesibim.com');
} else {
	
}
// ********************* DATABASE ***********************

// ********************* ERRORS ***********************
$err = error_reporting();
$strict = (($err | E_STRICT) == $err);
$display = ini_get('display_errors');

$error_tests = array();
$error_tests[] = array('label'=>'Strict errors reporting', 'status'=>!$strict?'Disabled':'Enabled', 'severity'=>!$strict?0:2);
$error_tests[] = array('label'=>'Error display', 'status'=>$display?'On':'Off', 'severity'=>$display?0:1);

$checks[] = array('label'=>'Error reporting', 'tests'=>$error_tests, 'solution'=>'http://forum.epesibim.com');
// ********************* ERRORS ***********************

// ********************* EXECUTION SETTINGS ***********************
$mem = ini_get('memory_limit');
if (strpos($mem, 'M')===false) $mem_s = 2;
else {
	$mem = str_replace('M', '', $mem);
	if ($mem<32) $mem_s = 2;
	elseif ($mem==32) $mem_s = 1;
	else $mem_s = 0;
	$mem .= ' MB';
}

$upload_size = ini_get('upload_max_filesize');
if (strpos($upload_size, 'M')===false) $upload_size_s = 2;
else {
	$upload_size = str_replace('M', '', $upload_size);
	if ($upload_size<8) $upload_size_s = 2;
	elseif ($upload_size==8) $upload_size_s = 1;
	else $upload_size_s = 0;
	$upload_size .= ' MB';
}

$post_size = ini_get('post_max_size');
if (strpos($post_size, 'M')===false) $post_size_s = 2;
else {
	$post_size = str_replace('M', '', $post_size);
	if ($post_size<16) $post_size_s = 2;
	elseif ($post_size==16) $post_size_s = 1;
	else $post_size_s = 0;
	$post_size .= ' MB';
}

if (version_compare(PHP_VERSION, '5.4') >= 0)
	$safe_mode = false;
else
	$safe_mode = ini_get('safe_mode');

$lang_code = 'pl';
setlocale(LC_ALL,$lang_code.'_'.strtoupper($lang_code).'.utf8',
		$lang_code.'_'.strtoupper($lang_code).'.UTF-8',
		$lang_code.'.utf8',
		$lang_code.'.UTF-8','polish');
setlocale(LC_NUMERIC,'en_EN.utf8','en_EN.UTF-8','en_US.utf8','en_US.UTF-8','C','POSIX','en_EN','en_US','en','en.utf8','en.UTF-8','english');
$str = print_r(1.1,true);

if (strpos($str,'.') === false) {
	$loc = 'ERROR';
	$loc_s = 1;
} else {
	$loc = 'OK';
	$loc_s = 0;
}

$tests = array();
$tests[] = array('label'=>'Safe mode', 'status'=>!$safe_mode?'Disabled':'Enabled', 'severity'=>!$safe_mode?0:2);
$tests[] = array('label'=>'Memory limit', 'status'=>$mem, 'severity'=>$mem_s);
$tests[] = array('label'=>'Upload file size', 'status'=>$upload_size, 'severity'=>$upload_size_s);
$tests[] = array('label'=>'POST max size', 'status'=>$post_size, 'severity'=>$post_size_s);
$tests[] = array('label'=>'Locale settings', 'status'=>$loc, 'severity'=>$loc_s);

$checks[] = array('label'=>'Script execution', 'tests'=>$tests, 'solution'=>'http://forum.epesibim.com');
// ********************* EXECUTION SETTINGS ***********************

// ********************* FEATURES ***********************

$zip = class_exists('ZipArchive');
$curl = extension_loaded('curl');
$remote_fgc = ini_get('allow_url_fopen');
$modules_writable = is_writable('modules');

$error_tests = array();
$error_tests[] = array('label'=>'Remote file_get_contents()', 'status'=>$remote_fgc?'Enabled':'Disabled', 'severity'=>$remote_fgc?0:2);
$error_tests[] = array('label'=>'ZIPArchive library loaded', 'status'=>$zip?'Loaded':'Not found', 'severity'=>$zip?0:1);
$error_tests[] = array('label'=>'cURL library loaded', 'status'=>$curl?'Loaded':'Not found', 'severity'=>$curl?0:1);
$error_tests[] = array('label'=>'Modules directory writable', 'status'=>$modules_writable?'Yes':'No', 'severity'=>$modules_writable?0:1);


$checks[] = array('label'=>'Error reporting', 'tests'=>$error_tests, 'solution'=>'http://forum.epesibim.com');
// ********************* FEATURES ***********************

foreach ($checks as $c) {
	$html .= '<strong>'.$c['label'].'</strong><br>';
	$solution = false;
	foreach ($c['tests'] as $t) {
		switch ($t['severity']) {
			case 0: $color = '#00CC00'; break;
			case 1: $color = '#CCAA00'; $solution = true; break;
			case 2: $color = 'red'; $solution = true; break;
		}
		$html .= '<span style="font-weight:bold;float:right;margin-right:100px;color:'.$color.'">'.$t['status'].'</span>';
		$html .= '<span style="margin-left:40px;">'.$t['label'].'</span>';
		$html .= '<br>';
	}
	if ($solution) {
		//$html .= 'Solution available here: <a target="_blank" href="'.$c['solution'].'">'.$c['solution'].'</a>';
		//$html .= '<br>';
	}
	$html .= '<br>';
}

$html .= '<br><br>';
$html .= '<font size=-2>';
$html .= 'Legend:<br>';
$html .= '<span style="color:#00CC00;">Green</span> - matches EPESI requirements<br>';
$html .= '<span style="color:#CCAA00;">Yellow</span> - shouldn\'t prevent EPESI from running, but it\'s recommended to change the settings<br>';
$html .= '<span style="color:red;">Red</span> - check failed, it\'s necessary to change the settings<br>';
$html .= '</font>';

if ($fullscreen) {
	if (class_exists('Utils_FrontPageCommon'))
		Utils_FrontPageCommon::display('EPESI Compatibility check', $html);
	else
		print('<div style="width:600px;margin:0 auto;">'.$html.'</div>');
} else {
	print($html);
}

?>
