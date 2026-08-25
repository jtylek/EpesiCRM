<?php
/**
 * Created by PhpStorm.
 * User: norbertnader
 * Date: 19.04.2017
 * Time: 13:49
 */

DB::CreateTable('utils_filestorage_remote', '
		id I8 AUTO KEY,
        file_id I8 NOTNULL,
		token C(128) NOTNULL,
        created_on T NOTNULL,
        created_by I8 NOTNULL,
        expires_on T',
    ['constraints' => ', FOREIGN KEY (file_id) REFERENCES utils_filestorage(id)']);

DB::CreateTable('utils_filestorage_access', '
		id I8 AUTO KEY,
        file_id I8 NOTNULL,
		date_accessed T NOTNULL,
		accessed_by I8 NOTNULL,
        type I8 NOTNULL,
        ip_address C(32),
		host_name C(64)',
    ['constraints' => ', FOREIGN KEY (file_id) REFERENCES utils_filestorage(id)']);