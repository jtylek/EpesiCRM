<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('utils_attachment_link','title','C(255)');
PatchUtil::db_add_column('utils_attachment_link','crypted','I1 DEFAULT 0');
