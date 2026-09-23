DROP TABLE IF EXISTS `firecms_plugin_dynamicForms`;
CREATE TABLE `firecms_plugin_dynamicForms` (
  `id` int unsigned NOT null AUTO_INCREMENT,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `itemsSpecifications` text NOT null,
  `templateName` varchar(50) NOT null,
  `whereToSend` varchar(20) DEFAULT null,
  `afterSendInformations` varchar(200) DEFAULT null,
  `createdBy` int unsigned NOT null,
  PRIMARY KEY (`id`),
  UNIQUE KEY `templateName` (`templateName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_plugin_dynamicFormDescriptions`;
CREATE TABLE `firecms_plugin_dynamicFormDescriptions` (
  `dynamicFormId` int unsigned NOT null,
  `languageId` char(2) NOT null,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `title` varchar(50) NOT null,
  `items` text NOT null,
  `submitMessage` varchar(200) DEFAULT null,
  PRIMARY KEY (`dynamicFormId`,`languageId`),
  KEY `languageId` (`languageId`),
  CONSTRAINT `dynamicFormDescriptions_ibfk_3` FOREIGN KEY (`dynamicFormId`) REFERENCES `firecms_plugin_dynamicForms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dynamicFormDescriptions_ibfk_4` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_plugin_dynamicFormSendedValues`;
CREATE TABLE `firecms_plugin_dynamicFormSendedValues` (
  `id` int unsigned NOT null AUTO_INCREMENT,
  `dynamicFormId` int unsigned NOT null,
  `languageId` char(2) NOT null,
  `createDate` datetime NOT NULL DEFAULT current_timestamp(),
  `updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `sendedValues` text NOT null,
  PRIMARY KEY (`id`),
  KEY `dynamicFormId` (`dynamicFormId`),
  KEY `languageId` (`languageId`),
  KEY `dynamicFormIdLanguageId` (`dynamicFormId`,`languageId`),
  CONSTRAINT `dynamicFormSendedValues_ibfk_1` FOREIGN KEY (`dynamicFormId`) REFERENCES `firecms_plugin_dynamicForms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dynamicFormSendedValues_ibfk_3` FOREIGN KEY (`languageId`) REFERENCES `firecms_languages` (`languageId`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `firecms_modules` (`parentId`, `name`, `privilege`, `title`)
VALUES (null, 'DynamicForms', null, 'Dynamic forms');