CREATE TABLE `rc_session` (
 `sess_id` varchar(128) NOT NULL,
 `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `ip` varchar(40) NOT NULL,
 `vars` mediumtext NOT NULL,
 PRIMARY KEY(`sess_id`),
 INDEX `changed_index` (`changed`)
);
CREATE TABLE `rc_users` (
 `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
 `username` varchar(128) BINARY NOT NULL,
 `mail_host` varchar(128) NOT NULL,
 `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `last_login` datetime DEFAULT NULL,
 `failed_login` datetime DEFAULT NULL,
 `failed_login_counter` int(10) UNSIGNED DEFAULT NULL,
 `language` varchar(5),
 `preferences` longtext,
 PRIMARY KEY(`user_id`),
 UNIQUE `username` (`username`, `mail_host`)
);
CREATE TABLE `rc_cache` (
 `user_id` int(10) UNSIGNED NOT NULL,
 `cache_key` varchar(128) /*!40101 CHARACTER SET ascii COLLATE ascii_general_ci */ NOT NULL,
 `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `expires` datetime DEFAULT NULL,
 `data` longtext NOT NULL,
 CONSTRAINT `user_id_fk_cache` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `expires_index` (`expires`),
 INDEX `user_cache_index` (`user_id`,`cache_key`)
);
CREATE TABLE `rc_cache_shared` (
 `cache_key` varchar(255) /*!40101 CHARACTER SET ascii COLLATE ascii_general_ci */ NOT NULL,
 `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `expires` datetime DEFAULT NULL,
 `data` longtext NOT NULL,
 INDEX `expires_index` (`expires`),
 INDEX `cache_key_index` (`cache_key`)
);
CREATE TABLE `rc_cache_index` (
 `user_id` int(10) UNSIGNED NOT NULL,
 `mailbox` varchar(255) BINARY NOT NULL,
 `expires` datetime DEFAULT NULL,
 `valid` tinyint(1) NOT NULL DEFAULT '0',
 `data` longtext NOT NULL,
 CONSTRAINT `user_id_fk_cache_index` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `expires_index` (`expires`),
 PRIMARY KEY (`user_id`, `mailbox`)
);
CREATE TABLE `rc_cache_thread` (
 `user_id` int(10) UNSIGNED NOT NULL,
 `mailbox` varchar(255) BINARY NOT NULL,
 `expires` datetime DEFAULT NULL,
 `data` longtext NOT NULL,
 CONSTRAINT `user_id_fk_cache_thread` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `expires_index` (`expires`),
 PRIMARY KEY (`user_id`, `mailbox`)
);
CREATE TABLE `rc_cache_messages` (
 `user_id` int(10) UNSIGNED NOT NULL,
 `mailbox` varchar(255) BINARY NOT NULL,
 `uid` int(11) UNSIGNED NOT NULL DEFAULT '0',
 `expires` datetime DEFAULT NULL,
 `data` longtext NOT NULL,
 `flags` int(11) NOT NULL DEFAULT '0',
 CONSTRAINT `user_id_fk_cache_messages` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `expires_index` (`expires`),
 PRIMARY KEY (`user_id`, `mailbox`, `uid`)
);
CREATE TABLE `rc_contacts` (
 `contact_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `del` tinyint(1) NOT NULL DEFAULT '0',
 `name` varchar(128) NOT NULL DEFAULT '',
 `email` text NOT NULL,
 `firstname` varchar(128) NOT NULL DEFAULT '',
 `surname` varchar(128) NOT NULL DEFAULT '',
 `vcard` longtext NULL,
 `words` text NULL,
 `user_id` int(10) UNSIGNED NOT NULL,
 PRIMARY KEY(`contact_id`),
 CONSTRAINT `user_id_fk_contacts` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `user_contacts_index` (`user_id`,`del`)
);
CREATE TABLE `rc_contactgroups` (
  `contactgroup_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `del` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY(`contactgroup_id`),
  CONSTRAINT `user_id_fk_contactgroups` FOREIGN KEY (`user_id`)
    REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `contactgroups_user_index` (`user_id`,`del`)
);
CREATE TABLE `rc_contactgroupmembers` (
  `contactgroup_id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`contactgroup_id`, `contact_id`),
  CONSTRAINT `contactgroup_id_fk_contactgroups` FOREIGN KEY (`contactgroup_id`)
    REFERENCES `rc_contactgroups`(`contactgroup_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `contact_id_fk_contacts` FOREIGN KEY (`contact_id`)
    REFERENCES `rc_contacts`(`contact_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `contactgroupmembers_contact_index` (`contact_id`)
);
CREATE TABLE `rc_identities` (
 `identity_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
 `user_id` int(10) UNSIGNED NOT NULL,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `del` tinyint(1) NOT NULL DEFAULT '0',
 `standard` tinyint(1) NOT NULL DEFAULT '0',
 `name` varchar(128) NOT NULL,
 `organization` varchar(128) NOT NULL DEFAULT '',
 `email` varchar(128) NOT NULL,
 `reply-to` varchar(128) NOT NULL DEFAULT '',
 `bcc` varchar(128) NOT NULL DEFAULT '',
 `signature` longtext,
 `html_signature` tinyint(1) NOT NULL DEFAULT '0',
 PRIMARY KEY(`identity_id`),
 CONSTRAINT `user_id_fk_identities` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `user_identities_index` (`user_id`, `del`),
 INDEX `email_identities_index` (`email`, `del`)
);
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
 `user_id` int(10) UNSIGNED NOT NULL,
 `type` int(3) NOT NULL DEFAULT '0',
 `name` varchar(128) NOT NULL,
 `data` text,
 PRIMARY KEY(`search_id`),
 CONSTRAINT `user_id_fk_searches` FOREIGN KEY (`user_id`)
   REFERENCES `rc_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 UNIQUE `uniqueness` (`user_id`, `type`, `name`)
);
CREATE TABLE IF NOT EXISTS `rc_system` (
 `name` varchar(64) NOT NULL,
 `value` mediumtext,
 PRIMARY KEY(`name`)
);
INSERT INTO rc_system (name, value) VALUES ('roundcube-version', '2015030800');
