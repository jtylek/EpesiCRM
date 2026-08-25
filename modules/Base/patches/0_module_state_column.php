<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('modules', 'state', 'I NOTNULL DEFAULT 0');
