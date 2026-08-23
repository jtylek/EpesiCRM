<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

@PatchUtil::db_add_column('recordbrowser_table_properties', 'description_fields', 'C(255) DEFAULT \'\'');
