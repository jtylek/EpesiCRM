-- Schema-only (mysqldump --no-data), sanitized dump of the legacy Epesi 1.9.1 (revision
-- 20220911) clean installation, used for Migration Wizard M10/M11 fixture
-- shape checks. 268 tables, zero rows, no credentials or secrets. See
-- AI-shared/Migration_Wizard_legacy_Epesi.md "Live test target" for provenance.
-- Tables are dumped alphabetically, not in FK dependency order; disable checks while loading.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `apps_shoutbox_messages`;
CREATE TABLE `apps_shoutbox_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `base_user_login_id` int(11) NOT NULL,
  `to_user_login_id` int(11) DEFAULT NULL,
  `message` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `posted_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `base_user_login_id` (`base_user_login_id`),
  CONSTRAINT `apps_shoutbox_messages_ibfk_1` FOREIGN KEY (`base_user_login_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `available_modules`;
CREATE TABLE `available_modules` (
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `vkey` int(11) NOT NULL,
  `version` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`name`,`vkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_acl_clearance`;
CREATE TABLE `base_acl_clearance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `callback` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_acl_permission`;
CREATE TABLE `base_acl_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_acl_rules`;
CREATE TABLE `base_acl_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `base_acl_rules_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `base_acl_permission` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_acl_rules_clearance`;
CREATE TABLE `base_acl_rules_clearance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `base_acl_rules_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `base_acl_rules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_admin_access`;
CREATE TABLE `base_admin_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `section` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `allow` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_dashboard_applets`;
CREATE TABLE `base_dashboard_applets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_login_id` int(11) DEFAULT NULL,
  `module_name` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `col` smallint(6) DEFAULT 0,
  `pos` smallint(6) DEFAULT 0,
  `color` smallint(6) DEFAULT 0,
  `tab` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_login_id` (`user_login_id`),
  KEY `tab` (`tab`),
  CONSTRAINT `base_dashboard_applets_ibfk_1` FOREIGN KEY (`user_login_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `base_dashboard_applets_ibfk_2` FOREIGN KEY (`tab`) REFERENCES `base_dashboard_tabs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_dashboard_default_applets`;
CREATE TABLE `base_dashboard_default_applets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `col` smallint(6) DEFAULT 0,
  `pos` smallint(6) DEFAULT 0,
  `color` smallint(6) DEFAULT 0,
  `tab` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tab` (`tab`),
  CONSTRAINT `base_dashboard_default_applets_ibfk_1` FOREIGN KEY (`tab`) REFERENCES `base_dashboard_default_tabs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_dashboard_default_settings`;
CREATE TABLE `base_dashboard_default_settings` (
  `applet_id` int(11) NOT NULL,
  `name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`applet_id`,`name`),
  CONSTRAINT `base_dashboard_default_settings_ibfk_1` FOREIGN KEY (`applet_id`) REFERENCES `base_dashboard_default_applets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_dashboard_default_tabs`;
CREATE TABLE `base_dashboard_default_tabs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `pos` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_dashboard_settings`;
CREATE TABLE `base_dashboard_settings` (
  `applet_id` int(11) NOT NULL,
  `name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`applet_id`,`name`),
  CONSTRAINT `base_dashboard_settings_ibfk_1` FOREIGN KEY (`applet_id`) REFERENCES `base_dashboard_applets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_dashboard_tabs`;
CREATE TABLE `base_dashboard_tabs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_login_id` int(11) DEFAULT NULL,
  `name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `pos` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_login_id` (`user_login_id`),
  CONSTRAINT `base_dashboard_tabs_ibfk_1` FOREIGN KEY (`user_login_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_home_page`;
CREATE TABLE `base_home_page` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `priority` int(11) DEFAULT NULL,
  `home_page` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_home_page_clearance`;
CREATE TABLE `base_home_page_clearance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `home_page_id` int(11) DEFAULT NULL,
  `clearance` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `home_page_id` (`home_page_id`),
  CONSTRAINT `base_home_page_clearance_ibfk_1` FOREIGN KEY (`home_page_id`) REFERENCES `base_home_page` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_lang_trans_contrib`;
CREATE TABLE `base_lang_trans_contrib` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `allow` tinyint(4) DEFAULT NULL,
  `first_name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `credits` tinyint(4) DEFAULT NULL,
  `credits_website` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `contact_email` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_login_audit`;
CREATE TABLE `base_login_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_login_id` int(11) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `ip_address` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `host_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_notify`;
CREATE TABLE `base_notify` (
  `token` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `cache` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_refresh` bigint(20) DEFAULT NULL,
  `single_cache_uid` int(11) DEFAULT NULL,
  `telegram` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`token`),
  KEY `single_cache_uid` (`single_cache_uid`),
  CONSTRAINT `base_notify_ibfk_1` FOREIGN KEY (`single_cache_uid`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_user_settings`;
CREATE TABLE `base_user_settings` (
  `user_login_id` int(11) NOT NULL,
  `module` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `variable` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`user_login_id`,`module`,`variable`),
  CONSTRAINT `base_user_settings_ibfk_1` FOREIGN KEY (`user_login_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `base_user_settings_admin_defaults`;
CREATE TABLE `base_user_settings_admin_defaults` (
  `module` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `variable` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`module`,`variable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_access`;
CREATE TABLE `company_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_access_clearance`;
CREATE TABLE `company_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `company_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `company_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_access_fields`;
CREATE TABLE `company_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `company_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `company_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_callback`;
CREATE TABLE `company_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_data_1`;
CREATE TABLE `company_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_company_name` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_short_name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_phone` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_fax` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_email` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_web_address` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_group` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_permission` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_address_1` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_address_2` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_city` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_country` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_zone` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_postal_code` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_tax_id` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_idxed` (`indexed`,`active`),
  KEY `company_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_edit_history`;
CREATE TABLE `company_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `company_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `company_edit_history_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `company_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_edit_history_data`;
CREATE TABLE `company_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `company_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `company_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_favorite`;
CREATE TABLE `company_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `company_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `company_favorite_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `company_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_field`;
CREATE TABLE `company_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `company_recent`;
CREATE TABLE `company_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `company_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `company_recent_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `company_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_access`;
CREATE TABLE `contact_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_access_clearance`;
CREATE TABLE `contact_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `contact_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `contact_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_access_fields`;
CREATE TABLE `contact_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `contact_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `contact_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_callback`;
CREATE TABLE `contact_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_data_1`;
CREATE TABLE `contact_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_last_name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_first_name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_company_name` int(11) DEFAULT NULL,
  `f_related_companies` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_group` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_title` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_work_phone` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_mobile_phone` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_fax` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_email` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_web_address` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_address_1` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_address_2` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_city` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_country` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_zone` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_postal_code` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_permission` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_home_phone` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_home_address_1` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_home_address_2` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_home_city` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_home_country` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_home_zone` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_home_postal_code` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_birth_date` date DEFAULT NULL,
  `f_login` int(11) DEFAULT NULL,
  `f_access` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_idxed` (`indexed`,`active`),
  KEY `contact_act` (`active`),
  KEY `contact_data_1__f_login_idx` (`f_login`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_edit_history`;
CREATE TABLE `contact_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `contact_id` (`contact_id`),
  CONSTRAINT `contact_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `contact_edit_history_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `contact_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_edit_history_data`;
CREATE TABLE `contact_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `contact_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `contact_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_favorite`;
CREATE TABLE `contact_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `contact_id` (`contact_id`),
  CONSTRAINT `contact_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `contact_favorite_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `contact_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_field`;
CREATE TABLE `contact_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `contact_recent`;
CREATE TABLE `contact_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `contact_id` (`contact_id`),
  CONSTRAINT `contact_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `contact_recent_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `contact_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_calendar_custom_events_handlers`;
CREATE TABLE `crm_calendar_custom_events_handlers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `handler_callback` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_filters_contacts`;
CREATE TABLE `crm_filters_contacts` (
  `group_id` int(11) NOT NULL,
  `contact_id` int(11) DEFAULT NULL,
  KEY `group_id` (`group_id`),
  CONSTRAINT `crm_filters_contacts_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `crm_filters_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_filters_group`;
CREATE TABLE `crm_filters_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_login_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`user_login_id`),
  KEY `user_login_id` (`user_login_id`),
  CONSTRAINT `crm_filters_group_ibfk_1` FOREIGN KEY (`user_login_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_access`;
CREATE TABLE `crm_meeting_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_access_clearance`;
CREATE TABLE `crm_meeting_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `crm_meeting_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `crm_meeting_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_access_fields`;
CREATE TABLE `crm_meeting_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `crm_meeting_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `crm_meeting_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_callback`;
CREATE TABLE `crm_meeting_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_data_1`;
CREATE TABLE `crm_meeting_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_title` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_description` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_date` date DEFAULT NULL,
  `f_time` datetime DEFAULT NULL,
  `f_duration` int(11) DEFAULT NULL,
  `f_employees` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_customers` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_status` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_priority` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_permission` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_recurrence_type` int(11) DEFAULT NULL,
  `f_recurrence_end` date DEFAULT NULL,
  `f_recurrence_hash` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_related` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_meeting_idxed` (`indexed`,`active`),
  KEY `crm_meeting_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_edit_history`;
CREATE TABLE `crm_meeting_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `crm_meeting_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `crm_meeting_id` (`crm_meeting_id`),
  CONSTRAINT `crm_meeting_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `crm_meeting_edit_history_ibfk_2` FOREIGN KEY (`crm_meeting_id`) REFERENCES `crm_meeting_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_edit_history_data`;
CREATE TABLE `crm_meeting_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `crm_meeting_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `crm_meeting_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_favorite`;
CREATE TABLE `crm_meeting_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `crm_meeting_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `crm_meeting_id` (`crm_meeting_id`),
  CONSTRAINT `crm_meeting_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `crm_meeting_favorite_ibfk_2` FOREIGN KEY (`crm_meeting_id`) REFERENCES `crm_meeting_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_field`;
CREATE TABLE `crm_meeting_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_recent`;
CREATE TABLE `crm_meeting_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `crm_meeting_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `crm_meeting_id` (`crm_meeting_id`),
  CONSTRAINT `crm_meeting_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `crm_meeting_recent_ibfk_2` FOREIGN KEY (`crm_meeting_id`) REFERENCES `crm_meeting_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_access`;
CREATE TABLE `crm_meeting_related_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_access_clearance`;
CREATE TABLE `crm_meeting_related_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `crm_meeting_related_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `crm_meeting_related_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_access_fields`;
CREATE TABLE `crm_meeting_related_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `crm_meeting_related_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `crm_meeting_related_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_callback`;
CREATE TABLE `crm_meeting_related_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_data_1`;
CREATE TABLE `crm_meeting_related_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_recordset` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_meeting_related_idxed` (`indexed`,`active`),
  KEY `crm_meeting_related_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_edit_history`;
CREATE TABLE `crm_meeting_related_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `crm_meeting_related_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `crm_meeting_related_id` (`crm_meeting_related_id`),
  CONSTRAINT `crm_meeting_related_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `crm_meeting_related_edit_history_ibfk_2` FOREIGN KEY (`crm_meeting_related_id`) REFERENCES `crm_meeting_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_edit_history_data`;
CREATE TABLE `crm_meeting_related_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `crm_meeting_related_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `crm_meeting_related_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_favorite`;
CREATE TABLE `crm_meeting_related_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `crm_meeting_related_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `crm_meeting_related_id` (`crm_meeting_related_id`),
  CONSTRAINT `crm_meeting_related_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `crm_meeting_related_favorite_ibfk_2` FOREIGN KEY (`crm_meeting_related_id`) REFERENCES `crm_meeting_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_field`;
CREATE TABLE `crm_meeting_related_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `crm_meeting_related_recent`;
CREATE TABLE `crm_meeting_related_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `crm_meeting_related_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `crm_meeting_related_id` (`crm_meeting_related_id`),
  CONSTRAINT `crm_meeting_related_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `crm_meeting_related_recent_ibfk_2` FOREIGN KEY (`crm_meeting_related_id`) REFERENCES `crm_meeting_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `cron`;
CREATE TABLE `cron` (
  `func` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `last` int(11) NOT NULL,
  `running` tinyint(4) NOT NULL DEFAULT 0,
  `description` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`func`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `epesi_store_modules`;
CREATE TABLE `epesi_store_modules` (
  `module_id` int(11) NOT NULL,
  `version` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `module_license_id` int(11) NOT NULL,
  `file` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `history`;
CREATE TABLE `history` (
  `session_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `page_id` int(11) NOT NULL,
  `client_id` smallint(6) NOT NULL,
  `data` longblob DEFAULT NULL,
  PRIMARY KEY (`client_id`,`session_name`,`page_id`),
  KEY `history__session_name__client_id__idx` (`session_name`,`client_id`),
  CONSTRAINT `history_ibfk_1` FOREIGN KEY (`session_name`) REFERENCES `session` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `libs_tcpdf_pdf_index`;
CREATE TABLE `libs_tcpdf_pdf_index` (
  `created_on` datetime DEFAULT NULL,
  `filename` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `modules`;
CREATE TABLE `modules` (
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `version` int(11) NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `state` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `patches`;
CREATE TABLE `patches` (
  `id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_access`;
CREATE TABLE `phonecall_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_access_clearance`;
CREATE TABLE `phonecall_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `phonecall_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `phonecall_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_access_fields`;
CREATE TABLE `phonecall_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `phonecall_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `phonecall_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_callback`;
CREATE TABLE `phonecall_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_data_1`;
CREATE TABLE `phonecall_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_subject` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_customer` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_other_customer` tinyint(4) DEFAULT NULL,
  `f_other_customer_name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_permission` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_employees` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_status` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_priority` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_phone` int(11) DEFAULT NULL,
  `f_other_phone` tinyint(4) DEFAULT NULL,
  `f_other_phone_number` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_date_and_time` datetime DEFAULT NULL,
  `f_description` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_related` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phonecall_idxed` (`indexed`,`active`),
  KEY `phonecall_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_edit_history`;
CREATE TABLE `phonecall_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phonecall_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `phonecall_id` (`phonecall_id`),
  CONSTRAINT `phonecall_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `phonecall_edit_history_ibfk_2` FOREIGN KEY (`phonecall_id`) REFERENCES `phonecall_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_edit_history_data`;
CREATE TABLE `phonecall_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `phonecall_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `phonecall_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_favorite`;
CREATE TABLE `phonecall_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `phonecall_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `phonecall_id` (`phonecall_id`),
  CONSTRAINT `phonecall_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `phonecall_favorite_ibfk_2` FOREIGN KEY (`phonecall_id`) REFERENCES `phonecall_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_field`;
CREATE TABLE `phonecall_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_recent`;
CREATE TABLE `phonecall_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `phonecall_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `phonecall_id` (`phonecall_id`),
  CONSTRAINT `phonecall_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `phonecall_recent_ibfk_2` FOREIGN KEY (`phonecall_id`) REFERENCES `phonecall_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_access`;
CREATE TABLE `phonecall_related_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_access_clearance`;
CREATE TABLE `phonecall_related_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `phonecall_related_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `phonecall_related_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_access_fields`;
CREATE TABLE `phonecall_related_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `phonecall_related_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `phonecall_related_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_callback`;
CREATE TABLE `phonecall_related_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_data_1`;
CREATE TABLE `phonecall_related_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_recordset` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phonecall_related_idxed` (`indexed`,`active`),
  KEY `phonecall_related_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_edit_history`;
CREATE TABLE `phonecall_related_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phonecall_related_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `phonecall_related_id` (`phonecall_related_id`),
  CONSTRAINT `phonecall_related_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `phonecall_related_edit_history_ibfk_2` FOREIGN KEY (`phonecall_related_id`) REFERENCES `phonecall_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_edit_history_data`;
CREATE TABLE `phonecall_related_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `phonecall_related_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `phonecall_related_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_favorite`;
CREATE TABLE `phonecall_related_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `phonecall_related_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `phonecall_related_id` (`phonecall_related_id`),
  CONSTRAINT `phonecall_related_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `phonecall_related_favorite_ibfk_2` FOREIGN KEY (`phonecall_related_id`) REFERENCES `phonecall_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_field`;
CREATE TABLE `phonecall_related_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `phonecall_related_recent`;
CREATE TABLE `phonecall_related_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `phonecall_related_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `phonecall_related_id` (`phonecall_related_id`),
  CONSTRAINT `phonecall_related_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `phonecall_related_recent_ibfk_2` FOREIGN KEY (`phonecall_related_id`) REFERENCES `phonecall_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_access`;
CREATE TABLE `premium_checklist_item_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_access_clearance`;
CREATE TABLE `premium_checklist_item_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_item_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_item_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_access_fields`;
CREATE TABLE `premium_checklist_item_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_item_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_item_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_callback`;
CREATE TABLE `premium_checklist_item_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_data_1`;
CREATE TABLE `premium_checklist_item_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_label` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `premium_checklist_item_idxed` (`indexed`,`active`),
  KEY `premium_checklist_item_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_edit_history`;
CREATE TABLE `premium_checklist_item_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_item_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `premium_checklist_item_id` (`premium_checklist_item_id`),
  CONSTRAINT `premium_checklist_item_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_item_edit_history_ibfk_2` FOREIGN KEY (`premium_checklist_item_id`) REFERENCES `premium_checklist_item_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_edit_history_data`;
CREATE TABLE `premium_checklist_item_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `premium_checklist_item_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `premium_checklist_item_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_access`;
CREATE TABLE `premium_checklist_item_entry_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_access_clearance`;
CREATE TABLE `premium_checklist_item_entry_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_item_entry_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_item_entry_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_access_fields`;
CREATE TABLE `premium_checklist_item_entry_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_item_entry_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_item_entry_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_callback`;
CREATE TABLE `premium_checklist_item_entry_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_data_1`;
CREATE TABLE `premium_checklist_item_entry_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_checklist` int(11) DEFAULT NULL,
  `f_item` int(11) DEFAULT NULL,
  `f_status` int(11) DEFAULT NULL,
  `f_amount` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_note` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `premium_checklist_item_entry_idxed` (`indexed`,`active`),
  KEY `premium_checklist_item_entry_act` (`active`),
  KEY `checklist_index` (`f_checklist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_edit_history`;
CREATE TABLE `premium_checklist_item_entry_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_item_entry_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `premium_checklist_item_entry_id` (`premium_checklist_item_entry_id`),
  CONSTRAINT `premium_checklist_item_entry_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_item_entry_edit_history_ibfk_2` FOREIGN KEY (`premium_checklist_item_entry_id`) REFERENCES `premium_checklist_item_entry_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_edit_history_data`;
CREATE TABLE `premium_checklist_item_entry_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `premium_checklist_item_entry_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `premium_checklist_item_entry_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_favorite`;
CREATE TABLE `premium_checklist_item_entry_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_item_entry_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_item_entry_id` (`premium_checklist_item_entry_id`),
  CONSTRAINT `premium_checklist_item_entry_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_item_entry_favorite_ibfk_2` FOREIGN KEY (`premium_checklist_item_entry_id`) REFERENCES `premium_checklist_item_entry_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_field`;
CREATE TABLE `premium_checklist_item_entry_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_entry_recent`;
CREATE TABLE `premium_checklist_item_entry_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_item_entry_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_item_entry_id` (`premium_checklist_item_entry_id`),
  CONSTRAINT `premium_checklist_item_entry_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_item_entry_recent_ibfk_2` FOREIGN KEY (`premium_checklist_item_entry_id`) REFERENCES `premium_checklist_item_entry_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_favorite`;
CREATE TABLE `premium_checklist_item_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_item_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_item_id` (`premium_checklist_item_id`),
  CONSTRAINT `premium_checklist_item_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_item_favorite_ibfk_2` FOREIGN KEY (`premium_checklist_item_id`) REFERENCES `premium_checklist_item_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_field`;
CREATE TABLE `premium_checklist_item_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_item_recent`;
CREATE TABLE `premium_checklist_item_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_item_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_item_id` (`premium_checklist_item_id`),
  CONSTRAINT `premium_checklist_item_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_item_recent_ibfk_2` FOREIGN KEY (`premium_checklist_item_id`) REFERENCES `premium_checklist_item_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_access`;
CREATE TABLE `premium_checklist_list_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_access_clearance`;
CREATE TABLE `premium_checklist_list_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_list_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_list_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_access_fields`;
CREATE TABLE `premium_checklist_list_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_list_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_list_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_callback`;
CREATE TABLE `premium_checklist_list_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_data_1`;
CREATE TABLE `premium_checklist_list_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_label` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_recordset` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_checklist_items` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_hints` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_default_recurrence` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_employees` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `premium_checklist_list_idxed` (`indexed`,`active`),
  KEY `premium_checklist_list_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_edit_history`;
CREATE TABLE `premium_checklist_list_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_list_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `premium_checklist_list_id` (`premium_checklist_list_id`),
  CONSTRAINT `premium_checklist_list_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_list_edit_history_ibfk_2` FOREIGN KEY (`premium_checklist_list_id`) REFERENCES `premium_checklist_list_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_edit_history_data`;
CREATE TABLE `premium_checklist_list_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `premium_checklist_list_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `premium_checklist_list_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_access`;
CREATE TABLE `premium_checklist_list_entry_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_access_clearance`;
CREATE TABLE `premium_checklist_list_entry_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_list_entry_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_list_entry_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_access_fields`;
CREATE TABLE `premium_checklist_list_entry_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_list_entry_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_list_entry_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_callback`;
CREATE TABLE `premium_checklist_list_entry_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_data_1`;
CREATE TABLE `premium_checklist_list_entry_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_checklist` int(11) DEFAULT NULL,
  `f_record_id` int(11) DEFAULT NULL,
  `f_employee` int(11) DEFAULT NULL,
  `f_date` datetime DEFAULT NULL,
  `f_next_date` datetime DEFAULT NULL,
  `f_completed` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `premium_checklist_list_entry_idxed` (`indexed`,`active`),
  KEY `premium_checklist_list_entry_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_edit_history`;
CREATE TABLE `premium_checklist_list_entry_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_list_entry_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `premium_checklist_list_entry_id` (`premium_checklist_list_entry_id`),
  CONSTRAINT `premium_checklist_list_entry_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_list_entry_edit_history_ibfk_2` FOREIGN KEY (`premium_checklist_list_entry_id`) REFERENCES `premium_checklist_list_entry_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_edit_history_data`;
CREATE TABLE `premium_checklist_list_entry_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `premium_checklist_list_entry_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `premium_checklist_list_entry_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_favorite`;
CREATE TABLE `premium_checklist_list_entry_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_list_entry_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_list_entry_id` (`premium_checklist_list_entry_id`),
  CONSTRAINT `premium_checklist_list_entry_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_list_entry_favorite_ibfk_2` FOREIGN KEY (`premium_checklist_list_entry_id`) REFERENCES `premium_checklist_list_entry_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_field`;
CREATE TABLE `premium_checklist_list_entry_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_entry_recent`;
CREATE TABLE `premium_checklist_list_entry_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_list_entry_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_list_entry_id` (`premium_checklist_list_entry_id`),
  CONSTRAINT `premium_checklist_list_entry_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_list_entry_recent_ibfk_2` FOREIGN KEY (`premium_checklist_list_entry_id`) REFERENCES `premium_checklist_list_entry_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_favorite`;
CREATE TABLE `premium_checklist_list_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_list_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_list_id` (`premium_checklist_list_id`),
  CONSTRAINT `premium_checklist_list_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_list_favorite_ibfk_2` FOREIGN KEY (`premium_checklist_list_id`) REFERENCES `premium_checklist_list_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_field`;
CREATE TABLE `premium_checklist_list_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_list_recent`;
CREATE TABLE `premium_checklist_list_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_list_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_list_id` (`premium_checklist_list_id`),
  CONSTRAINT `premium_checklist_list_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_list_recent_ibfk_2` FOREIGN KEY (`premium_checklist_list_id`) REFERENCES `premium_checklist_list_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_access`;
CREATE TABLE `premium_checklist_status_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_access_clearance`;
CREATE TABLE `premium_checklist_status_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_status_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_status_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_access_fields`;
CREATE TABLE `premium_checklist_status_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `premium_checklist_status_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `premium_checklist_status_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_callback`;
CREATE TABLE `premium_checklist_status_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_data_1`;
CREATE TABLE `premium_checklist_status_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_label` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_checklist` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `premium_checklist_status_idxed` (`indexed`,`active`),
  KEY `premium_checklist_status_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_edit_history`;
CREATE TABLE `premium_checklist_status_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_status_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `premium_checklist_status_id` (`premium_checklist_status_id`),
  CONSTRAINT `premium_checklist_status_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_status_edit_history_ibfk_2` FOREIGN KEY (`premium_checklist_status_id`) REFERENCES `premium_checklist_status_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_edit_history_data`;
CREATE TABLE `premium_checklist_status_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `premium_checklist_status_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `premium_checklist_status_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_favorite`;
CREATE TABLE `premium_checklist_status_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_status_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_status_id` (`premium_checklist_status_id`),
  CONSTRAINT `premium_checklist_status_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_status_favorite_ibfk_2` FOREIGN KEY (`premium_checklist_status_id`) REFERENCES `premium_checklist_status_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_field`;
CREATE TABLE `premium_checklist_status_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `premium_checklist_status_recent`;
CREATE TABLE `premium_checklist_status_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `premium_checklist_status_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `premium_checklist_status_id` (`premium_checklist_status_id`),
  CONSTRAINT `premium_checklist_status_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `premium_checklist_status_recent_ibfk_2` FOREIGN KEY (`premium_checklist_status_id`) REFERENCES `premium_checklist_status_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_access`;
CREATE TABLE `rc_accounts_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_access_clearance`;
CREATE TABLE `rc_accounts_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_accounts_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_accounts_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_access_fields`;
CREATE TABLE `rc_accounts_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_accounts_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_accounts_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_callback`;
CREATE TABLE `rc_accounts_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_data_1`;
CREATE TABLE `rc_accounts_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_epesi_user` int(11) DEFAULT NULL,
  `f_email` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_account_name` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_server` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_login` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_password` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_security` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_smtp_server` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_smtp_auth` tinyint(4) DEFAULT NULL,
  `f_smtp_login` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_smtp_password` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_smtp_security` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_default_account` tinyint(4) DEFAULT NULL,
  `f_archive_on_sending` tinyint(4) DEFAULT NULL,
  `f_use_epesi_archive_directories` tinyint(4) DEFAULT NULL,
  `f_imap_root` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_imap_delimiter` varchar(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rc_accounts_idxed` (`indexed`,`active`),
  KEY `rc_accounts_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_edit_history`;
CREATE TABLE `rc_accounts_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_accounts_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `rc_accounts_id` (`rc_accounts_id`),
  CONSTRAINT `rc_accounts_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_accounts_edit_history_ibfk_2` FOREIGN KEY (`rc_accounts_id`) REFERENCES `rc_accounts_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_edit_history_data`;
CREATE TABLE `rc_accounts_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `rc_accounts_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `rc_accounts_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_favorite`;
CREATE TABLE `rc_accounts_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_accounts_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_accounts_id` (`rc_accounts_id`),
  CONSTRAINT `rc_accounts_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_accounts_favorite_ibfk_2` FOREIGN KEY (`rc_accounts_id`) REFERENCES `rc_accounts_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_field`;
CREATE TABLE `rc_accounts_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_accounts_recent`;
CREATE TABLE `rc_accounts_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_accounts_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_accounts_id` (`rc_accounts_id`),
  CONSTRAINT `rc_accounts_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_accounts_recent_ibfk_2` FOREIGN KEY (`rc_accounts_id`) REFERENCES `rc_accounts_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_cache`;
CREATE TABLE `rc_cache` (
  `user_id` int(10) unsigned NOT NULL,
  `cache_key` varchar(128) CHARACTER SET ascii NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `expires` datetime DEFAULT NULL,
  `data` longtext COLLATE utf8_unicode_ci NOT NULL,
  KEY `expires_index` (`expires`),
  KEY `user_cache_index` (`user_id`,`cache_key`),
  CONSTRAINT `user_id_fk_cache` FOREIGN KEY (`user_id`) REFERENCES `rc_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_cache_index`;
CREATE TABLE `rc_cache_index` (
  `user_id` int(10) unsigned NOT NULL,
  `mailbox` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `expires` datetime DEFAULT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT 0,
  `data` longtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user_id`,`mailbox`),
  KEY `expires_index` (`expires`),
  CONSTRAINT `user_id_fk_cache_index` FOREIGN KEY (`user_id`) REFERENCES `rc_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_cache_messages`;
CREATE TABLE `rc_cache_messages` (
  `user_id` int(10) unsigned NOT NULL,
  `mailbox` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `uid` int(11) unsigned NOT NULL DEFAULT 0,
  `expires` datetime DEFAULT NULL,
  `data` longtext COLLATE utf8_unicode_ci NOT NULL,
  `flags` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`,`mailbox`,`uid`),
  KEY `expires_index` (`expires`),
  CONSTRAINT `user_id_fk_cache_messages` FOREIGN KEY (`user_id`) REFERENCES `rc_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_cache_shared`;
CREATE TABLE `rc_cache_shared` (
  `cache_key` varchar(255) CHARACTER SET ascii NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `expires` datetime DEFAULT NULL,
  `data` longtext COLLATE utf8_unicode_ci NOT NULL,
  KEY `expires_index` (`expires`),
  KEY `cache_key_index` (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_cache_thread`;
CREATE TABLE `rc_cache_thread` (
  `user_id` int(10) unsigned NOT NULL,
  `mailbox` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `expires` datetime DEFAULT NULL,
  `data` longtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user_id`,`mailbox`),
  KEY `expires_index` (`expires`),
  CONSTRAINT `user_id_fk_cache_thread` FOREIGN KEY (`user_id`) REFERENCES `rc_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_contactgroupmembers`;
CREATE TABLE `rc_contactgroupmembers` (
  `contactgroup_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`contactgroup_id`,`contact_id`),
  KEY `contactgroupmembers_contact_index` (`contact_id`),
  CONSTRAINT `contact_id_fk_contacts` FOREIGN KEY (`contact_id`) REFERENCES `rc_contacts` (`contact_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `contactgroup_id_fk_contactgroups` FOREIGN KEY (`contactgroup_id`) REFERENCES `rc_contactgroups` (`contactgroup_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_contactgroups`;
CREATE TABLE `rc_contactgroups` (
  `contactgroup_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `del` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`contactgroup_id`),
  KEY `contactgroups_user_index` (`user_id`,`del`),
  CONSTRAINT `user_id_fk_contactgroups` FOREIGN KEY (`user_id`) REFERENCES `rc_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_contacts`;
CREATE TABLE `rc_contacts` (
  `contact_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `del` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `email` text COLLATE utf8_unicode_ci NOT NULL,
  `firstname` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `surname` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `vcard` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `words` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`contact_id`),
  KEY `user_contacts_index` (`user_id`,`del`),
  CONSTRAINT `user_id_fk_contacts` FOREIGN KEY (`user_id`) REFERENCES `rc_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_dictionary`;
CREATE TABLE `rc_dictionary` (
  `user_id` int(10) unsigned DEFAULT NULL,
  `language` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `data` longtext COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `uniqueness` (`user_id`,`language`),
  CONSTRAINT `user_id_fk_dictionary` FOREIGN KEY (`user_id`) REFERENCES `rc_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_identities`;
CREATE TABLE `rc_identities` (
  `identity_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `del` tinyint(1) NOT NULL DEFAULT 0,
  `standard` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `organization` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `reply-to` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `bcc` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `signature` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `html_signature` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`identity_id`),
  KEY `user_identities_index` (`user_id`,`del`),
  KEY `email_identities_index` (`email`,`del`),
  CONSTRAINT `user_id_fk_identities` FOREIGN KEY (`user_id`) REFERENCES `rc_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_access`;
CREATE TABLE `rc_mail_threads_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_access_clearance`;
CREATE TABLE `rc_mail_threads_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_mail_threads_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_mail_threads_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_access_fields`;
CREATE TABLE `rc_mail_threads_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_mail_threads_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_mail_threads_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_callback`;
CREATE TABLE `rc_mail_threads_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_data_1`;
CREATE TABLE `rc_mail_threads_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_subject` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_contacts` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_first_date` datetime DEFAULT NULL,
  `f_last_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rc_mail_threads_idxed` (`indexed`,`active`),
  KEY `rc_mail_threads_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_edit_history`;
CREATE TABLE `rc_mail_threads_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_mail_threads_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `rc_mail_threads_id` (`rc_mail_threads_id`),
  CONSTRAINT `rc_mail_threads_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_mail_threads_edit_history_ibfk_2` FOREIGN KEY (`rc_mail_threads_id`) REFERENCES `rc_mail_threads_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_edit_history_data`;
CREATE TABLE `rc_mail_threads_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `rc_mail_threads_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `rc_mail_threads_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_favorite`;
CREATE TABLE `rc_mail_threads_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_mail_threads_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_mail_threads_id` (`rc_mail_threads_id`),
  CONSTRAINT `rc_mail_threads_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_mail_threads_favorite_ibfk_2` FOREIGN KEY (`rc_mail_threads_id`) REFERENCES `rc_mail_threads_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_field`;
CREATE TABLE `rc_mail_threads_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mail_threads_recent`;
CREATE TABLE `rc_mail_threads_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_mail_threads_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_mail_threads_id` (`rc_mail_threads_id`),
  CONSTRAINT `rc_mail_threads_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_mail_threads_recent_ibfk_2` FOREIGN KEY (`rc_mail_threads_id`) REFERENCES `rc_mail_threads_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_access`;
CREATE TABLE `rc_mails_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_access_clearance`;
CREATE TABLE `rc_mails_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_mails_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_mails_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_access_fields`;
CREATE TABLE `rc_mails_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_mails_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_mails_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_attachments`;
CREATE TABLE `rc_mails_attachments` (
  `mail_id` int(11) NOT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `mime_id` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `attachment` tinyint(4) DEFAULT 1,
  KEY `mail_id` (`mail_id`),
  CONSTRAINT `rc_mails_attachments_ibfk_1` FOREIGN KEY (`mail_id`) REFERENCES `rc_mails_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_attachments_download`;
CREATE TABLE `rc_mails_attachments_download` (
  `mail_id` int(11) NOT NULL,
  `hash` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  KEY `mail_id` (`mail_id`),
  CONSTRAINT `rc_mails_attachments_download_ibfk_1` FOREIGN KEY (`mail_id`) REFERENCES `rc_mails_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_callback`;
CREATE TABLE `rc_mails_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_data_1`;
CREATE TABLE `rc_mails_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_subject` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_contacts` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_employee` int(11) DEFAULT NULL,
  `f_related` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_date` datetime DEFAULT NULL,
  `f_headers_data` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_body` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_from` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_to` varchar(4096) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_thread` int(11) DEFAULT NULL,
  `f_message_id` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_references` varchar(16384) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rc_mails_idxed` (`indexed`,`active`),
  KEY `rc_mails_act` (`active`),
  KEY `rc_mails_thread_idx` (`f_thread`),
  KEY `rc_mails_msgid_idx` (`f_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_edit_history`;
CREATE TABLE `rc_mails_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_mails_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `rc_mails_id` (`rc_mails_id`),
  CONSTRAINT `rc_mails_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_mails_edit_history_ibfk_2` FOREIGN KEY (`rc_mails_id`) REFERENCES `rc_mails_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_edit_history_data`;
CREATE TABLE `rc_mails_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `rc_mails_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `rc_mails_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_favorite`;
CREATE TABLE `rc_mails_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_mails_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_mails_id` (`rc_mails_id`),
  CONSTRAINT `rc_mails_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_mails_favorite_ibfk_2` FOREIGN KEY (`rc_mails_id`) REFERENCES `rc_mails_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_field`;
CREATE TABLE `rc_mails_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_mails_recent`;
CREATE TABLE `rc_mails_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_mails_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_mails_id` (`rc_mails_id`),
  CONSTRAINT `rc_mails_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_mails_recent_ibfk_2` FOREIGN KEY (`rc_mails_id`) REFERENCES `rc_mails_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_access`;
CREATE TABLE `rc_multiple_emails_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_access_clearance`;
CREATE TABLE `rc_multiple_emails_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_multiple_emails_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_multiple_emails_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_access_fields`;
CREATE TABLE `rc_multiple_emails_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_multiple_emails_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_multiple_emails_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_callback`;
CREATE TABLE `rc_multiple_emails_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_data_1`;
CREATE TABLE `rc_multiple_emails_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_record_type` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_record_id` int(11) DEFAULT NULL,
  `f_nickname` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_email` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rc_multiple_emails_idxed` (`indexed`,`active`),
  KEY `rc_multiple_emails_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_edit_history`;
CREATE TABLE `rc_multiple_emails_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_multiple_emails_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `rc_multiple_emails_id` (`rc_multiple_emails_id`),
  CONSTRAINT `rc_multiple_emails_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_multiple_emails_edit_history_ibfk_2` FOREIGN KEY (`rc_multiple_emails_id`) REFERENCES `rc_multiple_emails_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_edit_history_data`;
CREATE TABLE `rc_multiple_emails_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `rc_multiple_emails_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `rc_multiple_emails_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_favorite`;
CREATE TABLE `rc_multiple_emails_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_multiple_emails_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_multiple_emails_id` (`rc_multiple_emails_id`),
  CONSTRAINT `rc_multiple_emails_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_multiple_emails_favorite_ibfk_2` FOREIGN KEY (`rc_multiple_emails_id`) REFERENCES `rc_multiple_emails_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_field`;
CREATE TABLE `rc_multiple_emails_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_multiple_emails_recent`;
CREATE TABLE `rc_multiple_emails_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_multiple_emails_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_multiple_emails_id` (`rc_multiple_emails_id`),
  CONSTRAINT `rc_multiple_emails_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_multiple_emails_recent_ibfk_2` FOREIGN KEY (`rc_multiple_emails_id`) REFERENCES `rc_multiple_emails_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_access`;
CREATE TABLE `rc_related_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_access_clearance`;
CREATE TABLE `rc_related_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_related_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_related_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_access_fields`;
CREATE TABLE `rc_related_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `rc_related_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `rc_related_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_callback`;
CREATE TABLE `rc_related_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_data_1`;
CREATE TABLE `rc_related_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_recordset` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rc_related_idxed` (`indexed`,`active`),
  KEY `rc_related_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_edit_history`;
CREATE TABLE `rc_related_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_related_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `rc_related_id` (`rc_related_id`),
  CONSTRAINT `rc_related_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_related_edit_history_ibfk_2` FOREIGN KEY (`rc_related_id`) REFERENCES `rc_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_edit_history_data`;
CREATE TABLE `rc_related_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `rc_related_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `rc_related_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_favorite`;
CREATE TABLE `rc_related_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_related_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_related_id` (`rc_related_id`),
  CONSTRAINT `rc_related_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_related_favorite_ibfk_2` FOREIGN KEY (`rc_related_id`) REFERENCES `rc_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_field`;
CREATE TABLE `rc_related_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_related_recent`;
CREATE TABLE `rc_related_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `rc_related_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `rc_related_id` (`rc_related_id`),
  CONSTRAINT `rc_related_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `rc_related_recent_ibfk_2` FOREIGN KEY (`rc_related_id`) REFERENCES `rc_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_searches`;
CREATE TABLE `rc_searches` (
  `search_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` int(3) NOT NULL DEFAULT 0,
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `data` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`search_id`),
  UNIQUE KEY `uniqueness` (`user_id`,`type`,`name`),
  CONSTRAINT `user_id_fk_searches` FOREIGN KEY (`user_id`) REFERENCES `rc_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_session`;
CREATE TABLE `rc_session` (
  `sess_id` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `ip` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `vars` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`sess_id`),
  KEY `changed_index` (`changed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_system`;
CREATE TABLE `rc_system` (
  `name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `rc_users`;
CREATE TABLE `rc_users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mail_host` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `last_login` datetime DEFAULT NULL,
  `failed_login` datetime DEFAULT NULL,
  `failed_login_counter` int(10) unsigned DEFAULT NULL,
  `language` varchar(5) COLLATE utf8_unicode_ci DEFAULT NULL,
  `preferences` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`,`mail_host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `recordbrowser_access_methods`;
CREATE TABLE `recordbrowser_access_methods` (
  `tab` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `func` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `priority` int(11) DEFAULT 10,
  PRIMARY KEY (`tab`,`func`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `recordbrowser_addon`;
CREATE TABLE `recordbrowser_addon` (
  `tab` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `module` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `func` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `pos` int(11) DEFAULT NULL,
  `enabled` tinyint(4) DEFAULT NULL,
  `label` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`tab`,`module`,`func`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `recordbrowser_browse_mode_definitions`;
CREATE TABLE `recordbrowser_browse_mode_definitions` (
  `tab` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `module` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `func` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`tab`,`module`,`func`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `recordbrowser_clipboard_pattern`;
CREATE TABLE `recordbrowser_clipboard_pattern` (
  `tab` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `pattern` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `enabled` int(11) DEFAULT NULL,
  PRIMARY KEY (`tab`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `recordbrowser_custom_recordsets`;
CREATE TABLE `recordbrowser_custom_recordsets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(4) DEFAULT NULL,
  `tab` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `menu` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `recordbrowser_datatype`;
CREATE TABLE `recordbrowser_datatype` (
  `type` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `module` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `func` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `recordbrowser_processing_methods`;
CREATE TABLE `recordbrowser_processing_methods` (
  `tab` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `func` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`tab`,`func`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `recordbrowser_search_index`;
CREATE TABLE `recordbrowser_search_index` (
  `tab_id` smallint(6) NOT NULL,
  `record_id` int(11) NOT NULL,
  `field_id` smallint(6) NOT NULL,
  `text` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`tab_id`,`record_id`,`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `recordbrowser_table_properties`;
CREATE TABLE `recordbrowser_table_properties` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `tab` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `quickjump` varchar(64) COLLATE utf8_unicode_ci DEFAULT '',
  `tpl` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
  `favorites` tinyint(4) DEFAULT 0,
  `recent` smallint(6) DEFAULT 0,
  `full_history` tinyint(4) DEFAULT 1,
  `caption` varchar(32) COLLATE utf8_unicode_ci DEFAULT '',
  `icon` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
  `description_callback` varchar(128) COLLATE utf8_unicode_ci DEFAULT '',
  `description_fields` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
  `jump_to_id` tinyint(4) DEFAULT 1,
  `search_include` tinyint(4) DEFAULT 0,
  `search_priority` tinyint(4) DEFAULT 0,
  `printer` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tab` (`tab`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `session`;
CREATE TABLE `session` (
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `expires` int(11) NOT NULL DEFAULT 0,
  `data` longblob DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `session_client`;
CREATE TABLE `session_client` (
  `session_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` smallint(6) NOT NULL,
  `data` longblob DEFAULT NULL,
  PRIMARY KEY (`client_id`,`session_name`),
  KEY `session_name` (`session_name`),
  CONSTRAINT `session_client_ibfk_1` FOREIGN KEY (`session_name`) REFERENCES `session` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_access`;
CREATE TABLE `task_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_access_clearance`;
CREATE TABLE `task_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `task_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `task_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_access_fields`;
CREATE TABLE `task_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `task_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `task_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_callback`;
CREATE TABLE `task_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_data_1`;
CREATE TABLE `task_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_title` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_description` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_employees` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_customers` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_status` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_priority` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_permission` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_longterm` tinyint(4) DEFAULT NULL,
  `f_deadline` datetime DEFAULT NULL,
  `f_timeless` tinyint(4) DEFAULT NULL,
  `f_related` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_idxed` (`indexed`,`active`),
  KEY `task_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_edit_history`;
CREATE TABLE `task_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `task_edit_history_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `task_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_edit_history_data`;
CREATE TABLE `task_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `task_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `task_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_favorite`;
CREATE TABLE `task_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `task_favorite_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `task_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_field`;
CREATE TABLE `task_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_recent`;
CREATE TABLE `task_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `task_recent_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `task_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_access`;
CREATE TABLE `task_related_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_access_clearance`;
CREATE TABLE `task_related_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `task_related_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `task_related_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_access_fields`;
CREATE TABLE `task_related_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `task_related_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `task_related_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_callback`;
CREATE TABLE `task_related_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_data_1`;
CREATE TABLE `task_related_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_recordset` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_related_idxed` (`indexed`,`active`),
  KEY `task_related_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_edit_history`;
CREATE TABLE `task_related_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_related_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `task_related_id` (`task_related_id`),
  CONSTRAINT `task_related_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `task_related_edit_history_ibfk_2` FOREIGN KEY (`task_related_id`) REFERENCES `task_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_edit_history_data`;
CREATE TABLE `task_related_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `task_related_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `task_related_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_favorite`;
CREATE TABLE `task_related_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_related_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `task_related_id` (`task_related_id`),
  CONSTRAINT `task_related_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `task_related_favorite_ibfk_2` FOREIGN KEY (`task_related_id`) REFERENCES `task_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_field`;
CREATE TABLE `task_related_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `task_related_recent`;
CREATE TABLE `task_related_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_related_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `task_related_id` (`task_related_id`),
  CONSTRAINT `task_related_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `task_related_recent_ibfk_2` FOREIGN KEY (`task_related_id`) REFERENCES `task_related_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `tools_whoisonline_users`;
CREATE TABLE `tools_whoisonline_users` (
  `session_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `user_login_id` int(11) NOT NULL,
  PRIMARY KEY (`session_name`),
  KEY `user_login_id` (`user_login_id`),
  CONSTRAINT `tools_whoisonline_users_ibfk_1` FOREIGN KEY (`user_login_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `user_autologin`;
CREATE TABLE `user_autologin` (
  `user_login_id` int(11) NOT NULL,
  `autologin_id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `last_log` datetime DEFAULT NULL,
  `description` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `user_login_id` (`user_login_id`),
  CONSTRAINT `user_autologin_ibfk_1` FOREIGN KEY (`user_login_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `user_login`;
CREATE TABLE `user_login` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `admin` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `user_login_ban`;
CREATE TABLE `user_login_ban` (
  `failed_on` int(11) DEFAULT NULL,
  `from_addr` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `user_password`;
CREATE TABLE `user_password` (
  `user_login_id` int(11) NOT NULL,
  `password` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `mail` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user_login_id`),
  CONSTRAINT `user_password_ibfk_1` FOREIGN KEY (`user_login_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `user_reset_pass`;
CREATE TABLE `user_reset_pass` (
  `user_login_id` int(11) NOT NULL,
  `hash_id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `created_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  KEY `user_login_id` (`user_login_id`),
  CONSTRAINT `user_reset_pass_ibfk_1` FOREIGN KEY (`user_login_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_access`;
CREATE TABLE `utils_attachment_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `crits` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_access_clearance`;
CREATE TABLE `utils_attachment_access_clearance` (
  `rule_id` int(11) DEFAULT NULL,
  `clearance` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `utils_attachment_access_clearance_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `utils_attachment_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_access_fields`;
CREATE TABLE `utils_attachment_access_fields` (
  `rule_id` int(11) DEFAULT NULL,
  `block_field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `utils_attachment_access_fields_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `utils_attachment_access` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_callback`;
CREATE TABLE `utils_attachment_callback` (
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `freezed` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_data_1`;
CREATE TABLE `utils_attachment_data_1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `indexed` tinyint(4) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `f_edited_on` datetime DEFAULT NULL,
  `f_title` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_note` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_files` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_permission` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `f_sticky` tinyint(4) DEFAULT NULL,
  `f_crypted` tinyint(4) DEFAULT NULL,
  `f_attached_to` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `utils_attachment_idxed` (`indexed`,`active`),
  KEY `utils_attachment_act` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_edit_history`;
CREATE TABLE `utils_attachment_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utils_attachment_id` int(11) DEFAULT NULL,
  `edited_on` datetime DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `edited_by` (`edited_by`),
  KEY `utils_attachment_id` (`utils_attachment_id`),
  CONSTRAINT `utils_attachment_edit_history_ibfk_1` FOREIGN KEY (`edited_by`) REFERENCES `user_login` (`id`),
  CONSTRAINT `utils_attachment_edit_history_ibfk_2` FOREIGN KEY (`utils_attachment_id`) REFERENCES `utils_attachment_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_edit_history_data`;
CREATE TABLE `utils_attachment_edit_history_data` (
  `edit_id` int(11) DEFAULT NULL,
  `field` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `old_value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `edit_id` (`edit_id`),
  CONSTRAINT `utils_attachment_edit_history_data_ibfk_1` FOREIGN KEY (`edit_id`) REFERENCES `utils_attachment_edit_history` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_favorite`;
CREATE TABLE `utils_attachment_favorite` (
  `fav_id` int(11) NOT NULL AUTO_INCREMENT,
  `utils_attachment_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`fav_id`),
  KEY `user_id` (`user_id`),
  KEY `utils_attachment_id` (`utils_attachment_id`),
  CONSTRAINT `utils_attachment_favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `utils_attachment_favorite_ibfk_2` FOREIGN KEY (`utils_attachment_id`) REFERENCES `utils_attachment_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_field`;
CREATE TABLE `utils_attachment_field` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `field` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `extra` tinyint(4) DEFAULT 1,
  `visible` tinyint(4) DEFAULT 1,
  `tooltip` tinyint(4) DEFAULT 1,
  `required` tinyint(4) DEFAULT 1,
  `export` tinyint(4) DEFAULT 1,
  `active` tinyint(4) DEFAULT 1,
  `position` smallint(6) DEFAULT NULL,
  `processing_order` smallint(6) NOT NULL,
  `filter` tinyint(4) DEFAULT 0,
  `param` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `style` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `template` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `help` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_attachment_recent`;
CREATE TABLE `utils_attachment_recent` (
  `recent_id` int(11) NOT NULL AUTO_INCREMENT,
  `utils_attachment_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visited_on` datetime DEFAULT NULL,
  PRIMARY KEY (`recent_id`),
  KEY `user_id` (`user_id`),
  KEY `utils_attachment_id` (`utils_attachment_id`),
  CONSTRAINT `utils_attachment_recent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `utils_attachment_recent_ibfk_2` FOREIGN KEY (`utils_attachment_id`) REFERENCES `utils_attachment_data_1` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_bbcode`;
CREATE TABLE `utils_bbcode` (
  `code` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `func` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_commondata_tree`;
CREATE TABLE `utils_commondata_tree` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT -1,
  `akey` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `readonly` tinyint(4) DEFAULT 0,
  `position` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parent_id` (`parent_id`,`akey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_currency`;
CREATE TABLE `utils_currency` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `code` varchar(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `decimal_sign` varchar(2) COLLATE utf8_unicode_ci DEFAULT NULL,
  `thousand_sign` varchar(2) COLLATE utf8_unicode_ci DEFAULT NULL,
  `decimals` tinyint(4) DEFAULT NULL,
  `active` tinyint(4) DEFAULT NULL,
  `default_currency` tinyint(4) DEFAULT NULL,
  `pos_before` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_filedownload_files`;
CREATE TABLE `utils_filedownload_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `curr` bigint(20) DEFAULT 0,
  `size` bigint(20) DEFAULT 0,
  `rate` double DEFAULT 0,
  `time` double DEFAULT 0,
  `view_time` double DEFAULT 0,
  `posted_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_filestorage`;
CREATE TABLE `utils_filestorage` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `filename` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `link` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `backref` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `created_by` bigint(20) NOT NULL,
  `deleted` tinyint(4) DEFAULT 0,
  `file_id` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `utils_filestorage_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `utils_filestorage_files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_filestorage_access`;
CREATE TABLE `utils_filestorage_access` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `file_id` bigint(20) NOT NULL,
  `date_accessed` datetime NOT NULL,
  `accessed_by` bigint(20) NOT NULL,
  `type` bigint(20) NOT NULL,
  `ip_address` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `host_name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `utils_filestorage_access_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `utils_filestorage` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_filestorage_files`;
CREATE TABLE `utils_filestorage_files` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `hash` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `deleted` tinyint(4) DEFAULT 0,
  `size` bigint(20) DEFAULT NULL,
  `type` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_filestorage_remote`;
CREATE TABLE `utils_filestorage_remote` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `file_id` bigint(20) NOT NULL,
  `token` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `created_on` datetime NOT NULL,
  `created_by` bigint(20) NOT NULL,
  `expires_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `utils_filestorage_remote_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `utils_filestorage` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_messenger_message`;
CREATE TABLE `utils_messenger_message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `callback_method` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `callback_args` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_on` datetime NOT NULL,
  `alert_on` datetime DEFAULT NULL,
  `parent_module` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `page_id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `utils_messenger_message_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_messenger_users`;
CREATE TABLE `utils_messenger_users` (
  `message_id` int(11) DEFAULT NULL,
  `done` tinyint(4) DEFAULT 0,
  `user_login_id` int(11) DEFAULT NULL,
  `done_on` datetime DEFAULT NULL,
  `follow` tinyint(4) DEFAULT 0,
  KEY `message_id` (`message_id`),
  KEY `user_login_id` (`user_login_id`),
  CONSTRAINT `utils_messenger_users_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `utils_messenger_message` (`id`),
  CONSTRAINT `utils_messenger_users_ibfk_2` FOREIGN KEY (`user_login_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_watchdog_category`;
CREATE TABLE `utils_watchdog_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `callback` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_watchdog_category_subscription`;
CREATE TABLE `utils_watchdog_category_subscription` (
  `category_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `utils_watchdog_category_subscription_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`),
  CONSTRAINT `utils_watchdog_category_subscription_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `utils_watchdog_category` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_watchdog_event`;
CREATE TABLE `utils_watchdog_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `internal_id` int(11) DEFAULT NULL,
  `message` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `event_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `utils_watchdog_event__internal_id__idx` (`internal_id`),
  KEY `utils_watchdog_event__cat_int__idx` (`category_id`,`internal_id`),
  CONSTRAINT `utils_watchdog_event_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `utils_watchdog_category` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_watchdog_notification_queue`;
CREATE TABLE `utils_watchdog_notification_queue` (
  `event_id` int(11) NOT NULL,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `utils_watchdog_subscription`;
CREATE TABLE `utils_watchdog_subscription` (
  `category_id` int(11) DEFAULT NULL,
  `internal_id` int(11) DEFAULT NULL,
  `last_seen_event` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  KEY `utils_watchdog_subscription__cat_int__idx` (`category_id`,`internal_id`),
  KEY `utils_watchdog_subscription__user__idx` (`user_id`),
  CONSTRAINT `utils_watchdog_subscription_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_login` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS `variables`;
CREATE TABLE `variables` (
  `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET FOREIGN_KEY_CHECKS=1;
