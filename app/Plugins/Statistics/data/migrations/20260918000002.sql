DROP TABLE IF EXISTS `firecms_plugin_statistics`;
CREATE TABLE `firecms_plugin_statistics` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `session` varchar(32) NOT NULL,
  `ip` tinytext NOT NULL,
  `agent` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
