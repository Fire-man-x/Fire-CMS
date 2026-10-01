-- Úložiště sessions pro App\Session\DatabaseSessionHandler\MysqlSessionHandler (theme.neon: sessionHandler: driver: mysql).
-- Tabulka vzniká v každém projektu, bez nastaveného handleru zůstane prázdná. Pro sessions v jiné databázi
-- (sessionHandler: connection:) ji vytvořte tam stejným SQL. PostgreSQL viz docs/Architecture/sessions.md.
CREATE TABLE `system_sessions` (
	`id` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 hash ID session',
	`data` mediumtext CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'data session v base64',
	`expiresAt` int(10) unsigned NOT NULL COMMENT 'platnost do (unix timestamp)',
	PRIMARY KEY (`id`),
	KEY `expiresAt` (`expiresAt`)
) ENGINE=InnoDB;
