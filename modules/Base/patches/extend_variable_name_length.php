<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_alter_column('variables', 'name', 'C(128)');
