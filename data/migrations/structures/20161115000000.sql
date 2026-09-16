-- Adminer 6.0.1 MariaDB 12.2.2-MariaDB-ubu2404 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `articles`;
CREATE TABLE `articles` (
  `article_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `history_id` int(11) unsigned DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `update_date` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `publishing_date` datetime NOT NULL,
  `expiring_date` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('publish','pending','draft','auto-draft','trash') NOT NULL DEFAULT 'draft',
  `grid_name` varchar(512) DEFAULT NULL,
  `created_by` int(11) unsigned NOT NULL,
  `public` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`article_id`),
  KEY `history_id` (`history_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`history_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `article_comments`;
CREATE TABLE `article_comments` (
  `article_id` int(11) unsigned NOT NULL,
  `comment_id` int(11) unsigned NOT NULL,
  KEY `article_id` (`article_id`),
  KEY `comment_id` (`comment_id`),
  CONSTRAINT `article_comments_ibfk_3` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `article_comments_ibfk_4` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `article_descriptions`;
CREATE TABLE `article_descriptions` (
  `article_id` int(11) unsigned NOT NULL,
  `language_id` char(2) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `title` varchar(512) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `seo_title` text DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `seo_keywords` text DEFAULT NULL,
  `view_count` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`article_id`,`language_id`),
  KEY `language_id` (`language_id`),
  KEY `article_id` (`article_id`),
  CONSTRAINT `article_descriptions_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `article_descriptions_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `article_files`;
CREATE TABLE `article_files` (
  `article_id` int(11) unsigned NOT NULL,
  `file_id` int(11) unsigned NOT NULL,
  `is_main` tinyint(1) NOT NULL,
  `position` tinyint(1) NOT NULL,
  PRIMARY KEY (`article_id`,`file_id`),
  KEY `file_id` (`file_id`),
  KEY `article_id` (`article_id`),
  CONSTRAINT `article_files_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `article_files_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `article_metas`;
CREATE TABLE `article_metas` (
  `article_meta_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(11) unsigned NOT NULL,
  `meta_id` int(11) unsigned NOT NULL,
  `create_date` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`article_meta_id`),
  UNIQUE KEY `article_id_meta_id` (`article_id`,`meta_id`),
  KEY `created_by` (`created_by`),
  KEY `meta_id` (`meta_id`),
  CONSTRAINT `article_metas_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`),
  CONSTRAINT `article_metas_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `article_metas_ibfk_5` FOREIGN KEY (`meta_id`) REFERENCES `metas` (`meta_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `article_tags`;
CREATE TABLE `article_tags` (
  `article_id` int(11) unsigned NOT NULL,
  `tag_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  KEY `article_id` (`article_id`),
  CONSTRAINT `article_tags_ibfk_3` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `article_tags_ibfk_4` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;


DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `category_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `history_id` int(11) unsigned DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `update_date` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `publishing_date` datetime NOT NULL DEFAULT current_timestamp(),
  `expiring_date` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('publish','pending','draft','auto-draft') NOT NULL DEFAULT 'draft',
  `grid_name` varchar(512) DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  `level` int(11) NOT NULL DEFAULT 0,
  `category_left` int(11) DEFAULT NULL,
  `category_right` int(11) DEFAULT NULL,
  `created_by` int(11) unsigned NOT NULL DEFAULT 1,
  `type` enum('homepage','site','url','categoryLink','gallery','textBox') NOT NULL DEFAULT 'site',
  `show_in_menu` tinyint(1) NOT NULL DEFAULT 1,
  `public` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`category_id`),
  KEY `fk_parent_id` (`parent_id`) USING BTREE,
  KEY `category_left_category_right` (`category_left`,`category_right`),
  KEY `active_yn_parent_id_position` (`active`,`parent_id`,`position`),
  KEY `created_by` (`created_by`),
  KEY `history_id` (`history_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `categories_ibfk_3` FOREIGN KEY (`history_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `category_article`;
CREATE TABLE `category_article` (
  `category_id` int(11) unsigned NOT NULL,
  `article_id` int(11) unsigned NOT NULL,
  `is_main` tinyint(1) NOT NULL,
  PRIMARY KEY (`category_id`,`article_id`),
  KEY `category_id` (`category_id`),
  KEY `article_id` (`article_id`),
  CONSTRAINT `category_article_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `category_article_ibfk_4` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `category_comments`;
CREATE TABLE `category_comments` (
  `category_id` int(11) unsigned NOT NULL,
  `comment_id` int(11) unsigned NOT NULL,
  KEY `category_id` (`category_id`),
  KEY `comment_id` (`comment_id`),
  CONSTRAINT `category_comments_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `category_comments_ibfk_4` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `category_descriptions`;
CREATE TABLE `category_descriptions` (
  `category_id` int(11) unsigned NOT NULL,
  `language_id` char(2) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `title` varchar(512) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `seo_title` text DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `seo_keywords` text DEFAULT NULL,
  `view_count` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`category_id`,`language_id`),
  KEY `rel_category_category_description` (`category_id`) USING BTREE,
  KEY `language_id` (`language_id`),
  CONSTRAINT `category_descriptions_ibfk_2` FOREIGN KEY (`language_id`) REFERENCES `languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `category_descriptions_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `category_files`;
CREATE TABLE `category_files` (
  `category_id` int(11) unsigned NOT NULL,
  `file_id` int(11) unsigned NOT NULL,
  `is_main` tinyint(1) NOT NULL,
  `position` tinyint(1) NOT NULL,
  PRIMARY KEY (`category_id`,`file_id`),
  KEY `category_id` (`category_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `category_files_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `category_files_ibfk_4` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `category_metas`;
CREATE TABLE `category_metas` (
  `category_meta_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(11) unsigned NOT NULL,
  `meta_id` int(11) unsigned NOT NULL,
  `create_date` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`category_meta_id`),
  UNIQUE KEY `category_id_meta_id` (`category_id`,`meta_id`),
  KEY `created_by` (`created_by`),
  KEY `meta_id` (`meta_id`),
  CONSTRAINT `category_metas_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  CONSTRAINT `category_metas_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `category_metas_ibfk_5` FOREIGN KEY (`meta_id`) REFERENCES `metas` (`meta_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `category_tags`;
CREATE TABLE `category_tags` (
  `category_id` int(11) unsigned NOT NULL,
  `tag_id` int(11) unsigned NOT NULL,
  KEY `category_id` (`category_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `category_tags_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `category_tags_ibfk_4` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;


DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `comment_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `language_id` char(2) NOT NULL,
  `left` int(10) unsigned NOT NULL,
  `right` int(10) unsigned NOT NULL,
  `create_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('publish','pending','trash') NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `author` varchar(100) DEFAULT NULL,
  `author_email` varchar(100) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `text` text NOT NULL,
  `user_ip` tinytext NOT NULL,
  `user_agent` tinytext NOT NULL,
  PRIMARY KEY (`comment_id`),
  KEY `created_by` (`created_by`),
  KEY `parent_id` (`parent_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `comments_ibfk_4` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `comments_ibfk_6` FOREIGN KEY (`language_id`) REFERENCES `languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `file_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `file_folder_id` int(11) unsigned NOT NULL,
  `create_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `original_name` varchar(100) NOT NULL,
  `new_name` varchar(100) NOT NULL DEFAULT '',
  `disk_name` varchar(50) NOT NULL,
  `extension` varchar(5) NOT NULL,
  `mime_type` varchar(20) NOT NULL,
  `size` int(11) NOT NULL,
  `is_image` tinyint(1) NOT NULL,
  `view_count` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`file_id`),
  KEY `file_folder_id` (`file_folder_id`),
  CONSTRAINT `files_ibfk_2` FOREIGN KEY (`file_folder_id`) REFERENCES `file_folders` (`file_folder_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `file_folders`;
CREATE TABLE `file_folders` (
  `file_folder_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `create_date` datetime NOT NULL DEFAULT current_timestamp(),
  `name` varchar(200) NOT NULL,
  `default` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`file_folder_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `file_folders_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `file_folders` (`file_folder_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `languages`;
CREATE TABLE `languages` (
  `language_id` char(2) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `default` tinyint(1) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `shortcut` varchar(2) NOT NULL,
  PRIMARY KEY (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `menus`;
CREATE TABLE `menus` (
  `menu_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(256) NOT NULL,
  `location` varchar(256) NOT NULL,
  `created_by` tinyint(11) DEFAULT NULL,
  PRIMARY KEY (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE `menu_items` (
  `menu_id` int(11) unsigned NOT NULL,
  `category_id` int(11) unsigned NOT NULL,
  `position` tinyint(1) NOT NULL,
  PRIMARY KEY (`menu_id`,`category_id`),
  KEY `category_id` (`category_id`),
  KEY `menu_id` (`menu_id`),
  CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`menu_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `menu_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `metas`;
CREATE TABLE `metas` (
  `meta_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` char(2) DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `is_manual` tinyint(1) NOT NULL DEFAULT 1,
  `key` varchar(200) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`meta_id`),
  UNIQUE KEY `language_id_type_key` (`language_id`,`type`,`key`),
  CONSTRAINT `metas_ibfk_2` FOREIGN KEY (`language_id`) REFERENCES `languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `modules`;
CREATE TABLE `modules` (
  `module_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `privilege` varchar(50) DEFAULT NULL,
  `title` varchar(50) NOT NULL,
  PRIMARY KEY (`module_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `modules` (`module_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `options`;
CREATE TABLE `options` (
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `role_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `default` tinyint(1) NOT NULL DEFAULT 0,
  `position` tinyint(1) NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_uca1400_ai_ci NOT NULL,
  `title` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_uca1400_ai_ci NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `name` (`name`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_uca1400_ai_ci;


DROP TABLE IF EXISTS `role_module`;
CREATE TABLE `role_module` (
  `role_id` int(11) unsigned NOT NULL,
  `module_id` int(11) unsigned NOT NULL,
  `privilege` varchar(15) NOT NULL,
  PRIMARY KEY (`role_id`,`module_id`),
  KEY `module_id` (`module_id`),
  CONSTRAINT `role_module_ibfk_6` FOREIGN KEY (`module_id`) REFERENCES `modules` (`module_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `role_module_ibfk_7` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `sliders`;
CREATE TABLE `sliders` (
  `slider_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `create_date` datetime NOT NULL,
  `duration` int(10) unsigned NOT NULL,
  `speed` int(10) unsigned NOT NULL,
  `navigation` tinyint(1) unsigned NOT NULL,
  `manual` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`slider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `slider_items`;
CREATE TABLE `slider_items` (
  `slider_id` int(11) unsigned NOT NULL,
  `language_id` char(2) NOT NULL,
  `position` tinyint(1) unsigned NOT NULL,
  `file_id` int(11) unsigned NOT NULL,
  `url` varchar(256) NOT NULL DEFAULT '',
  `text` varchar(256) NOT NULL DEFAULT '',
  PRIMARY KEY (`slider_id`,`language_id`,`position`),
  KEY `language_id` (`language_id`),
  KEY `file_id` (`file_id`),
  KEY `slider_id` (`slider_id`),
  CONSTRAINT `slider_items_ibfk_2` FOREIGN KEY (`slider_id`) REFERENCES `sliders` (`slider_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `slider_items_ibfk_5` FOREIGN KEY (`language_id`) REFERENCES `languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `slider_items_ibfk_6` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `stalkers`;
CREATE TABLE `stalkers` (
  `stalker_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `create_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) unsigned NOT NULL,
  `ip` tinytext NOT NULL,
  `url` text NOT NULL,
  `data` text DEFAULT NULL,
  PRIMARY KEY (`stalker_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `stalkers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;


DROP TABLE IF EXISTS `statistics`;
CREATE TABLE `statistics` (
  `statistics_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `create_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `session` varchar(26) NOT NULL,
  `ip` tinytext NOT NULL,
  `agent` text NOT NULL,
  PRIMARY KEY (`statistics_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;


DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `tag_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `create_date` datetime NOT NULL DEFAULT current_timestamp(),
  `grid_name` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `tag_descriptions`;
CREATE TABLE `tag_descriptions` (
  `tag_id` int(11) unsigned NOT NULL,
  `language_id` char(2) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`tag_id`,`language_id`,`name`),
  KEY `language_id` (`language_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `tag_descriptions_ibfk_3` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tag_descriptions_ibfk_4` FOREIGN KEY (`language_id`) REFERENCES `languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL,
  `password` varchar(60) NOT NULL,
  `email` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `role_id` int(11) unsigned NOT NULL,
  `recovery_password_time` datetime DEFAULT NULL,
  `recovery_password_token` varchar(24) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `login_key` (`username`),
  KEY `nickname_key` (`nickname`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;


-- 2026-09-14 12:23:27 UTC
