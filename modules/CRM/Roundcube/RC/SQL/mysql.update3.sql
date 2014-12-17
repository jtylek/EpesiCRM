CREATE TABLE `rc_cache_shared` (
 `cache_key` varchar(255) /*!40101 CHARACTER SET ascii COLLATE ascii_general_ci */ NOT NULL,
 `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `data` longtext NOT NULL,
 INDEX `created_index` (`created`),
 INDEX `cache_key_index` (`cache_key`)
);

ALTER TABLE `rc_cache` ADD `expires` datetime DEFAULT NULL;
ALTER TABLE `rc_cache_shared` ADD `expires` datetime DEFAULT NULL;
ALTER TABLE `rc_cache_index` ADD `expires` datetime DEFAULT NULL;
ALTER TABLE `rc_cache_thread` ADD `expires` datetime DEFAULT NULL;
ALTER TABLE `rc_cache_messages` ADD `expires` datetime DEFAULT NULL;

-- initialize expires column with created/changed date + 7days
UPDATE `rc_cache` SET `expires` = `created` + interval 604800 second;
UPDATE `rc_cache_shared` SET `expires` = `created` + interval 604800 second;
UPDATE `rc_cache_index` SET `expires` = `changed` + interval 604800 second;
UPDATE `rc_cache_thread` SET `expires` = `changed` + interval 604800 second;
UPDATE `rc_cache_messages` SET `expires` = `changed` + interval 604800 second;

ALTER TABLE `rc_cache` DROP INDEX `created_index`;
ALTER TABLE `rc_cache_shared` DROP INDEX `created_index`;
ALTER TABLE `rc_cache_index` DROP `changed`;
ALTER TABLE `rc_cache_thread` DROP `changed`;
ALTER TABLE `rc_cache_messages` DROP `changed`;

ALTER TABLE `rc_cache` ADD INDEX `expires_index` (`expires`);
ALTER TABLE `rc_cache_shared` ADD INDEX `expires_index` (`expires`);
ALTER TABLE `rc_cache_index` ADD INDEX `expires_index` (`expires`);
ALTER TABLE `rc_cache_thread` ADD INDEX `expires_index` (`expires`);
ALTER TABLE `rc_cache_messages` ADD INDEX `expires_index` (`expires`);
