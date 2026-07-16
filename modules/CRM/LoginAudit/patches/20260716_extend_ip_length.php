<?php
/**
 * §61 — widen ip_address to hold a full IPv6 address (max 45 chars).
 * Was C(32), which silently truncated IPv6. Mirrors 20170123_extend_hostname_length.php.
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_alter_column('base_login_audit', 'ip_address', 'C(45)');
