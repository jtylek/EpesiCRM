ALTER TABLE `rc_contacts` ADD `words` TEXT NULL AFTER `vcard`;
ALTER TABLE `rc_contacts` CHANGE `vcard` `vcard` LONGTEXT /*!40101 CHARACTER SET utf8 */ NULL DEFAULT NULL;
ALTER TABLE `rc_contactgroupmembers` ADD INDEX `contactgroupmembers_contact_index` (`contact_id`);
TRUNCATE TABLE `rc_messages`;
TRUNCATE TABLE `rc_cache`;
ALTER TABLE `rc_users` CHANGE `alias` `alias` varchar(128) BINARY NOT NULL;
ALTER TABLE `rc_users` CHANGE `username` `username` varchar(128) BINARY NOT NULL;
CREATE TABLE `rc_dictionary` (
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `language` varchar(5) NOT NULL,
  `data` longtext NOT NULL,
  CONSTRAINT `user_id_fk_dictionary` FOREIGN KEY (`user_id`)
    REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE `uniqueness` (`user_id`, `language`)
);
CREATE TABLE `rc_searches` (
  `search_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `type` int(3) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `data` text,
  PRIMARY KEY(`search_id`),
  CONSTRAINT `user_id_fk_searches` FOREIGN KEY (`user_id`)
    REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE `uniqueness` (`user_id`, `type`, `name`)
);
DROP TABLE `rc_messages`;
CREATE TABLE `rc_cache_index` (
 `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
 `mailbox` varchar(255) BINARY NOT NULL,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `valid` tinyint(1) NOT NULL DEFAULT '0',
 `data` longtext NOT NULL,
 CONSTRAINT `user_id_fk_cache_index` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `changed_index` (`changed`),
 PRIMARY KEY (`user_id`, `mailbox`)
);
CREATE TABLE `rc_cache_thread` (
 `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
 `mailbox` varchar(255) BINARY NOT NULL,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `data` longtext NOT NULL,
 CONSTRAINT `user_id_fk_cache_thread` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `changed_index` (`changed`),
 PRIMARY KEY (`user_id`, `mailbox`)
);
CREATE TABLE `rc_cache_messages` (
 `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
 `mailbox` varchar(255) BINARY NOT NULL,
 `uid` int(11) UNSIGNED NOT NULL DEFAULT '0',
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `data` longtext NOT NULL,
 `flags` int(11) NOT NULL DEFAULT '0',
 CONSTRAINT `user_id_fk_cache_messages` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `changed_index` (`changed`),
 PRIMARY KEY (`user_id`, `mailbox`, `uid`)
);
ALTER TABLE `rc_session` CHANGE `sess_id` `sess_id` varchar(128) NOT NULL;
ALTER TABLE `rc_contacts` DROP FOREIGN KEY `user_id_fk_contacts`;
ALTER TABLE `rc_contacts` DROP INDEX `user_contacts_index`;
ALTER TABLE `rc_contacts` MODIFY `email` text NOT NULL;
ALTER TABLE `rc_contacts` ADD INDEX `user_contacts_index` (`user_id`,`del`);
ALTER TABLE `rc_contacts` ADD CONSTRAINT `user_id_fk_contacts` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `rc_cache` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `rc_cache_index` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `rc_cache_thread` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `rc_cache_messages` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `rc_contacts` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `rc_contactgroups` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `rc_contactgroupmembers` ALTER `contact_id` DROP DEFAULT;
ALTER TABLE `rc_identities` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `rc_searches` ALTER `user_id` DROP DEFAULT;
