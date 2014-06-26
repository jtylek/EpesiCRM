<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('cron','description','C(255)');
