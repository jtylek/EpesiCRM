<?php
/**
 * Adds the device (OS · Browser, parse_user_agent()) column CRM_LoginAuditCommon::init()
 * now populates alongside ip_address/host_name.
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('base_login_audit', 'device', 'C(64)');
