-- Položky menu oddělené od kategorií: položka má vlastní id, rodiče (vnořování), typ odkazu a cíl.
-- Dřív byla položka jen vazba menu -> kategorie (PK menuId+categoryId).
--
-- linkType / target:
--   category - target = id kategorie (webové menu k ní dál připojí podkategorie se showInMenu)
--   article  - target = id článku
--   url      - target = absolutní/relativní URL nebo kotva (https://..., /kontakt, #jak-to-funguje)
--   route    - target = Nette odkaz presenteru, volitelně s parametry: ":Front:Properties:default?town=Brno"
-- `target` je jeden sloupec pro všechny typy, takže na kategorii/článek nevede cizí klíč. Úklid při
-- smazání kategorie dělá App\Model\Categories::delete() (články se mažou jen do koše, viz status).

-- stávající vazby do dočasné tabulky bez constraintů (názvy menuItems_ibfk_* převezme nová tabulka)
CREATE TABLE `firecms_menuItemsLegacy` AS SELECT * FROM `firecms_menuItems`;
DROP TABLE `firecms_menuItems`;

CREATE TABLE `firecms_menuItems` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`menuId` int(11) unsigned NOT NULL,
	`parentId` int(11) unsigned DEFAULT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`active` tinyint(1) NOT NULL DEFAULT 1,
	`position` int(11) NOT NULL DEFAULT 0,
	`linkType` enum('category','article','url','route') NOT NULL,
	`target` varchar(512) NOT NULL,
	`newWindow` tinyint(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `menuId` (`menuId`),
	KEY `parentId` (`parentId`),
	KEY `linkTypeTarget` (`linkType`, `target`(191)),
	CONSTRAINT `menuItems_ibfk_1` FOREIGN KEY (`menuId`) REFERENCES `firecms_menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `menuItems_ibfk_2` FOREIGN KEY (`parentId`) REFERENCES `firecms_menuItems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- popisek položky v jednotlivých jazycích; NULL/chybějící = název kategorie/článku (u url/route povinný)
CREATE TABLE `firecms_menuItemDescriptions` (
	`menuItemId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`label` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`menuItemId`, `languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `menuItemDescriptions_ibfk_1` FOREIGN KEY (`menuItemId`) REFERENCES `firecms_menuItems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `menuItemDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- stávající položky = odkazy na kategorie, ve stejném pořadí; popisek zůstává prázdný (= název kategorie)
INSERT INTO `firecms_menuItems` (`menuId`, `parentId`, `createDate`, `updateDate`, `active`, `position`, `linkType`, `target`)
SELECT `menuId`, NULL, `createDate`, `updateDate`, 1, `position`, 'category', CAST(`categoryId` AS CHAR)
FROM `firecms_menuItemsLegacy`
ORDER BY `menuId`, `position`;

DROP TABLE `firecms_menuItemsLegacy`;

CREATE TABLE `firecms_menuDescriptions` (
	`menuId` int(11) unsigned NOT NULL,
	`languageId` char(2) NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`title` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`menuId`, `languageId`),
	KEY `languageId` (`languageId`),
	CONSTRAINT `menuDescriptions_ibfk_1` FOREIGN KEY (`menuId`) REFERENCES `firecms_menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `menuDescriptions_ibfk_2` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

