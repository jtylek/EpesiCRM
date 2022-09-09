<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$log_dir = EPESI_LOCAL_DIR.'/'.DATA_DIR.'/CRM_Roundcube/log/';

if(file_exists($log_dir)){

    if (file_exists($log_dir.'/errors')) {
        unlink($log_dir.'/errors');
    }

    if (file_exists($log_dir.'/sendmail')) {
        unlink($log_dir.'/sendmail');
    }
}
?>