<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$temp = DATA_DIR . DIRECTORY_SEPARATOR . 'Utils_Attachment' . DIRECTORY_SEPARATOR . 'temp';
if (file_exists($temp)) {
    recursive_rmdir($temp);
}
