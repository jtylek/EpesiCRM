<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

		DB::CreateTable('recordbrowser_words_index', 'id I AUTO KEY,word C(3)',
					array('constraints'=>', UNIQUE(word)'));
		DB::CreateTable('recordbrowser_words_map', 'word_id I, tab C(64), record_id I, field_name C(32), position I',
					array('constraints'=>', FOREIGN KEY (word_id) REFERENCES recordbrowser_words_index(id)'));
		DB::CreateIndex('recordbrowser_words_map__idx','recordbrowser_words_map','word_id,tab,record_id,field_name');
		DB::CreateIndex('recordbrowser_words_map__idx2','recordbrowser_words_map','tab,record_id');

$tabs = DB::GetCol('SELECT tab FROM recordbrowser_table_properties');
foreach($tabs as $tab) {
    PatchUtil::db_add_column($tab.'_data_1', 'indexed', 'I1 DEFAULT 0');
    DB::CreateIndex($tab.'_idxed',$tab.'_data_1','indexed');
}