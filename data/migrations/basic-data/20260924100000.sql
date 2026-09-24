-- ACL modul pro stránky (#[Resource('Pages')] v App\AdminModule\Presenters\PagesPresenter).
-- Administrátor má přístup ke všemu, redaktor stránky upravuje stejně jako menu a kategorie článků.
INSERT INTO `firecms_modules` (`parentId`, `name`, `privilege`, `title`)
SELECT NULL, 'Pages', NULL, 'Pages'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `firecms_modules` WHERE `name` = 'Pages');

INSERT IGNORE INTO `firecms_roleModule` (`roleId`, `moduleId`, `privilege`)
SELECT `r`.`id`, `m`.`id`, 'edit'
FROM `firecms_roles` `r`
JOIN `firecms_modules` `m` ON `m`.`name` = 'Pages'
WHERE `r`.`name` = 'editor';
