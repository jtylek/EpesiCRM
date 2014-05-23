<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('recordbrowser_table_properties', 'jump_to_id', 'I1 DEFAULT 1');
