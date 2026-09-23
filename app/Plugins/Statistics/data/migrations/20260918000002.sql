CREATE TABLE `firecms_plugin_statistics` (
	`id` int(11) unsigned NOT null AUTO_INCREMENT,
	`createDate` timestamp NOT null DEFAULT CURRENT_TIMESTAMP,
	`session` int(10) unsigned NOT null,
	`agent` text NOT null,
	`hits` int NOT null,
	PRIMARY KEY (`id`),
	KEY `createdBy` (`createdBy`),
	CONSTRAINT `statistics_ibfk_1` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;