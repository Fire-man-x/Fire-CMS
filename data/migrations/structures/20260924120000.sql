-- Stránky: vnořování (parentId + pořadí mezi sourozenci) a obrázky.
-- URL zůstávají ploché (jeden slug, viz UrlManager) - hierarchie se projeví v drobečkové navigaci,
-- výpisu podstránek a stromu v administraci. Smazání rodiče přesune podstránky o úroveň výš
-- (App\Model\Pages::delete(), FK je jen pojistka SET NULL).
ALTER TABLE `firecms_pages`
	ADD COLUMN `parentId` int(11) unsigned DEFAULT NULL AFTER `id`,
	ADD COLUMN `position` int(11) NOT NULL DEFAULT 0 AFTER `public`,
	ADD KEY `parentId` (`parentId`),
	ADD CONSTRAINT `pages_ibfk_2` FOREIGN KEY (`parentId`) REFERENCES `firecms_pages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- obrázky stránky ze správce souborů; hlavní obrázek = první podle `position`
CREATE TABLE `firecms_pageFiles` (
	`pageId` int(11) unsigned NOT NULL,
	`fileId` int(11) unsigned NOT NULL,
	`createDate` datetime NOT NULL DEFAULT current_timestamp(),
	`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
	`position` int(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (`pageId`, `fileId`),
	KEY `fileId` (`fileId`),
	CONSTRAINT `pageFiles_ibfk_1` FOREIGN KEY (`pageId`) REFERENCES `firecms_pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `pageFiles_ibfk_2` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
