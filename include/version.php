<?php
define("EPESI_VERSION", '1.7.1');
define("EPESI_REVISION", 20160127);

function epesi_requires_update()
{
    $ret = null;
    if (class_exists('Variable', false)) {
        $system_version = Variable::get('version');
        $ret = version_compare($system_version, EPESI_VERSION, '<');
    }
    return $ret;
}
