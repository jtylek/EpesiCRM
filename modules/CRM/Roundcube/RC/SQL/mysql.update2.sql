ALTER TABLE `rc_cache` DROP COLUMN `cache_id`;
ALTER TABLE `rc_users` DROP COLUMN `alias`;
ALTER TABLE `rc_identities` ADD INDEX `email_identities_index` (`email`, `del`);

CREATE TABLE IF NOT EXISTS `rc_system` (
 `name` varchar(64) NOT NULL,
 `value` mediumtext,
 PRIMARY KEY(`name`)
);
INSERT INTO system (name, value) VALUES ('roundcube-version', '2013011700');