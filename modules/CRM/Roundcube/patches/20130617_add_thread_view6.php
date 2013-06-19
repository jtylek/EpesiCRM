<?php
        DB::CreateTable('rc_mails_attachments_download','
            mail_id I4 NOTNULL,
            hash C(32),
            created_on T DEFTIMESTAMP',
            array('constraints'=>', FOREIGN KEY (mail_id) REFERENCES rc_mails_data_1(ID)'));
