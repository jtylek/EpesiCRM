<?php

PatchUtil::db_rename_column('epesi_store_modules', 'order_id', 'module_license_id', 'I4 NOTNULL');
?>