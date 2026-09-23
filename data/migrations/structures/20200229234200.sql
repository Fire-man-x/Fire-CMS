ALTER TABLE `firecms_languages`
ADD INDEX `activeDefault` (`active`, `default`);

ALTER TABLE `firecms_modules`
ADD INDEX `namePrivilege` (`name`, `privilege`);