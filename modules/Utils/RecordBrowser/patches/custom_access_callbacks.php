<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// patch without date because it has to be run first

$tables = DB::MetaTables();
if (!in_array('recordbrowser_access_methods', $tables)) {
    DB::CreateTable('recordbrowser_access_methods',
                    'tab C(64),'.
                    'func C(255),'.
                    'priority I DEFAULT 10',
                    array('constraints'=>', PRIMARY KEY(tab, func)'));

    PatchUtil::db_drop_column('recordbrowser_table_properties', 'access_callback');
}
