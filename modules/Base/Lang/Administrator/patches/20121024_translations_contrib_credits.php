<?php

PatchUtil::db_add_column('base_lang_trans_contrib','credits','I1');
PatchUtil::db_add_column('base_lang_trans_contrib','credits_website','C(128)');
PatchUtil::db_add_column('base_lang_trans_contrib','contact_email','C(128)');

?>
