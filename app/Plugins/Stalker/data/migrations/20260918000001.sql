CREATE TABLE `firecms_plugin_stalkers` (
  `id` int(11) unsigned NOT null AUTO_INCREMENT,
  `createDate` timestamp NOT null DEFAULT CURRENT_TIMESTAMP,
  `createdBy` int(10) unsigned NOT null,
  `url` text NOT null,
  PRIMARY KEY (`id`),
  KEY `createdBy` (`createdBy`),
  CONSTRAINT `stalkers_ibfk_1` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;