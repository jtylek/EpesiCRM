<?php
/**
 * §61 — widen ip_address to hold a full IPv6 address (max 45 chars).
 * Was C(32), which silently truncated IPv6 in the file remote-access log.
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_alter_column('utils_filestorage_access', 'ip_address', 'C(45)');
