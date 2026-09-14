ALTER TABLE `languages`
ADD INDEX `active_default` (`active`, `default`);

ALTER TABLE `modules`
ADD INDEX `name_privilege` (`name`, `privilege`);