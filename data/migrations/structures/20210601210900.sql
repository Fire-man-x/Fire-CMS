ALTER TABLE `users`
CHANGE COLUMN `role_id` `role_id` INT(11) UNSIGNED NOT NULL AFTER `email`,
ADD COLUMN `oauth_service` VARCHAR(20) NULL DEFAULT NULL AFTER `recovery_password_token`,
ADD COLUMN `oauth_id` VARCHAR(20) NULL DEFAULT NULL AFTER `oauth_service`;