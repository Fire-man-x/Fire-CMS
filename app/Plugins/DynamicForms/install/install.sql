
SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
DROP TABLE IF EXISTS `dynamic_forms`;
CREATE TABLE `dynamic_forms` (
  `dynamic_form_id` int unsigned NOT null AUTO_INCREMENT,
  `items_specifications` text NOT null,
  `template_name` varchar(50) NOT null,
  `where_to_send` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT null,
  `after_send_informations` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT null,
  `grid_name` varchar(50) NOT null,
  `created_by` int unsigned NOT null,
  PRIMARY KEY (`dynamic_form_id`),
  UNIQUE KEY `template_name` (`template_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `dynamic_form_descriptions`;
CREATE TABLE `dynamic_form_descriptions` (
  `dynamic_form_id` int unsigned NOT null,
  `language_id` char(2) NOT null,
  `title` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT null,
  `items` text NOT null,
  `submit_message` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT null,
  PRIMARY KEY (`dynamic_form_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `dynamic_form_descriptions_ibfk_3` FOREIGN KEY (`dynamic_form_id`) REFERENCES `dynamic_forms` (`dynamic_form_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dynamic_form_descriptions_ibfk_4` FOREIGN KEY (`language_id`) REFERENCES `languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `dynamic_form_sended_values`;
CREATE TABLE `dynamic_form_sended_values` (
  `dynamic_form_sended_value_id` int unsigned NOT null AUTO_INCREMENT,
  `dynamic_form_id` int unsigned NOT null,
  `language_id` char(2) NOT null,
  `create_date` datetime NOT null,
  `sended_values` text NOT null,
  PRIMARY KEY (`dynamic_form_sended_value_id`),
  KEY `dynamic_form_id` (`dynamic_form_id`),
  KEY `language_id` (`language_id`),
  KEY `dynamic_form_id_language_id` (`dynamic_form_id`,`language_id`),
  CONSTRAINT `dynamic_form_sended_values_ibfk_1` FOREIGN KEY (`dynamic_form_id`) REFERENCES `dynamic_forms` (`dynamic_form_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dynamic_form_sended_values_ibfk_3` FOREIGN KEY (`language_id`) REFERENCES `languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `modules` (`parent_id`, `name`, `privilege`, `title`)
VALUES (null, 'DynamicForms', null, 'Dynamic forms');