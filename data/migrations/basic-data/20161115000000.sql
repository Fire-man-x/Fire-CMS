INSERT INTO `firecms_languages` (`languageId`, `active`, `default`, `position`, `name`, `shortcut`) VALUES
	('en',	1,	1,	1,	'Angličitina',	'EN'),
	('jn',	0,	0,	3,	'Jablka',	'JN');

INSERT INTO `firecms_options` (`key`, `value`) VALUES
	('image_resolution',	'1000x1000'),
	('main_description',	'Toto je popis webu'),
	('main_title',	'FIre CMS / FrontEnd - Testovací stránka'),
	('seo_description',	'Seo popis'),
	('seo_keywords',	'Seo klíčová slova'),
	('seo_title',	''),
	('main_email',	'info@example.com'),
	('themePath',	'sdh');

INSERT INTO `firecms_modules` (`parentId`, `name`, `privilege`, `title`) VALUES
	(NULL,	'Settings',	NULL,	'Settings'),
	(NULL,	'Users',	NULL,	'Users'),
	(NULL,	'Roles',	NULL,	'Roles'),
	(NULL,	'Categories',	NULL,	'Categories'),
	(NULL,	'Articles',	NULL,	'Articles'),
	(5,	NULL,	'approve_article',	'Approve article'),
	(2,	NULL,	'visit_tags',	'Tags'),
	(NULL,	'Plugins',	NULL,	'Plugins'),
	(NULL,	'Domains',	NULL,	'Domains'),
	(NULL,	'Languages',	NULL,	'Languages'),
	(NULL,	'Metas',	NULL,	'Metas'),
	(NULL,	'FilesManager',	NULL,	'Files manager'),
	(NULL,	'Menus',	NULL,	'Menus'),
	(NULL,	'Tags',	NULL,	'Tags'),
	(NULL,	'Comments',	NULL,	'Comments');

INSERT INTO `firecms_roles` (`id`, `parentId`, `default`, `position`, `name`, `title`) VALUES
	(1,	NULL,	1,	1,	'admin',	'Administrátor'),
	(2,	NULL,	1,	2,	'editor',	'Redaktor'),
	(3,	NULL,	1,	3,	'author',	'Editor'),
	(4,	NULL,	1,	4,	'subscriber',	'Návštěvník');


INSERT INTO `firecms_roleModule` (`roleId`, `moduleId`, `privilege`) VALUES
	(2,	4,	'edit'),
	(2,	5,	'edit'),
	(2,	6,	'approve_article'),
	(2,	7,	'visit_tags'),
	(2,	11,	'edit'),
	(2,	13,	'edit'),
	(2,	14,	'edit'),
	(3,	5,	'add'),
	(3,	7,	'visit_tags'),
	(3,	11,	'add'),
	(3,	14,	'add'),
	(4,	5,	'view');


INSERT INTO `firecms_users` (`id`, `username`, `password`, `email`, `active`, `nickname`, `firstName`, `surname`, `roleId`, `recoveryPasswordTime`, `recoveryPasswordToken`) VALUES
	(1,	'admin',	'$2y$10$xJ4UKIKHweoiIikx9Fv2tuFc.bq7cQqKYdYnRnX.0Ca6IbdDhinTO',	'admin@firecms.test',	1,	NULL,	'Administrator',	'',	1,	NULL,	NULL),
	(2,	'editor',	'$2y$10$rQ/ab7dMFHmpf5u8SOmnyuMuRNgxJrZX2nmXDEEtiYp/AL8YuMZmq',	'editor@firecms.test',	1,	NULL,	'Editor',	'',	2,	NULL,	NULL),
	(3,	'author',	'$2y$10$9mYTRqXK15PZNqOt3Av.TOlB1xa9Wae7h23Phm9BN.xI8DVyJQmsm',	'author@firecms.test',	0,	NULL,	'',	'',	4,	NULL,	NULL),
	(4,	'subscriber1',	'$2y$10$mYWEI00EarBV25qDxs5zRuYxhk/RlNfhXMDySbMeXThql3JeqZXV2',	'subscriber1@firecms.test',	0,	NULL,	'',	'',	4,	NULL,	NULL),
	(5,	'subscriber2',	'$2y$10$sqj0wlr5WS1ss1PaEbTsQezx8ed/ct6T.l0a9gJFNu5Z0g.lpQ6AG',	'subscriber2@firecms.test',	0,	NULL,	'',	'',	4,	NULL,	NULL);

INSERT INTO `firecms_fileFolders` (`id`, `parentId`, `name`, `default`, `position`, `level`) VALUES
	(1,	NULL,	'Nezařazené',	1,	0,	0),
	(2,	NULL,	'Download',	0,	1,	0),
	(3,	NULL,	'Upload',	0,	2,	0),
	(4,	NULL,	'Obrázky',	0,	3,	0);