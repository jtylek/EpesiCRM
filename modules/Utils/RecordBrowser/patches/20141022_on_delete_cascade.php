<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Remove words map records on recordset removal

if (DB::is_postgresql()) {
    $a = DB::GetOne("SELECT
            tc.constraint_name, tc.table_name, kcu.column_name,
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name
        FROM
            information_schema.table_constraints AS tc
        JOIN information_schema.key_column_usage AS kcu
          ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.constraint_column_usage AS ccu
          ON ccu.constraint_name = tc.constraint_name
        WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name='recordbrowser_words_map' AND kcu.column_name='word_id';");
    if ($a) {
        DB::StartTrans();
        DB::Execute('ALTER TABLE recordbrowser_words_map DROP CONSTRAINT "' . $a . '"');
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD CONSTRAINT "' . $a . '" FOREIGN KEY (word_id) REFERENCES recordbrowser_words_index(id) ON DELETE CASCADE ON UPDATE CASCADE');
        DB::CompleteTrans();
    }

    $b = DB::GetOne("SELECT
            tc.constraint_name, tc.table_name, kcu.column_name,
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name
        FROM
            information_schema.table_constraints AS tc
        JOIN information_schema.key_column_usage AS kcu
          ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.constraint_column_usage AS ccu
          ON ccu.constraint_name = tc.constraint_name
        WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name='recordbrowser_words_map' AND kcu.column_name='tab_id';");
    if ($b) {
        DB::StartTrans();
        DB::Execute('ALTER TABLE recordbrowser_words_map DROP CONSTRAINT "' . $b . '"');
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD CONSTRAINT "' . $b . '" FOREIGN KEY (tab_id) REFERENCES recordbrowser_table_properties(id) ON DELETE CASCADE ON UPDATE CASCADE');
        DB::CompleteTrans();
    }
} elseif (DB::is_mysql()) {
    $a = DB::GetRow('SHOW CREATE TABLE recordbrowser_words_map');

    preg_match('/CONSTRAINT (.+) FOREIGN KEY .*word_id/', $a[1], $m);
    if (isset($m[1])) {
        DB::StartTrans();
        DB::Execute('ALTER TABLE recordbrowser_words_map DROP FOREIGN KEY ' . $m[1]);
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD FOREIGN KEY (word_id) REFERENCES recordbrowser_words_index(id) ON DELETE CASCADE ON UPDATE CASCADE');
        DB::CompleteTrans();
    }
    unset($m);

    preg_match('/CONSTRAINT (.+) FOREIGN KEY .*tab_id/', $a[1], $m);
    if (isset($m[1])) {
        DB::StartTrans();
        DB::Execute('ALTER TABLE recordbrowser_words_map DROP FOREIGN KEY ' . $m[1]);
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD FOREIGN KEY (tab_id) REFERENCES recordbrowser_table_properties(id) ON DELETE CASCADE ON UPDATE CASCADE');
        DB::CompleteTrans();
    }
}

