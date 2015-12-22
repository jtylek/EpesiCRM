<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$checkpoint = Patch::checkpoint('truncate');
if (!$checkpoint->is_done()) {
    $size = DB::GetOne('SELECT count(*) FROM recordbrowser_words_map');
    if($size>100000) {
        @DB::Execute('TRUNCATE recordbrowser_words_map');
        $tabs = DB::GetCol('SELECT tab FROM recordbrowser_table_properties');
        foreach($tabs as $tab) DB::Execute('UPDATE ' . $tab . '_data_1 SET indexed=0');

    }
    $checkpoint->done();
}

$indexes = Patch::checkpoint('indexes');
if (!$indexes->is_done()) {
    $idxs = DB::MetaIndexes('recordbrowser_words_map');
    $indexes->set('data',$idxs);
    $indexes->done();
}
$idxs = $indexes->get('data',array());

$checkpoint = Patch::checkpoint('word_index');
if (!$checkpoint->is_done() && !isset($idxs['rb_words_map__word_idx'])) {
    DB::CreateIndex('rb_words_map__word_idx', 'recordbrowser_words_map', 'word_id');
    $checkpoint->done();
}
$checkpoint = Patch::checkpoint('tab_index');
if (!$checkpoint->is_done() && !isset($idxs['rb_words_map__tab_idx'])) {
    DB::CreateIndex('rb_words_map__tab_idx','recordbrowser_words_map','tab_id');
    $checkpoint->done();
}
$checkpoint = Patch::checkpoint('record_tab_index');
if (!$checkpoint->is_done() && !isset($idxs['rb_words_map__record_tab_idx'])) {
    DB::CreateIndex('rb_words_map__record_tab_idx','recordbrowser_words_map','record_id,tab_id');
    $checkpoint->done();
}
$checkpoint = Patch::checkpoint('drop_index');
if (!$checkpoint->is_done() && isset($idxs['recordbrowser_words_map__idx'])) {
    DB::DropIndex('recordbrowser_words_map__idx','recordbrowser_words_map');
    $checkpoint->done();
}
