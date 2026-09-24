-- Sekce: znovupoužitelné skupiny "kategorie + články" (Blog, Novinky, Reference, ...). Kategorie článků i
-- články patří do právě jedné sekce, kategorie článku musí být ze stejné sekce (hlídá aplikace).
-- Výpis sekce na webu má hezkou URL typu `section` (Front:Sections:detail), do menu přes linkType `section`.
-- Sekci s kategoriemi/články nejde smazat (FK RESTRICT) - obsah se nesmaže omylem.
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
	PRIMARY KEY (`sectionId`, `languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `sectionDescriptions_ibfk_1` FOREIGN KEY (`sectionId`) REFERENCES `firecms_sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `sectionDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- existující kategorie/články se do sekcí převádějí ručně (bez výchozí hodnoty, NOT NULL)
ALTER TABLE `firecms_categories`
	ADD COLUMN `sectionId` int(11) unsigned NOT NULL AFTER `id`,
	ADD KEY `sectionId` (`sectionId`),
	ADD CONSTRAINT `categories_section_fk` FOREIGN KEY (`sectionId`) REFERENCES `firecms_sections` (`id`) ON UPDATE CASCADE;

ALTER TABLE `firecms_articles`
	ADD COLUMN `sectionId` int(11) unsigned NOT NULL AFTER `id`,
	ADD KEY `sectionId` (`sectionId`),
	ADD CONSTRAINT `articles_section_fk` FOREIGN KEY (`sectionId`) REFERENCES `firecms_sections` (`id`) ON UPDATE CASCADE;

ALTER TABLE `firecms_menuItems`
	MODIFY `linkType` enum('category','article','page','section','url','route') NOT NULL;
