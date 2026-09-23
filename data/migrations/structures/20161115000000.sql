-- Adminer 6.0.1 MariaDB 12.2.2-MariaDB-ubu2404 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `firecms_articles`;
CREATE TABLE `firecms_articles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `historyId` int(11) unsigned DEFAULT NULL,
  `createdBy` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `publishingDate` datetime NOT NULL,
  `expiringDate` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('publish','pending','draft','auto-draft','trash') NOT NULL DEFAULT 'draft',
  `public` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `historyId` (`historyId`),
  KEY `createdBy` (`createdBy`),
  CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`historyId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_articleComments`;
CREATE TABLE `firecms_articleComments` (
  `articleId` int(11) unsigned NOT NULL,
  `commentId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  KEY `articleId` (`articleId`),
  KEY `commentId` (`commentId`),
  CONSTRAINT `articleComments_ibfk_3` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `articleComments_ibfk_4` FOREIGN KEY (`commentId`) REFERENCES `firecms_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_articleDescriptions`;
CREATE TABLE `firecms_articleDescriptions` (
  `articleId` int(11) unsigned NOT NULL,
  `languageId` char(2) NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `slug` varchar(255) DEFAULT NULL,
  `title` varchar(512) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `seoTitle` text DEFAULT NULL,
  `seoDescription` text DEFAULT NULL,
  `seoKeywords` text DEFAULT NULL,
  `viewCount` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`articleId`,`languageId`),
  KEY `languageId` (`languageId`),
  KEY `articleId` (`articleId`),
  CONSTRAINT `articleDescriptions_ibfk_1` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `articleDescriptions_ibfk_2` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_articleFiles`;
CREATE TABLE `firecms_articleFiles` (
  `articleId` int(11) unsigned NOT NULL,
  `fileId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `isMain` tinyint(1) NOT NULL,
  `position` tinyint(1) NOT NULL,
  PRIMARY KEY (`articleId`,`fileId`),
  KEY `fileId` (`fileId`),
  KEY `articleId` (`articleId`),
  CONSTRAINT `articleFiles_ibfk_1` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `articleFiles_ibfk_2` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_articleMetas`;
CREATE TABLE `firecms_articleMetas` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `articleId` int(11) unsigned NOT NULL,
  `metaId` int(11) unsigned NOT NULL,
  `createdBy` int(10) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `articleIdMetaId` (`articleId`,`metaId`),
  KEY `createdBy` (`createdBy`),
  KEY `metaId` (`metaId`),
  CONSTRAINT `articleMetas_ibfk_1` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`),
  CONSTRAINT `articleMetas_ibfk_3` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`),
  CONSTRAINT `articleMetas_ibfk_5` FOREIGN KEY (`metaId`) REFERENCES `firecms_metas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_articleTags`;
CREATE TABLE `firecms_articleTags` (
  `articleId` int(11) unsigned NOT NULL,
  `tagId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`articleId`,`tagId`),
  KEY `tagId` (`tagId`),
  KEY `articleId` (`articleId`),
  CONSTRAINT `articleTags_ibfk_3` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `articleTags_ibfk_4` FOREIGN KEY (`tagId`) REFERENCES `firecms_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_categories`;
CREATE TABLE `firecms_categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int(11) unsigned DEFAULT NULL,
  `historyId` int(11) unsigned DEFAULT NULL,
  `createdBy` int(11) unsigned NOT NULL DEFAULT 1,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `publishingDate` datetime NOT NULL DEFAULT current_timestamp(),
  `expiringDate` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('publish','pending','draft','auto-draft') NOT NULL DEFAULT 'draft',
  `position` int(11) DEFAULT NULL,
  `level` int(11) NOT NULL DEFAULT 0,
  `categoryLeft` int(11) DEFAULT NULL,
  `categoryRight` int(11) DEFAULT NULL,
  `type` enum('homepage','site','url','categoryLink','gallery','textBox') NOT NULL DEFAULT 'site',
  `showInMenu` tinyint(1) NOT NULL DEFAULT 1,
  `public` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fkParentId` (`parentId`) USING BTREE,
  KEY `categoryLeftCategoryRight` (`categoryLeft`,`categoryRight`),
  KEY `activeYnParentIdPosition` (`active`,`parentId`,`position`),
  KEY `createdBy` (`createdBy`),
  KEY `historyId` (`historyId`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `categories_ibfk_3` FOREIGN KEY (`historyId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_categoryArticle`;
CREATE TABLE `firecms_categoryArticle` (
  `categoryId` int(11) unsigned NOT NULL,
  `articleId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `isMain` tinyint(1) NOT NULL,
  PRIMARY KEY (`categoryId`,`articleId`),
  KEY `categoryId` (`categoryId`),
  KEY `articleId` (`articleId`),
  CONSTRAINT `categoryArticle_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `categoryArticle_ibfk_4` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_categoryComments`;
CREATE TABLE `firecms_categoryComments` (
  `categoryId` int(11) unsigned NOT NULL,
  `commentId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  KEY `categoryId` (`categoryId`),
  KEY `commentId` (`commentId`),
  CONSTRAINT `categoryComments_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `categoryComments_ibfk_4` FOREIGN KEY (`commentId`) REFERENCES `firecms_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_categoryDescriptions`;
CREATE TABLE `firecms_categoryDescriptions` (
  `categoryId` int(11) unsigned NOT NULL,
  `languageId` char(2) NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `slug` varchar(255) DEFAULT NULL,
  `title` varchar(512) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `seoTitle` text DEFAULT NULL,
  `seoDescription` text DEFAULT NULL,
  `seoKeywords` text DEFAULT NULL,
  `viewCount` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`categoryId`,`languageId`),
  KEY `relCategoryCategoryDescription` (`categoryId`) USING BTREE,
  KEY `languageId` (`languageId`),
  CONSTRAINT `categoryDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `categoryDescriptions_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_categoryFiles`;
CREATE TABLE `firecms_categoryFiles` (
  `categoryId` int(11) unsigned NOT NULL,
  `fileId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `isMain` tinyint(1) NOT NULL,
  `position` tinyint(1) NOT NULL,
  PRIMARY KEY (`categoryId`,`fileId`),
  KEY `categoryId` (`categoryId`),
  KEY `fileId` (`fileId`),
  CONSTRAINT `categoryFiles_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `categoryFiles_ibfk_4` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_categoryMetas`;
CREATE TABLE `firecms_categoryMetas` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `categoryId` int(11) unsigned NOT NULL,
  `metaId` int(11) unsigned NOT NULL,
  `createdBy` int(10) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categoryIdMetaId` (`categoryId`,`metaId`),
  KEY `createdBy` (`createdBy`),
  KEY `metaId` (`metaId`),
  CONSTRAINT `categoryMetas_ibfk_1` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`),
  CONSTRAINT `categoryMetas_ibfk_3` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`),
  CONSTRAINT `categoryMetas_ibfk_5` FOREIGN KEY (`metaId`) REFERENCES `firecms_metas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_categoryTags`;
CREATE TABLE `firecms_categoryTags` (
  `categoryId` int(11) unsigned NOT NULL,
  `tagId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  KEY `categoryId` (`categoryId`),
  KEY `tagId` (`tagId`),
  CONSTRAINT `categoryTags_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `categoryTags_ibfk_4` FOREIGN KEY (`tagId`) REFERENCES `firecms_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_comments`;
CREATE TABLE `firecms_comments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int(11) unsigned DEFAULT NULL,
  `languageId` char(2) NOT NULL,
  `createdBy` int(10) unsigned DEFAULT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `left` int(10) unsigned NOT NULL,
  `right` int(10) unsigned NOT NULL,
  `status` enum('publish','pending','trash') NOT NULL,
  `author` varchar(100) DEFAULT NULL,
  `authorEmail` varchar(100) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `text` text NOT NULL,
  `userIp` tinytext NOT NULL,
  `userAgent` tinytext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `createdBy` (`createdBy`),
  KEY `parentId` (`parentId`),
  KEY `languageId` (`languageId`),
  CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `comments_ibfk_4` FOREIGN KEY (`parentId`) REFERENCES `firecms_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `comments_ibfk_6` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_files`;
CREATE TABLE `firecms_files` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fileFolderId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `originalName` varchar(100) NOT NULL,
  `newName` varchar(100) NOT NULL DEFAULT '',
  `diskName` varchar(50) NOT NULL,
  `extension` varchar(5) NOT NULL,
  `mimeType` varchar(20) NOT NULL,
  `size` int(11) NOT NULL,
  `isImage` tinyint(1) NOT NULL,
  `viewCount` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fileFolderId` (`fileFolderId`),
  CONSTRAINT `files_ibfk_2` FOREIGN KEY (`fileFolderId`) REFERENCES `firecms_fileFolders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_fileFolders`;
CREATE TABLE `firecms_fileFolders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int(11) unsigned DEFAULT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `name` varchar(200) NOT NULL,
  `default` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `parentId` (`parentId`),
  CONSTRAINT `fileFolders_ibfk_2` FOREIGN KEY (`parentId`) REFERENCES `firecms_fileFolders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_languages`;
CREATE TABLE `firecms_languages` (
  `languageId` char(2) NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `default` tinyint(1) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `shortcut` varchar(2) NOT NULL,
  PRIMARY KEY (`languageId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_menus`;
CREATE TABLE `firecms_menus` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(256) NOT NULL,
  `location` varchar(256) NOT NULL,
  `createdBy` tinyint(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_menuItems`;
CREATE TABLE `firecms_menuItems` (
  `menuId` int(11) unsigned NOT NULL,
  `categoryId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `position` tinyint(1) NOT NULL,
  PRIMARY KEY (`menuId`,`categoryId`),
  KEY `categoryId` (`categoryId`),
  KEY `menuId` (`menuId`),
  CONSTRAINT `menuItems_ibfk_1` FOREIGN KEY (`menuId`) REFERENCES `firecms_menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `menuItems_ibfk_2` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_metas`;
CREATE TABLE `firecms_metas` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `languageId` char(2) DEFAULT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `type` varchar(20) NOT NULL,
  `isManual` tinyint(1) NOT NULL DEFAULT 1,
  `key` varchar(200) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `languageIdTypeKey` (`languageId`,`type`,`key`),
  CONSTRAINT `metas_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_modules`;
CREATE TABLE `firecms_modules` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int(11) unsigned DEFAULT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `name` varchar(50) DEFAULT NULL,
  `privilege` varchar(50) DEFAULT NULL,
  `title` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parentId` (`parentId`),
  CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `firecms_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `firecms_modules`
	ADD UNIQUE `name` (`name`);


DROP TABLE IF EXISTS `firecms_options`;
CREATE TABLE `firecms_options` (
  `key` varchar(100) NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_roles`;
CREATE TABLE `firecms_roles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int(11) unsigned DEFAULT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `default` tinyint(1) NOT NULL DEFAULT 0,
  `position` tinyint(1) NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `parentId` (`parentId`),
  CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `firecms_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_uca1400_ai_ci;


DROP TABLE IF EXISTS `firecms_roleModule`;
CREATE TABLE `firecms_roleModule` (
  `roleId` int(11) unsigned NOT NULL,
  `moduleId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `privilege` varchar(15) NOT NULL,
  PRIMARY KEY (`roleId`,`moduleId`),
  KEY `moduleId` (`moduleId`),
  CONSTRAINT `roleModule_ibfk_6` FOREIGN KEY (`moduleId`) REFERENCES `firecms_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `roleModule_ibfk_7` FOREIGN KEY (`roleId`) REFERENCES `firecms_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_tags`;
CREATE TABLE `firecms_tags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_tagDescriptions`;
CREATE TABLE `firecms_tagDescriptions` (
  `tagId` int(11) unsigned NOT NULL,
  `languageId` char(2) NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`tagId`,`languageId`,`name`),
  KEY `languageId` (`languageId`),
  KEY `tagId` (`tagId`),
  CONSTRAINT `tagDescriptions_ibfk_3` FOREIGN KEY (`tagId`) REFERENCES `firecms_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tagDescriptions_ibfk_4` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_users`;
CREATE TABLE `firecms_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `roleId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `username` varchar(60) NOT NULL,
  `password` varchar(60) NOT NULL,
  `email` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `firstName` varchar(50) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `recoveryPasswordTime` datetime DEFAULT NULL,
  `recoveryPasswordToken` varchar(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loginKey` (`username`),
  KEY `nicknameKey` (`nickname`),
  KEY `roleId` (`roleId`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`roleId`) REFERENCES `firecms_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `firecms_urls`;
CREATE TABLE `firecms_urls` (
						`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
						`languageId` char(2) NOT NULL,
						`createDate` datetime NOT NULL DEFAULT current_timestamp(),
						`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
						`type` varchar(128) NOT NULL,
						`key` int(10) unsigned NOT NULL,
						`url` varchar(2000) NOT NULL,
						PRIMARY KEY (`id`),
						UNIQUE KEY `languageIdTypeKey` (`languageId`,`type`,`key`),
						CONSTRAINT `urls_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `firecms_urlRedirections`;
CREATE TABLE `firecms_urlRedirections` (
									`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
									`languageId` char(2) NOT NULL,
									`createDate` datetime NOT NULL DEFAULT current_timestamp(),
									`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
									`oldUrl` varchar(2000) NOT NULL,
									`newUrl` varchar(2000) NOT NULL,
									`lastUsageDate` datetime DEFAULT NULL,
									PRIMARY KEY (`id`),
									KEY `languageId` (`languageId`),
									CONSTRAINT `urlRedirections_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 2026-09-14 12:23:27 UTC
