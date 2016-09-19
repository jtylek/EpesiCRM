<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

recursive_rmdir(DATA_DIR . DIRECTORY_SEPARATOR . 'Utils_Attachment' . DIRECTORY_SEPARATOR . 'temp');
