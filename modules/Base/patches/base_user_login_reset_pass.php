<?php
@DB::CreateTable('user_reset_pass',"user_login_id I NOTNULL, hash_id C(32) NOTNULL, created_on T DEFTIMESTAMP",array('constraints' => ', FOREIGN KEY (user_login_id) REFERENCES user_login(id)'));
?>

