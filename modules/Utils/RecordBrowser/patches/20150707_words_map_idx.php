<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$checkpoint = Patch::checkpoint('word_index');
if (!$checkpoint->is_done()) {
    DB::CreateIndex('rb_words_map__word_idx', 'recordbrowser_words_map', 'word_id');
    $checkpoint->done();
}
$checkpoint = Patch::checkpoint('tab_index');
if (!$checkpoint->is_done()) {
    DB::CreateIndex('rb_words_map__tab_idx','recordbrowser_words_map','tab_id');
    $checkpoint->done();
}
$checkpoint = Patch::checkpoint('record_tab_index');
if (!$checkpoint->is_done()) {
    DB::CreateIndex('rb_words_map__record_tab_idx','recordbrowser_words_map','record_id,tab_id');
    $checkpoint->done();
}
$checkpoint = Patch::checkpoint('drop_index');
if (!$checkpoint->is_done()) {
    DB::DropIndex('recordbrowser_words_map__idx','recordbrowser_words_map');
    $checkpoint->done();
}
