ALTER TABLE `firecms_languages`
ADD INDEX `active_default` (`active`, `default`);

ALTER TABLE `firecms_modules`
ADD INDEX `name_privilege` (`name`, `privilege`);