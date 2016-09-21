<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('UPDATE rc_mails_field SET param=REPLACE(param, ' . DB::qstr('CRM_RoundcubeCommon') . ', ' . DB::qstr('CRM_MailCommon') . ')');
