<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('UPDATE utils_attachment_file af SET filestorage_id=(SELECT id FROM utils_filestorage WHERE link=(' . DB::Concat('\'attachment_file/\'', 'af.id') . '))');
