DROP TABLE IF EXISTS `firecms_settingDescriptions`;
DROP TABLE IF EXISTS `firecms_settings`;

CREATE TABLE `firecms_settings` (
	`settingId` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`modifyDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`imageResolution` varchar(20) NOT NULL,
	`themePath` varchar(100) DEFAULT NULL,
	PRIMARY KEY (`settingId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `firecms_settingDescriptions` (
	`settingId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`modifyDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`mainTitle` varchar(255) DEFAULT NULL,
	`mainDescription` text DEFAULT NULL,
	`mainEmail` varchar(100) DEFAULT NULL,
	`seoTitle` text DEFAULT NULL,
	`seoDescription` text DEFAULT NULL,
	`seoKeywords` text DEFAULT NULL,
	PRIMARY KEY (`settingId`,`languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `settingDescriptions_ibfk_1` FOREIGN KEY (`settingId`) REFERENCES `firecms_settings` (`settingId`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `settingDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `firecms_settings` (`settingId`, `imageResolution`, `themePath`) VALUES
	(1,	'1000x1000',	'default');
INSERT INTO `firecms_settingDescriptions` (`settingId`, `languageId`,  `mainTitle`, `mainDescription`, `mainEmail`, `seoTitle`, `seoDescription`, `seoKeywords`) VALUES
	(1,	'en',	'Fire CMS / FrontEnd - Testovací stránka',	'Toto je popis webu',	'info@example.com',	NULL,	'Seo popis',	'default');

DROP TABLE IF EXISTS `firecms_options`;