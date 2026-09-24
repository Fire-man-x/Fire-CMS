-- Kategorie bez typů, které existovaly jen kvůli menu: `url`, `categoryLink`, `textBox`. Odkazy řeší položky
-- menu (firecms_menuItems.linkType, viz 20260923200000.sql). Zůstávají typy `site`, `homepage`, `gallery`.
--
-- 1) položky menu na tyto kategorie se převedou, 2) kategorie jdou do koše (obsah se neztratí),
-- 3) z výčtu typů se hodnoty odeberou.

-- popisek převáděné položky = název odkazové kategorie (vlastní popisek položky má přednost)
INSERT IGNORE INTO `firecms_menuItemDescriptions` (`menuItemId`, `languageId`, `label`)
SELECT `mi`.`id`, `d`.`languageId`, `d`.`title`
FROM `firecms_menuItems` `mi`
JOIN `firecms_categories` `c` ON `mi`.`linkType` = 'category' AND `mi`.`target` = CAST(`c`.`id` AS CHAR) AND `c`.`type` IN ('url', 'categoryLink')
JOIN `firecms_categoryDescriptions` `d` ON `d`.`categoryId` = `c`.`id`
WHERE `d`.`title` IS NOT NULL AND `d`.`title` != '';

-- categoryLink: id cílové kategorie ukládal formulář jako URL kategorie (firecms_urls), přednostně výchozí jazyk
UPDATE `firecms_menuItems` `mi`
JOIN `firecms_categories` `c` ON `mi`.`linkType` = 'category' AND `mi`.`target` = CAST(`c`.`id` AS CHAR) AND `c`.`type` = 'categoryLink'
SET `mi`.`target` = COALESCE((
	SELECT `u`.`url` FROM `firecms_urls` `u`
	WHERE `u`.`type` = 'category' AND `u`.`key` = `c`.`id` AND `u`.`url` REGEXP '^[0-9]+$'
	ORDER BY `u`.`languageId` = (SELECT `l`.`languageId` FROM `firecms_languages` `l` WHERE `l`.`default` = 1 LIMIT 1) DESC
	LIMIT 1
), `mi`.`target`);

-- url: adresa z URL kategorie (firecms_urls), přednostně výchozí jazyk; bez uložené adresy položka zůstane
-- odkazem na (vyřazenou) kategorii a v menu se nezobrazí
UPDATE `firecms_menuItems` `mi`
JOIN `firecms_categories` `c` ON `mi`.`linkType` = 'category' AND `mi`.`target` = CAST(`c`.`id` AS CHAR) AND `c`.`type` = 'url'
JOIN (
	SELECT `u`.`key`, `u`.`url` FROM `firecms_urls` `u`
	WHERE `u`.`type` = 'category'
		AND `u`.`id` = (
			SELECT `u2`.`id` FROM `firecms_urls` `u2`
			WHERE `u2`.`type` = 'category' AND `u2`.`key` = `u`.`key`
			ORDER BY `u2`.`languageId` = (SELECT `l`.`languageId` FROM `firecms_languages` `l` WHERE `l`.`default` = 1 LIMIT 1) DESC
			LIMIT 1
		)
) `categoryUrl` ON `categoryUrl`.`key` = `c`.`id`
SET `mi`.`linkType` = 'url', `mi`.`target` = `categoryUrl`.`url`;

-- textBox: text v menu nemá náhradu - položky se smažou (podpoložky přes FK parentId)
DELETE `mi` FROM `firecms_menuItems` `mi`
JOIN `firecms_categories` `c` ON `mi`.`linkType` = 'category' AND `mi`.`target` = CAST(`c`.`id` AS CHAR) AND `c`.`type` = 'textBox';

-- kategorie těchto typů do koše (aktuální verze; revize s historyId jen změní typ)
UPDATE `firecms_categories` SET `status` = 'trash' WHERE `type` IN ('url', 'categoryLink', 'textBox') AND `historyId` IS NULL;
UPDATE `firecms_categories` SET `type` = 'site' WHERE `type` IN ('url', 'categoryLink', 'textBox');

ALTER TABLE `firecms_categories`
	MODIFY `type` enum('homepage','site','gallery') NOT NULL DEFAULT 'site';
