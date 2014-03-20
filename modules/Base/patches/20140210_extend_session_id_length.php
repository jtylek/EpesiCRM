<?php
PatchUtil::db_alter_column('session', 'name', 'C(128) NOTNULL');
PatchUtil::db_alter_column('session_client', 'session_name', 'C(128) NOTNULL');
PatchUtil::db_alter_column('history', 'session_name', 'C(128) NOTNULL');
