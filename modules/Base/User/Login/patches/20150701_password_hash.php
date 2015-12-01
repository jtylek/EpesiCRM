<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_alter_column('user_password', 'password', 'C(256) NOTNULL');
