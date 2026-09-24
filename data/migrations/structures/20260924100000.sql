-- Stránky: samostatný typ obsahu vedle článků a kategorií článků (O nás, Kontakt, Obchodní podmínky, ...).
-- Bez revizí, štítků a kategorií. Hezká URL přes firecms_urls (type 'page'), do menu přes linkType 'page'.
CREATE TABLE `firecms_pages` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`createdBy` int(11) unsigned DEFAULT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`active` tinyint(1) NOT NULL DEFAULT 1,
	`status` enum('publish','draft') NOT NULL DEFAULT 'draft',
	`public` tinyint(1) NOT NULL DEFAULT 1,
	PRIMARY KEY (`id`),
	KEY `createdBy` (`createdBy`),
	CONSTRAINT `pages_ibfk_1` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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
	PRIMARY KEY (`pageId`, `languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `pageDescriptions_ibfk_1` FOREIGN KEY (`pageId`) REFERENCES `firecms_pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `pageDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- stránka jako cíl položky menu (target = id stránky)
ALTER TABLE `firecms_menuItems`
	MODIFY `linkType` enum('category','article','page','url','route') NOT NULL;
