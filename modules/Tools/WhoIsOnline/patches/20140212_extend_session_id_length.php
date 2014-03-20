<?php
PatchUtil::db_alter_column('tools_whoisonline_users', 'session_name', 'C(128) KEY NOTNULL');
