<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
PatchUtil::db_alter_column('tools_whoisonline_users', 'session_name', 'C(128) KEY NOTNULL');
