<?php

if (!isset($_REQUEST['cid']) || !isset($_REQUEST['path'])) {
    die('Invalid usage');
}

$cid = $_REQUEST['cid'];

define('CID', $cid);
define('READ_ONLY_SESSION', false);

require_once('../../../include.php');
ModuleManager::load_modules();

if (!Acl::is_user()) {
    die('Permission denied');
}

$module_path = $_REQUEST['path'];
$files_in_session = &Module::static_get_module_variable($module_path, 'files');
if (!is_array($files_in_session)) {
    $files_in_session = array('add' => array(), 'delete' => array(), 'existing' => array());
}
$module_data_dir = DATA_DIR . '/Utils_FileUpload/';

if (array_key_exists('delete', $_POST)) {
    $found = false;
    foreach ($files_in_session['add'] as $file_key => $file_detail) {
        if ($_POST['delete'] == $file_detail['name']) {
            unset($files_in_session['add'][$file_key]);
            @unlink($file_detail['file']);
            $found = true;
            break;
        }
    }
    if (!$found) {
        foreach ($files_in_session['existing'] as $file_key => $file_detail) {
            if ($_POST['delete'] == $file_detail['name']) {
                $files_in_session['delete'][$file_detail['file_id']] = $file_detail['file_id'];
                break;
            }
        }
    }
} else {
    foreach ($_FILES['file']['tmp_name'] as $key => $file) {
        if (is_uploaded_file($file)) {
            $temp_filename = preg_replace('/[^0-9]/', '', strval(microtime(true))) . '_' . Base_AclCommon::get_user();
            $temp_filepath = $module_data_dir . $temp_filename;
            if (move_uploaded_file($file, $temp_filepath)) {
                $files_in_session['add'][] =
                    array('file'  => $temp_filepath,
                          'name'  => $_FILES['file']['name'][$key],
                          'size'  => $_FILES['file']['size'][$key],
                          'type'  => $_FILES['file']['type'][$key],
                          'error' => $_FILES['file']['error'][$key]);
            }
        }
    }
}

Utils_FileUpload_Dropzone::remove_old_temp_files();