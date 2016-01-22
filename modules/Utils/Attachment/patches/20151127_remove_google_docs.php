<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

Variable::delete('utils_attachments_google_user', false);
Variable::delete('utils_attachments_google_pass', false);

@DB::DropTable('utils_attachment_googledocs');
