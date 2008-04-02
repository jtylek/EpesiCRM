<?php
/**
 * Epesi core updater.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

include_once('include/misc.php');

function install_default_theme_common_files($dir,$f) {
	if(class_exists('ZipArchive')) {
		$zip = new ZipArchive;
		if ($zip->open($dir.$f.'.zip') == 1)
			$zip->extractTo('data/Base_Theme/templates/default/');
		return;
	}
	mkdir('data/Base_Theme/templates/default/'.$f);
	$content = scandir($dir.$f);
	foreach ($content as $name){
		if ($name == '.' || $name == '..') continue;
		$path = $dir.$f.'/'.$name;
		if (is_dir($path))
			install_default_theme_common_files($dir,$f.'/'.$name);
		else
			copy($path,'data/Base_Theme/templates/default/'.$f.'/'.$name);
	}
}

function themeup(){
	$data_dir = 'data/Base_Theme/templates/default/';
	$content = scandir($data_dir);
	foreach ($content as $name){
		if ($name == '.' || $name == '..') continue;
		recursive_rmdir($data_dir.$name);
	}

	$ret = DB::Execute('SELECT * FROM modules');
	while($row = $ret->FetchRow()) {
		$directory = 'modules/'.str_replace('_','/',$row[0]).'/theme_'.$row['version'];
		if (!is_dir($directory)) $directory = 'modules/'.str_replace('_','/',$row[0]).'/theme';
		$mod_name = $row[0];
		$data_dir = 'data/Base_Theme/templates/default/';
		if (!is_dir($directory)) continue;
		$content = scandir($directory);
		foreach ($content as $name){
			if($name == '.' || $name == '..' || ereg('^[\.~]',$name)) continue;
			recursive_copy($directory.'/'.$name,$data_dir.$mod_name.'__'.$name);
		}
	}

	install_default_theme_common_files('modules/Base/Theme/','images');
}

$versions = array('0.8.5','0.8.6','0.8.7','0.8.8','0.8.9','0.8.10','0.8.11','0.9.0','0.9.1','0.9.9beta1','0.9.9beta2','1.0.0rc1');

/****************** 0.8.5 to 0.8.6 **********************/
function update_from_0_9_9beta1_to_0_9_9beta2() {
	trigger_error('You cannot update to 0.9.9beta2. This version is next "make world".',E_USER_ERROR);
}

function update_from_0_9_9beta2_to_1_0_0rc1() {
	define('CID',false);
	require_once('include.php');
	//attachment
	ob_start();
	ModuleManager::load_modules();
	ModuleManager::install('Utils_Attachment_Administrator');
	ob_end_clean();
	//RB 1.01
	DB::CreateTable('recordbrowser_addon',
			'tab C(64),'.
			'module C(128),'.
			'func C(128),'.
			'label C(64)',
			array('constraints'=>', PRIMARY KEY(module, func)'));
	$ret = DB::Execute('SELECT tab FROM recordbrowser_table_properties');
	while($row = $ret->FetchRow()) {
		$ret2 = DB::Execute('SELECT module, func, label FROM '.$row['tab'].'_addon');
		while($row2 = $ret2->FetchRow()) {
			DB::Execute('INSERT INTO recordbrowser_addon (tab, module, func, label) VALUES (%s, %s, %s, %s)', array($row['tab'], $row2['module'], $row2['func'], $row2['label']));
		}
		DB::DropTable($row['tab'].'_addon');
	}
	
	//RB 1.02
	$ret = DB::Execute('SELECT tab FROM recordbrowser_table_properties');
	while($row = $ret->FetchRow()) {
		DB::Execute('ALTER TABLE '.$row['tab'].'_field ADD COLUMN style VARCHAR(64)');
		DB::Execute('UPDATE '.$row['tab'].'_field SET style=type WHERE type=%s or type=%s', array('timestamp','currency'));
	}
	
	//dashboard colors
	$q = DB::dict()->AddColumnSQL('base_dashboard_applets','color I2 DEFAULT 0');
	DB::Execute($q[0]);
	$q = DB::dict()->AddColumnSQL('base_dashboard_default_applets','color I2 DEFAULT 0');
	DB::Execute($q[0]);

	//tasks
	if(ModuleManager::is_installed('Utils_Tasks')>=0) {
		$q = DB::dict()->DropColumnSQL('utils_tasks_task','parent_module');
		DB::Execute($q[0]);
	}
	
	themeup();
}

//=========================================================================

try {
$cur_ver = Variable::get('version');
} catch(Exception $s) {
$cur_ver = '0.8.5';
}
$go=false;
$last_ver = '';
foreach($versions as $v) {
	$x = str_replace('.','_',$v);
	if($go) {
		if(is_callable('update_from_'.$last_ver.'_to_'.$x)) {
//			print('Update from '.$last_ver.' to '.$x.'<br>');
			call_user_func('update_from_'.$last_ver.'_to_'.$x);
		}
	}
	if($v==$cur_ver) $go=true;
	if($v==EPESI_VERSION) $go=false;
	$last_ver = $x;
}
Variable::set('version',EPESI_VERSION);
?>
