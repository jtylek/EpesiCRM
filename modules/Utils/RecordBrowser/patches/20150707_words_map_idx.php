<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');


DB::CreateIndex('rb_words_map__word_idx','recordbrowser_words_map','word_id');
DB::CreateIndex('rb_words_map__tab_idx','recordbrowser_words_map','tab_id');
DB::CreateIndex('rb_words_map__record_tab_idx','recordbrowser_words_map','record_id,tab_id');

DB::DropIndex('recordbrowser_words_map__idx','recordbrowser_words_map');
