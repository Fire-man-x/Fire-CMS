DROP TABLE IF EXISTS `firecms_plugin_dynamicForms`;
CREATE TABLE `firecms_plugin_dynamicForms` (
  `dynamic_form_id` int unsigned NOT null AUTO_INCREMENT,
  `items_specifications` text NOT null,
  `template_name` varchar(50) NOT null,
  `where_to_send` varchar(20) DEFAULT null,
  `after_send_informations` varchar(200) DEFAULT null,
  `grid_name` varchar(50) NOT null,
  `created_by` int unsigned NOT null,
  PRIMARY KEY (`dynamic_form_id`),
  UNIQUE KEY `template_name` (`template_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_plugin_dynamicFormDescriptions`;
CREATE TABLE `firecms_plugin_dynamicFormDescriptions` (
  `dynamic_form_id` int unsigned NOT null,
  `language_id` char(2) NOT null,
  `title` varchar(50) NOT null,
  `items` text NOT null,
  `submit_message` varchar(200) DEFAULT null,
  PRIMARY KEY (`dynamic_form_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `dynamic_form_descriptions_ibfk_3` FOREIGN KEY (`dynamic_form_id`) REFERENCES `firecms_plugin_dynamicForms` (`dynamic_form_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dynamic_form_descriptions_ibfk_4` FOREIGN KEY (`language_id`) REFERENCES `firecms_languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `firecms_plugin_dynamicFormSendedValues`;
CREATE TABLE `firecms_plugin_dynamicFormSendedValues` (
  `dynamic_form_sended_value_id` int unsigned NOT null AUTO_INCREMENT,
  `dynamic_form_id` int unsigned NOT null,
  `language_id` char(2) NOT null,
  `create_date` datetime NOT null,
  `sended_values` text NOT null,
  PRIMARY KEY (`dynamic_form_sended_value_id`),
  KEY `dynamic_form_id` (`dynamic_form_id`),
  KEY `language_id` (`language_id`),
  KEY `dynamic_form_id_language_id` (`dynamic_form_id`,`language_id`),
  CONSTRAINT `dynamic_form_sended_values_ibfk_1` FOREIGN KEY (`dynamic_form_id`) REFERENCES `firecms_plugin_dynamicForms` (`dynamic_form_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dynamic_form_sended_values_ibfk_3` FOREIGN KEY (`language_id`) REFERENCES `firecms_languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `firecms_modules` (`parent_id`, `name`, `privilege`, `title`)
VALUES (null, 'DynamicForms', null, 'Dynamic forms');