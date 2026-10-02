DROP TABLE IF EXISTS `firecms_plugin_stalkers`;
CREATE TABLE `firecms_plugin_stalkers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `createdBy` int(10) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ip` tinytext NOT NULL,
  `url` text NOT NULL,
  `data` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `createdBy` (`createdBy`),
  CONSTRAINT `stalkers_ibfk_1` FOREIGN KEY (`createdBy`) REFERENCES `firecms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
