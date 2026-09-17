-- Domény přiřazené jazykovým mutacím (viz docs/Changelog/2026-09-17-language-domains.md).
-- Jeden jazyk může mít 0-N domén; `default` určuje, která doména se použije při generování
-- odchozích URL, pokud jich má jazyk přiřazeno víc.
CREATE TABLE `firecms_domains` (
  `domain_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `language_id` char(2) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `default` tinyint(1) NOT NULL DEFAULT 1,
  `position` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`domain_id`),
  UNIQUE KEY `domain` (`domain`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `domains_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `firecms_languages` (`language_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
