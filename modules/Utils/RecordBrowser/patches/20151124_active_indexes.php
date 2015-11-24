<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$checkpoint = Patch::checkpoint('indexes');
if(!$checkpoint->is_done()) {
    $done = $checkpoint->get('recordsets', array());
    $recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
    foreach ($recordsets as $tab => $caption) {
        Patch::require_time(1);
        if (!isset($done[$tab])) {
            @DB::CreateIndex($tab.'_act',$tab.'_data_1','active');
            @DB::CreateIndex($tab.'_idxed',$tab.'_data_1','indexed,active');
            $done[$tab] = true;
            $checkpoint->set('recordsets', $done);
        }
    }
    $checkpoint->done();
}