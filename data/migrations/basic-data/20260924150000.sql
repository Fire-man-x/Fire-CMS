-- Výchozí sekce "Články" (id 1) - čerstvá instalace má kam ukládat články a kategorie i bez ukázkových dat.
-- ACL modul pro správu sekcí (App\AdminModule\Presenters\SectionsPresenter), redaktor sekce jen upravuje.
INSERT INTO `firecms_sections` (`id`, `active`, `position`) VALUES (1, 1, 1);

INSERT INTO `firecms_sectionDescriptions` (`sectionId`, `languageId`, `title`)
SELECT 1, `languageId`, IF(`languageId` = 'cs', 'Články', 'Articles') FROM `firecms_languages` WHERE `languageId` IN ('cs', 'en');

INSERT INTO `firecms_urls` (`languageId`, `type`, `key`, `url`)
SELECT `languageId`, 'section', 1, IF(`languageId` = 'cs', 'clanky', 'articles') FROM `firecms_languages` WHERE `languageId` IN ('cs', 'en');

INSERT INTO `firecms_modules` (`parentId`, `name`, `privilege`, `title`)
SELECT NULL, 'Sections', NULL, 'Sections' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `firecms_modules` WHERE `name` = 'Sections');

INSERT IGNORE INTO `firecms_roleModule` (`roleId`, `moduleId`, `privilege`)
SELECT `r`.`id`, `m`.`id`, 'edit'
FROM `firecms_roles` `r`
JOIN `firecms_modules` `m` ON `m`.`name` = 'Sections'
WHERE `r`.`name` = 'editor';
