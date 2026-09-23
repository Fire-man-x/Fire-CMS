DROP TABLE IF EXISTS `firecms_plugin_sliders`;
CREATE TABLE `firecms_plugin_sliders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `name` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `duration` int(10) unsigned NOT NULL,
  `speed` int(10) unsigned NOT NULL,
  `navigation` tinyint(1) unsigned NOT NULL,
  `manual` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_plugin_sliderItems`;
CREATE TABLE `firecms_plugin_sliderItems` (
  `sliderId` int(11) unsigned NOT NULL,
  `languageId` char(2) NOT NULL,
  `fileId` int(11) unsigned NOT NULL,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `position` tinyint(1) unsigned NOT NULL,
  `url` varchar(256) NOT NULL DEFAULT '',
  `text` varchar(256) NOT NULL DEFAULT '',
  PRIMARY KEY (`sliderId`,`languageId`,`position`),
  KEY `languageId` (`languageId`),
  KEY `fileId` (`fileId`),
  KEY `sliderId` (`sliderId`),
  CONSTRAINT `sliderItems_ibfk_2` FOREIGN KEY (`sliderId`) REFERENCES `firecms_plugin_sliders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sliderItems_ibfk_5` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sliderItems_ibfk_6` FOREIGN KEY (`fileId`) REFERENCES `firecms_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `firecms_modules` (`parentId`, `name`, `privilege`, `title`)
VALUES (null, 'Sliders', null, 'Sliders');
