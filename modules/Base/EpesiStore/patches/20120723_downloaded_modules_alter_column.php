<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_rename_column('epesi_store_modules', 'order_id', 'module_license_id', 'I4 NOTNULL');
?>