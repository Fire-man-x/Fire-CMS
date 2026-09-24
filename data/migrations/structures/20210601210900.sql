ALTER TABLE `firecms_users`
CHANGE COLUMN `roleId` `roleId` INT(11) UNSIGNED NOT NULL AFTER `id`;
-- `oauthService` a `oauthId` jsou od 2026-09-24 přímo v CREATE TABLE firecms_users (structures/20161115000000.sql)
