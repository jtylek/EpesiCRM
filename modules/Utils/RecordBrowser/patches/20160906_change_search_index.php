<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$cp = Patch::checkpoint('all');

$tables = DB::MetaTables();

if ($cp->get('remove', true)) {
    foreach (array('recordbrowser_words_map', 'recordbrowser_words_index') as $tab) {
        if (in_array($tab, $tables)) {
            Patch::require_time(5);
            DB::DropTable($tab);
        }
    }
    $cp->set('remove', false);
}

if ($cp->get('create', true)) {
    if (!in_array('recordbrowser_search_index', $tables)) {
        Patch::require_time(5);
        DB::CreateTable('recordbrowser_search_index', 'tab_id I2 NOTNULL, record_id I NOTNULL, field_id I2 NOTNULL, text X', array('constraints' => ', PRIMARY KEY(tab_id, record_id, field_id)'));
    }
    $cp->set('create', false);
}

$tabs = $cp->get('tabs', array());
foreach (Utils_RecordBrowserCommon::list_installed_recordsets() as $rs => $label) {
    if (!isset($tabs[$rs])) {
        Patch::require_time(5);
        Utils_RecordBrowserCommon::clear_search_index($rs);
        $tabs[$rs] = true;
        $cp->set('tabs', $tabs);
    }
}

