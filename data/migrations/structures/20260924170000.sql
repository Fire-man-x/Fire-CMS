-- Obrázky položek menu ze správce souborů (např. ikona nebo náhled v rozbalovacím menu); hlavní obrázek =
-- první podle `position`. Smazáním položky (i podpoložky přes FK parentId) se vazby smažou.
CREATE TABLE `firecms_menuItemFiles` (
	`menuItemId` int(11) unsigned NOT NULL,
	`fileId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`position` int(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (`menuItemId`, `fileId`),
	KEY `fileId` (`fileId`),
	CONSTRAINT `menuItemFiles_ibfk_1` FOREIGN KEY (`menuItemId`) REFERENCES `firecms_menuItems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `menuItemFiles_ibfk_2` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
