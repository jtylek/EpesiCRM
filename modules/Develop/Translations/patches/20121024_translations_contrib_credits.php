<?php

PatchUtil::db_add_column('develop_trans_users','credits','I1');
PatchUtil::db_add_column('develop_trans_users','credits_website','C(128)');
PatchUtil::db_add_column('develop_trans_users','contact_email','C(128)');

?>
