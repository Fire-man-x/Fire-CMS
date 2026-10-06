SET NAMES utf8mb4;

CREATE TABLE `firecms_roles` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`parentId` int(11) unsigned DEFAULT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`default` tinyint(1) NOT NULL DEFAULT 0,
	`position` tinyint(1) NOT NULL,
	`name` varchar(50) NOT NULL,
	`title` varchar(50) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `name` (`name`),
	KEY `parentId` (`parentId`),
	CONSTRAINT `firecms_roles_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `firecms_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	`oauthService` varchar(20) DEFAULT NULL,
	`oauthId` varchar(20) DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `loginKey` (`username`),
	UNIQUE KEY `email` (`email`),
	KEY `nicknameKey` (`nickname`),
	KEY `roleId` (`roleId`),
	CONSTRAINT `firecms_users_ibfk_2` FOREIGN KEY (`roleId`) REFERENCES `firecms_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_languages` (
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`active` tinyint(1) NOT NULL DEFAULT 1,
	`default` tinyint(1) NOT NULL DEFAULT 0,
	`position` int(11) NOT NULL,
	`name` varchar(20) NOT NULL,
	`shortcut` varchar(2) NOT NULL,
	PRIMARY KEY (`languageId`),
	KEY `activeDefault` (`active`,`default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_comments_ibfk_3` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_comments_ibfk_4` FOREIGN KEY (`parentId`) REFERENCES `firecms_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_comments_ibfk_6` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_sections` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`active` tinyint(1) NOT NULL DEFAULT 1,
	`position` int(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_sectionDescriptions` (
	`sectionId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`title` varchar(255) DEFAULT NULL,
	`content` longtext DEFAULT NULL,
	`seoTitle` text DEFAULT NULL,
	`seoDescription` text DEFAULT NULL,
	`seoKeywords` text DEFAULT NULL,
	PRIMARY KEY (`sectionId`,`languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `firecms_sectionDescriptions_ibfk_1` FOREIGN KEY (`sectionId`) REFERENCES `firecms_sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_sectionDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_settings` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`imageResolution` varchar(20) NOT NULL,
	`themePath` varchar(100) DEFAULT NULL,
	`contactPhone` varchar(50) DEFAULT NULL,
	`mapLatitude` decimal(9,6) DEFAULT NULL,
	`mapLongitude` decimal(9,6) DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_settingDescriptions` (
	`settingId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`mainTitle` varchar(255) DEFAULT NULL,
	`mainDescription` text DEFAULT NULL,
	`mainEmail` varchar(100) DEFAULT NULL,
	`contactAddress` text DEFAULT NULL,
	`seoTitle` text DEFAULT NULL,
	`seoDescription` text DEFAULT NULL,
	`seoKeywords` text DEFAULT NULL,
	PRIMARY KEY (`settingId`,`languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `firecms_settingDescriptions_ibfk_1` FOREIGN KEY (`settingId`) REFERENCES `firecms_settings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_settingDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_articles` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`sectionId` int(11) unsigned NOT NULL,
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
	KEY `sectionId` (`sectionId`),
	CONSTRAINT `firecms_articles_ibfk_1` FOREIGN KEY (`historyId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_articles_ibfk_2` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_articles_section_fk` FOREIGN KEY (`sectionId`) REFERENCES `firecms_sections` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_categories` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`sectionId` int(11) unsigned NOT NULL,
	`parentId` int(11) unsigned DEFAULT NULL,
	`historyId` int(11) unsigned DEFAULT NULL,
	`createdBy` int(11) unsigned NOT NULL DEFAULT 1,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`publishingDate` datetime NOT NULL DEFAULT current_timestamp(),
	`expiringDate` datetime DEFAULT NULL,
	`active` tinyint(1) NOT NULL DEFAULT 1,
	`status` enum('publish','pending','draft','auto-draft','trash') NOT NULL DEFAULT 'draft',
	`position` int(11) DEFAULT NULL,
	`level` int(11) NOT NULL DEFAULT 0,
	`categoryLeft` int(11) DEFAULT NULL,
	`categoryRight` int(11) DEFAULT NULL,
	`showInMenu` tinyint(1) NOT NULL DEFAULT 1,
	`public` tinyint(1) NOT NULL DEFAULT 1,
	PRIMARY KEY (`id`),
	KEY `fkParentId` (`parentId`) USING BTREE,
	KEY `categoryLeftCategoryRight` (`categoryLeft`,`categoryRight`),
	KEY `activeYnParentIdPosition` (`active`,`parentId`,`position`),
	KEY `createdBy` (`createdBy`),
	KEY `historyId` (`historyId`),
	KEY `sectionId` (`sectionId`),
	CONSTRAINT `firecms_categories_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_categories_ibfk_2` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON UPDATE CASCADE,
	CONSTRAINT `firecms_categories_ibfk_3` FOREIGN KEY (`historyId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_categories_section_fk` FOREIGN KEY (`sectionId`) REFERENCES `firecms_sections` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_articleComments` (
	`articleId` int(11) unsigned NOT NULL,
	`commentId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	KEY `articleId` (`articleId`),
	KEY `commentId` (`commentId`),
	CONSTRAINT `firecms_articleComments_ibfk_3` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_articleComments_ibfk_4` FOREIGN KEY (`commentId`) REFERENCES `firecms_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_articleDescriptions` (
	`articleId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
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
	CONSTRAINT `firecms_articleDescriptions_ibfk_1` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_articleDescriptions_ibfk_2` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_fileFolders_ibfk_2` FOREIGN KEY (`parentId`) REFERENCES `firecms_fileFolders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_files_ibfk_2` FOREIGN KEY (`fileFolderId`) REFERENCES `firecms_fileFolders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_articleFiles_ibfk_1` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_articleFiles_ibfk_2` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_metas_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_tags` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_tagDescriptions` (
	`tagId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`name` varchar(100) NOT NULL,
	PRIMARY KEY (`tagId`,`languageId`,`name`),
	KEY `languageId` (`languageId`),
	KEY `tagId` (`tagId`),
	CONSTRAINT `firecms_tagDescriptions_ibfk_3` FOREIGN KEY (`tagId`) REFERENCES `firecms_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_tagDescriptions_ibfk_4` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_articleMetas_ibfk_1` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`),
	CONSTRAINT `firecms_articleMetas_ibfk_3` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`),
	CONSTRAINT `firecms_articleMetas_ibfk_5` FOREIGN KEY (`metaId`) REFERENCES `firecms_metas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_articleTags` (
	`articleId` int(11) unsigned NOT NULL,
	`tagId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	PRIMARY KEY (`articleId`,`tagId`),
	KEY `tagId` (`tagId`),
	KEY `articleId` (`articleId`),
	CONSTRAINT `firecms_articleTags_ibfk_3` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_articleTags_ibfk_4` FOREIGN KEY (`tagId`) REFERENCES `firecms_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_categoryArticle` (
	`categoryId` int(11) unsigned NOT NULL,
	`articleId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`isMain` tinyint(1) NOT NULL,
	PRIMARY KEY (`categoryId`,`articleId`),
	KEY `categoryId` (`categoryId`),
	KEY `articleId` (`articleId`),
	CONSTRAINT `firecms_categoryArticle_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_categoryArticle_ibfk_4` FOREIGN KEY (`articleId`) REFERENCES `firecms_articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_categoryComments` (
	`categoryId` int(11) unsigned NOT NULL,
	`commentId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	KEY `categoryId` (`categoryId`),
	KEY `commentId` (`commentId`),
	CONSTRAINT `firecms_categoryComments_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_categoryComments_ibfk_4` FOREIGN KEY (`commentId`) REFERENCES `firecms_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_categoryDescriptions` (
	`categoryId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
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
	CONSTRAINT `firecms_categoryDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_categoryDescriptions_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_categoryFiles_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_categoryFiles_ibfk_4` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_categoryMetas_ibfk_1` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`),
	CONSTRAINT `firecms_categoryMetas_ibfk_3` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`),
	CONSTRAINT `firecms_categoryMetas_ibfk_5` FOREIGN KEY (`metaId`) REFERENCES `firecms_metas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_categoryTags` (
	`categoryId` int(11) unsigned NOT NULL,
	`tagId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	KEY `categoryId` (`categoryId`),
	KEY `tagId` (`tagId`),
	CONSTRAINT `firecms_categoryTags_ibfk_3` FOREIGN KEY (`categoryId`) REFERENCES `firecms_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_categoryTags_ibfk_4` FOREIGN KEY (`tagId`) REFERENCES `firecms_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_cronTasks` (
	`name` varchar(100) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`lastRunAt` datetime DEFAULT NULL,
	`lastFinishedAt` datetime DEFAULT NULL,
	`lastStatus` enum('ok','error') DEFAULT NULL,
	`lastMessage` varchar(1000) DEFAULT NULL,
	`lastDuration` decimal(10,3) unsigned DEFAULT NULL,
	PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_domains` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`domain` varchar(255) NOT NULL,
	`active` tinyint(1) NOT NULL DEFAULT 1,
	`default` tinyint(1) NOT NULL DEFAULT 1,
	`position` int(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	UNIQUE KEY `domain` (`domain`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `firecms_domains_ibfk_1` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_dynamicForms` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`itemsSpecifications` text NOT NULL,
	`templateName` varchar(50) NOT NULL,
	`whereToSend` varchar(20) DEFAULT NULL,
	`afterSendInformations` varchar(200) DEFAULT NULL,
	`createdBy` int(10) unsigned NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `templateName` (`templateName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_dynamicFormDescriptions` (
	`dynamicFormId` int(10) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`title` varchar(50) NOT NULL,
	`items` text NOT NULL,
	`submitMessage` varchar(200) DEFAULT NULL,
	PRIMARY KEY (`dynamicFormId`,`languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `firecms_dynamicFormDescriptions_ibfk_3` FOREIGN KEY (`dynamicFormId`) REFERENCES `firecms_dynamicForms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_dynamicFormDescriptions_ibfk_4` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_dynamicFormSendedValues` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`dynamicFormId` int(10) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`sendedValues` text NOT NULL,
	PRIMARY KEY (`id`),
	KEY `dynamicFormId` (`dynamicFormId`),
	KEY `languageId` (`languageId`),
	KEY `dynamicFormIdLanguageId` (`dynamicFormId`,`languageId`),
	CONSTRAINT `firecms_dynamicFormSendedValues_ibfk_1` FOREIGN KEY (`dynamicFormId`) REFERENCES `firecms_dynamicForms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_dynamicFormSendedValues_ibfk_3` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_menus` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`active` tinyint(1) NOT NULL DEFAULT 1,
	`location` varchar(256) NOT NULL,
	`createdBy` tinyint(11) DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_menuDescriptions` (
	`menuId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`title` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`menuId`,`languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `firecms_menuDescriptions_ibfk_1` FOREIGN KEY (`menuId`) REFERENCES `firecms_menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_menuDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_menuItems` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`menuId` int(11) unsigned NOT NULL,
	`parentId` int(11) unsigned DEFAULT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`active` tinyint(1) NOT NULL DEFAULT 1,
	`position` int(11) NOT NULL DEFAULT 0,
	`linkType` enum('category','article','page','section','url','route') NOT NULL,
	`target` varchar(512) NOT NULL,
	`newWindow` tinyint(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `menuId` (`menuId`),
	KEY `parentId` (`parentId`),
	KEY `linkTypeTarget` (`linkType`,`target`(191)),
	CONSTRAINT `firecms_menuItems_ibfk_1` FOREIGN KEY (`menuId`) REFERENCES `firecms_menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_menuItems_ibfk_2` FOREIGN KEY (`parentId`) REFERENCES `firecms_menuItems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_menuItemDescriptions` (
	`menuItemId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`label` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`menuItemId`,`languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `firecms_menuItemDescriptions_ibfk_1` FOREIGN KEY (`menuItemId`) REFERENCES `firecms_menuItems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_menuItemDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_menuItemFiles` (
	`menuItemId` int(11) unsigned NOT NULL,
	`fileId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`position` int(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (`menuItemId`,`fileId`),
	KEY `fileId` (`fileId`),
	CONSTRAINT `firecms_menuItemFiles_ibfk_1` FOREIGN KEY (`menuItemId`) REFERENCES `firecms_menuItems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_menuItemFiles_ibfk_2` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_modules` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`parentId` int(11) unsigned DEFAULT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`name` varchar(50) DEFAULT NULL,
	`privilege` varchar(50) DEFAULT NULL,
	`title` varchar(50) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `name` (`name`),
	KEY `parentId` (`parentId`),
	KEY `namePrivilege` (`name`,`privilege`),
	CONSTRAINT `firecms_modules_ibfk_1` FOREIGN KEY (`parentId`) REFERENCES `firecms_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_pages` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`parentId` int(11) unsigned DEFAULT NULL,
	`createdBy` int(11) unsigned DEFAULT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`active` tinyint(1) NOT NULL DEFAULT 1,
	`status` enum('publish','draft') NOT NULL DEFAULT 'draft',
	`public` tinyint(1) NOT NULL DEFAULT 1,
	`position` int(11) NOT NULL DEFAULT 0,
	`template` varchar(50) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `createdBy` (`createdBy`),
	KEY `parentId` (`parentId`),
	CONSTRAINT `firecms_pages_ibfk_1` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT `firecms_pages_ibfk_2` FOREIGN KEY (`parentId`) REFERENCES `firecms_pages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_pageDescriptions` (
	`pageId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`title` varchar(512) DEFAULT NULL,
	`content` longtext DEFAULT NULL,
	`seoTitle` text DEFAULT NULL,
	`seoDescription` text DEFAULT NULL,
	`seoKeywords` text DEFAULT NULL,
	PRIMARY KEY (`pageId`,`languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `firecms_pageDescriptions_ibfk_1` FOREIGN KEY (`pageId`) REFERENCES `firecms_pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_pageDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_pageFiles` (
	`pageId` int(11) unsigned NOT NULL,
	`fileId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`position` int(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (`pageId`,`fileId`),
	KEY `fileId` (`fileId`),
	CONSTRAINT `firecms_pageFiles_ibfk_1` FOREIGN KEY (`pageId`) REFERENCES `firecms_pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_pageFiles_ibfk_2` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_roleModule` (
	`roleId` int(11) unsigned NOT NULL,
	`moduleId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`privilege` varchar(15) NOT NULL,
	PRIMARY KEY (`roleId`,`moduleId`),
	KEY `moduleId` (`moduleId`),
	CONSTRAINT `firecms_roleModule_ibfk_6` FOREIGN KEY (`moduleId`) REFERENCES `firecms_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_roleModule_ibfk_7` FOREIGN KEY (`roleId`) REFERENCES `firecms_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_sliders` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`name` varchar(100) NOT NULL,
	`location` varchar(100) NOT NULL,
	`duration` int(10) unsigned NOT NULL,
	`speed` int(10) unsigned NOT NULL,
	`navigation` tinyint(1) unsigned NOT NULL,
	`manual` tinyint(1) unsigned NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `firecms_sliderItems` (
	`sliderId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`fileId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`position` tinyint(1) unsigned NOT NULL,
	`url` varchar(256) NOT NULL DEFAULT '',
	`text` varchar(256) NOT NULL DEFAULT '',
	PRIMARY KEY (`sliderId`,`languageId`,`position`),
	KEY `languageId` (`languageId`),
	KEY `fileId` (`fileId`),
	KEY `sliderId` (`sliderId`),
	CONSTRAINT `firecms_sliderItems_ibfk_2` FOREIGN KEY (`sliderId`) REFERENCES `firecms_sliders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_sliderItems_ibfk_5` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `firecms_sliderItems_ibfk_6` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_urlRedirections_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
	CONSTRAINT `firecms_urls_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;