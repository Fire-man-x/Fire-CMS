-- E-mail a telefon webu v obou tabulkách nastavení:
-- - `settings.mainEmail` = globální e-mail (dosud jen po jazycích), naplní se e-mailem výchozího jazyka.
-- - `settingDescriptions.contactPhone` = telefon pro jazyk (dosud jen globální), prázdný.
-- Prázdná hodnota jazyka = použije se globální, viz App\Model\Database\Settings::getAllForLanguage().

ALTER TABLE `firecms_settings`
	ADD `mainEmail` varchar(100) DEFAULT NULL AFTER `themePath`;

ALTER TABLE `firecms_settingDescriptions`
	ADD `contactPhone` varchar(50) DEFAULT NULL AFTER `mainEmail`;

UPDATE `firecms_settings` `s`
SET `s`.`mainEmail` = (
	SELECT `d`.`mainEmail`
	FROM `firecms_settingDescriptions` `d`
	INNER JOIN `firecms_languages` `l` ON `l`.`languageId` = `d`.`languageId`
	WHERE `d`.`settingId` = `s`.`id` AND `l`.`default` = 1
	LIMIT 1
);
