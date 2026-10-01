INSERT INTO `firecms_fileFolders` (`id`, `parentId`, `name`, `default`, `position`, `level`) VALUES
	(1,	NULL,	'Nezařazené',	1,	0,	0),
	(2,	NULL,	'Download',	0,	1,	0),
	(3,	NULL,	'Upload',	0,	2,	0),
	(4,	NULL,	'Obrázky',	0,	3,	0);

INSERT INTO `firecms_languages` (`languageId`, `active`, `default`, `position`, `name`, `shortcut`) VALUES
	('cs',	1,	1,	2,	'Čeština',	'CS'),
	('en',	1,	0,	1,	'Angličitina',	'EN');

INSERT INTO `firecms_modules` (`id`, `parentId`, `name`, `privilege`, `title`) VALUES
	(1,	NULL,	'Settings',	NULL,	'Settings'),
	(2,	NULL,	'Users',	NULL,	'Users'),
	(3,	NULL,	'Roles',	NULL,	'Roles'),
	(4,	NULL,	'Categories',	NULL,	'Categories'),
	(5,	NULL,	'Articles',	NULL,	'Articles'),
	(6,	5,	NULL,	'approve_article',	'Approve article'),
	(7,	2,	NULL,	'visit_tags',	'Tags'),
	(8,	NULL,	'Plugins',	NULL,	'Plugins'),
	(9,	NULL,	'Domains',	NULL,	'Domains'),
	(10,	NULL,	'Languages',	NULL,	'Languages'),
	(11,	NULL,	'Metas',	NULL,	'Metas'),
	(12,	NULL,	'FilesManager',	NULL,	'Files manager'),
	(13,	NULL,	'Menus',	NULL,	'Menus'),
	(14,	NULL,	'Tags',	NULL,	'Tags'),
	(15,	NULL,	'Comments',	NULL,	'Comments'),
	(16,	NULL,	'DynamicForms',	NULL,	'Dynamic forms'),
	(17,	NULL,	'Sliders',	NULL,	'Sliders'),
	(18,	NULL,	'Pages',	NULL,	'Pages'),
	(19,	NULL,	'Sections',	NULL,	'Sections');

INSERT INTO `firecms_roleModule` (`roleId`, `moduleId`, `privilege`) VALUES
	(2,	4,	'edit'),
	(2,	5,	'edit'),
	(2,	6,	'approve_article'),
	(2,	7,	'visit_tags'),
	(2,	11,	'edit'),
	(2,	13,	'edit'),
	(2,	14,	'edit'),
	(2,	18,	'edit'),
	(2,	19,	'edit'),
	(3,	5,	'add'),
	(3,	7,	'visit_tags'),
	(3,	11,	'add'),
	(3,	14,	'add'),
	(4,	5,	'view');

INSERT INTO `firecms_roles` (`id`, `parentId`, `default`, `position`, `name`, `title`) VALUES
	(1,	NULL,	1,	1,	'admin',	'Administrátor'),
	(2,	NULL,	1,	2,	'editor',	'Redaktor'),
	(3,	NULL,	1,	3,	'author',	'Editor'),
	(4,	NULL,	1,	4,	'subscriber',	'Návštěvník');

INSERT INTO `firecms_settingDescriptions` (`settingId`, `languageId`, `mainTitle`, `mainDescription`, `mainEmail`, `contactAddress`, `seoTitle`, `seoDescription`, `seoKeywords`) VALUES
	(1,	'cs',	'Fire CMS',	NULL,	'info@example.com',	NULL,	NULL,	NULL,	NULL),
	(1,	'en',	'Fire CMS',	NULL,	'info@example.com',	NULL,	NULL,	NULL,	NULL);

INSERT INTO `firecms_settings` (`id`, `imageResolution`, `themePath`, `contactPhone`, `mapLatitude`, `mapLongitude`) VALUES
	(1,	'1000x1000',	'default',	NULL,	NULL,	NULL);

INSERT INTO `firecms_users` (`id`, `roleId`, `username`, `password`, `email`, `active`, `nickname`, `firstName`, `surname`, `recoveryPasswordTime`, `recoveryPasswordToken`, `oauthService`, `oauthId`) VALUES
	(1,	1,	'admin',	'$2y$10$xJ4UKIKHweoiIikx9Fv2tuFc.bq7cQqKYdYnRnX.0Ca6IbdDhinTO',	'admin@firecms.test',	1,	NULL,	'Administrator',	'',	NULL,	NULL,	NULL,	NULL),
	(2,	2,	'editor',	'$2y$10$rQ/ab7dMFHmpf5u8SOmnyuMuRNgxJrZX2nmXDEEtiYp/AL8YuMZmq',	'editor@firecms.test',	1,	NULL,	'Editor',	'',	NULL,	NULL,	NULL,	NULL),
	(3,	4,	'author',	'$2y$10$9mYTRqXK15PZNqOt3Av.TOlB1xa9Wae7h23Phm9BN.xI8DVyJQmsm',	'author@firecms.test',	0,	NULL,	'',	'',	NULL,	NULL,	NULL,	NULL),
	(4,	4,	'subscriber1',	'$2y$10$mYWEI00EarBV25qDxs5zRuYxhk/RlNfhXMDySbMeXThql3JeqZXV2',	'subscriber1@firecms.test',	0,	NULL,	'',	'',	NULL,	NULL,	NULL,	NULL),
	(5,	4,	'subscriber2',	'$2y$10$sqj0wlr5WS1ss1PaEbTsQezx8ed/ct6T.l0a9gJFNu5Z0g.lpQ6AG',	'subscriber2@firecms.test',	0,	NULL,	'',	'',	NULL,	NULL,	NULL,	NULL);
