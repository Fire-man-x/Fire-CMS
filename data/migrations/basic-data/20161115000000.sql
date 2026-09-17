INSERT INTO `firecms_languages` (`language_id`, `active`, `default`, `position`, `name`, `shortcut`) VALUES
	('en',	1,	1,	1,	'Angličitina',	'EN'),
	('jn',	0,	0,	3,	'Jablka',	'JN');

INSERT INTO `firecms_modules` (`module_id`, `parent_id`, `name`, `privilege`, `title`) VALUES
	(1,	NULL,	'Settings',	NULL,	'Settings'),
	(2,	NULL,	'Users',	NULL,	'Users'),
	(3,	NULL,	'Categories',	NULL,	'Categories'),
	(4,	NULL,	'Articles',	NULL,	'Articles'),
	(5,	NULL,	'FilesManager',	NULL,	'Files manager'),
	(6,	NULL,	'Menus',	NULL,	'Menus'),
	(7,	NULL,	'Tags',	NULL,	'Tags'),
	(8,	4,	NULL,	'approve_article',	'Approve article'),
	(9,	2,	NULL,	'visit_tags',	'Tags'),
	(10,	NULL,	'Comments',	NULL,	'Comments'),
	(11,	NULL,	'Plugins',	NULL,	'Plugins');

INSERT INTO `firecms_options` (`key`, `value`) VALUES
	('image_resolution',	'1000x1000'),
	('main_description',	'Toto je popis webu'),
	('main_title',	'FIre CMS / FrontEnd - Testovací stránka'),
	('seo_description',	'Seo popis'),
	('seo_keywords',	'Seo klíčová slova'),
	('seo_title',	'');

INSERT INTO `firecms_roles` (`role_id`, `parent_id`, `default`, `position`, `name`, `title`) VALUES
	(1,	NULL,	1,	1,	'admin',	'Administrátor'),
	(2,	NULL,	1,	2,	'editor',	'Redaktor'),
	(3,	NULL,	1,	3,	'author',	'Editor'),
	(4,	NULL,	1,	4,	'subscriber',	'Návštěvník');


INSERT INTO `firecms_roleModule` (`role_id`, `module_id`, `privilege`) VALUES
	(2,	3,	'view'),
	(2,	4,	'view'),
	(2,	5,	'view'),
	(2,	8,	'approve_article'),
	(2,	9,	'visit_tags'),
	(3,	1,	'edit'),
	(3,	2,	'view'),
	(3,	3,	'add'),
	(4,	1,	'view'),
	(4,	2,	'view'),
	(4,	3,	'view'),
	(4,	4,	'view');


INSERT INTO `firecms_users` (`user_id`, `username`, `password`, `email`, `active`, `nickname`, `first_name`, `surname`, `role_id`, `recovery_password_time`, `recovery_password_token`) VALUES
	(1,	'admin',	'$2y$10$xJ4UKIKHweoiIikx9Fv2tuFc.bq7cQqKYdYnRnX.0Ca6IbdDhinTO',	'admin@firecms.test',	1,	NULL,	'Administrator',	'',	1,	NULL,	NULL),
	(2,	'editor',	'$2y$10$rQ/ab7dMFHmpf5u8SOmnyuMuRNgxJrZX2nmXDEEtiYp/AL8YuMZmq',	'editor@firecms.test',	1,	NULL,	'Editor',	'',	2,	NULL,	NULL),
	(3,	'author',	'$2y$10$9mYTRqXK15PZNqOt3Av.TOlB1xa9Wae7h23Phm9BN.xI8DVyJQmsm',	'author@firecms.test',	0,	NULL,	'',	'',	4,	NULL,	NULL),
	(4,	'subscriber1',	'$2y$10$mYWEI00EarBV25qDxs5zRuYxhk/RlNfhXMDySbMeXThql3JeqZXV2',	'subscriber1@firecms.test',	0,	NULL,	'',	'',	4,	NULL,	NULL),
	(5,	'subscriber2',	'$2y$10$sqj0wlr5WS1ss1PaEbTsQezx8ed/ct6T.l0a9gJFNu5Z0g.lpQ6AG',	'subscriber2@firecms.test',	0,	NULL,	'',	'',	4,	NULL,	NULL);

INSERT INTO `firecms_fileFolders` (`file_folder_id`, `parent_id`, `name`, `default`, `position`, `level`) VALUES
	(1,	NULL,	'Nezařazené',	1,	0,	0),
	(2,	NULL,	'Download',	0,	1,	0),
	(3,	NULL,	'Upload',	0,	2,	0),
	(4,	NULL,	'Obrázky',	0,	3,	0);