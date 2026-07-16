<?php
/**
 * §61 — widen from_addr to hold a full IPv6 address (max 45 chars).
 * Was C(32), which silently truncated IPv6 in the brute-force login-ban table.
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_alter_column('user_login_ban', 'from_addr', 'C(45)');
