<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_alter_column('base_login_audit', 'host_name', 'C(255)');
