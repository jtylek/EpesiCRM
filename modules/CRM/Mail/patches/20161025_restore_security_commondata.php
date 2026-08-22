<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

if (!Utils_CommonDataCommon::get_id('CRM/Mail/Security')) {
    Utils_CommonDataCommon::new_array('CRM/Mail/Security', array('tls' => _M('TLS'), 'ssl' => _M('SSL')), true, true);
}
