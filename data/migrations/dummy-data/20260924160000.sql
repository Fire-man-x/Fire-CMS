-- Ukázková data pro vývoj (skupina dummy-data, zapíná se parametrem dummyData v config.local.neon):
-- sekce Články (id 1 z basic-data) a Novinky, kategorie článků, články, stránky se stromem, hlavní menu
-- a patička. Jazyky cs (výchozí) + en, autor admin (id 1).

-- ---------------------------------------------------------------- sekce
UPDATE `firecms_sectionDescriptions` SET `content` = '<p>Tipy, rady a recenze z naší redakce.</p>' WHERE `sectionId` = 1 AND `languageId` = 'cs';
UPDATE `firecms_sectionDescriptions` SET `content` = '<p>Tips, advice and reviews from our editors.</p>' WHERE `sectionId` = 1 AND `languageId` = 'en';

INSERT INTO `firecms_sections` (`id`, `active`, `position`) VALUES (2, 1, 2);
INSERT INTO `firecms_sectionDescriptions` (`sectionId`, `languageId`, `title`, `content`, `seoTitle`, `seoDescription`) VALUES
	(2, 'cs', 'Novinky', '<p>Aktuality a pozvánky na akce.</p>', 'Novinky', 'Aktuality a akce'),
	(2, 'en', 'News', '<p>Latest news and upcoming events.</p>', 'News', 'News and events');
INSERT INTO `firecms_urls` (`languageId`, `type`, `key`, `url`) VALUES
	('cs', 'section', 2, 'novinky'),
	('en', 'section', 2, 'news');

-- ---------------------------------------------------------------- kategorie článků (vnořené množiny left/right)
INSERT INTO `firecms_categories` (`id`, `sectionId`, `parentId`, `createdBy`, `publishingDate`, `active`, `status`, `position`, `level`, `categoryLeft`, `categoryRight`, `showInMenu`, `public`) VALUES
	(1, 1, NULL, 1, NOW(), 1, 'publish', 1, 0, 1, 4, 1, 1),
	(2, 1, 1, 1, NOW(), 1, 'publish', 1, 1, 2, 3, 1, 1),
	(3, 1, NULL, 1, NOW(), 1, 'publish', 2, 0, 5, 6, 1, 1),
	(4, 2, NULL, 1, NOW(), 1, 'publish', 1, 0, 7, 8, 1, 1),
	(5, 2, NULL, 1, NOW(), 1, 'publish', 2, 0, 9, 10, 1, 1);

INSERT INTO `firecms_categoryDescriptions` (`categoryId`, `languageId`, `title`, `excerpt`, `content`, `seoTitle`, `seoDescription`) VALUES
	(1, 'cs', 'Tipy a rady', '<p>Praktické tipy pro každý den.</p>', '<p>Vybrané rady, které se hodí.</p>', 'Tipy a rady', 'Praktické tipy'),
	(1, 'en', 'Tips and advice', '<p>Practical everyday tips.</p>', '<p>Selected advice worth knowing.</p>', 'Tips and advice', 'Practical tips'),
	(2, 'cs', 'Péče o psa', '<p>Výživa, pohyb a zdraví.</p>', '<p>Jak se o psa starat správně.</p>', 'Péče o psa', 'Výživa, pohyb a zdraví'),
	(2, 'en', 'Dog care', '<p>Nutrition, exercise and health.</p>', '<p>How to take good care of your dog.</p>', 'Dog care', 'Nutrition, exercise and health'),
	(3, 'cs', 'Recenze', '<p>Vyzkoušeli jsme za vás.</p>', '<p>Poctivé recenze produktů.</p>', 'Recenze', 'Recenze produktů'),
	(3, 'en', 'Reviews', '<p>We tried it for you.</p>', '<p>Honest product reviews.</p>', 'Reviews', 'Product reviews'),
	(4, 'cs', 'Aktuality', '<p>Co je u nás nového.</p>', NULL, 'Aktuality', 'Novinky z provozu'),
	(4, 'en', 'Updates', '<p>What is new.</p>', NULL, 'Updates', 'Latest updates'),
	(5, 'cs', 'Akce', '<p>Pozvánky na akce.</p>', NULL, 'Akce', 'Pozvánky na akce'),
	(5, 'en', 'Events', '<p>Upcoming events.</p>', NULL, 'Events', 'Upcoming events');

INSERT INTO `firecms_urls` (`languageId`, `type`, `key`, `url`) VALUES
	('cs', 'category', 1, 'tipy-a-rady'), ('en', 'category', 1, 'tips-and-advice'),
	('cs', 'category', 2, 'pece-o-psa'), ('en', 'category', 2, 'dog-care'),
	('cs', 'category', 3, 'recenze'), ('en', 'category', 3, 'reviews'),
	('cs', 'category', 4, 'aktuality'), ('en', 'category', 4, 'updates'),
	('cs', 'category', 5, 'akce'), ('en', 'category', 5, 'events');

-- ---------------------------------------------------------------- články
INSERT INTO `firecms_articles` (`id`, `sectionId`, `createdBy`, `publishingDate`, `active`, `status`, `public`) VALUES
	(1, 1, 1, NOW() - INTERVAL 10 DAY, 1, 'publish', 1),
	(2, 1, 1, NOW() - INTERVAL 7 DAY, 1, 'publish', 1),
	(3, 1, 1, NOW() - INTERVAL 3 DAY, 1, 'publish', 1),
	(4, 1, 1, NOW(), 1, 'draft', 1),
	(5, 2, 1, NOW() - INTERVAL 5 DAY, 1, 'publish', 1),
	(6, 2, 1, NOW() - INTERVAL 2 DAY, 1, 'publish', 1),
	(7, 2, 1, NOW() - INTERVAL 1 DAY, 1, 'publish', 1);

INSERT INTO `firecms_articleDescriptions` (`articleId`, `languageId`, `title`, `excerpt`, `content`, `seoTitle`, `seoDescription`) VALUES
	(1, 'cs', 'Jak vybrat správné krmivo', '<p>Na co se zaměřit při výběru krmiva.</p>', '<p>Složení, věk psa a jeho aktivita rozhodují o tom, jaké krmivo je vhodné.</p>', 'Jak vybrat krmivo', 'Rady k výběru krmiva'),
	(1, 'en', 'How to choose the right food', '<p>What to look for when choosing food.</p>', '<p>Ingredients, age and activity decide which food fits your dog.</p>', 'Choosing dog food', 'Advice on choosing food'),
	(2, 'cs', 'Procházky v zimě', '<p>Jak chránit tlapky v mrazu.</p>', '<p>Krátké a častější procházky, ochranný krém na tlapky a teplá deka doma.</p>', 'Procházky v zimě', 'Zimní procházky se psem'),
	(2, 'en', 'Winter walks', '<p>Protecting paws in frost.</p>', '<p>Shorter and more frequent walks, paw balm and a warm blanket at home.</p>', 'Winter walks', 'Winter walks with a dog'),
	(3, 'cs', 'Recenze: pelíšek Comfort', '<p>Vyzkoušeli jsme nový pelíšek.</p>', '<p>Pevný, pratelný a psi ho milují. Hodnocení 4/5.</p>', 'Recenze pelíšku Comfort', 'Test pelíšku'),
	(3, 'en', 'Review: Comfort dog bed', '<p>We tested a new dog bed.</p>', '<p>Sturdy, washable and dogs love it. Rating 4/5.</p>', 'Comfort dog bed review', 'Dog bed test'),
	(4, 'cs', 'Rozpracovaný článek', '<p>Koncept - na webu se nezobrazí.</p>', '<p>Text v přípravě.</p>', NULL, NULL),
	(5, 'cs', 'Otevřeli jsme novou pobočku', '<p>Nová pobočka v Brně.</p>', '<p>Od pondělí nás najdete i v Brně.</p>', 'Nová pobočka', 'Pobočka Brno'),
	(5, 'en', 'We opened a new branch', '<p>New branch in Brno.</p>', '<p>From Monday you can find us in Brno too.</p>', 'New branch', 'Brno branch'),
	(6, 'cs', 'Den otevřených dveří', '<p>Přijďte se podívat.</p>', '<p>V sobotu od 10 do 16 hodin, občerstvení zajištěno.</p>', 'Den otevřených dveří', 'Pozvánka'),
	(6, 'en', 'Open day', '<p>Come and see us.</p>', '<p>Saturday 10 am to 4 pm, refreshments provided.</p>', 'Open day', 'Invitation'),
	(7, 'cs', 'Nové otevírací hodiny', '<p>Změna od příštího měsíce.</p>', '<p>Nově otevřeno každý den 7-19 hodin.</p>', 'Otevírací hodiny', 'Změna otevírací doby'),
	(7, 'en', 'New opening hours', '<p>Changes from next month.</p>', '<p>Open daily 7 am to 7 pm.</p>', 'Opening hours', 'Opening hours change');

INSERT INTO `firecms_categoryArticle` (`categoryId`, `articleId`, `isMain`) VALUES
	(2, 1, 1), (1, 1, 0),
	(2, 2, 1),
	(3, 3, 1),
	(1, 4, 1),
	(4, 5, 1),
	(5, 6, 1),
	(4, 7, 1);

INSERT INTO `firecms_urls` (`languageId`, `type`, `key`, `url`) VALUES
	('cs', 'article', 1, 'jak-vybrat-spravne-krmivo'), ('en', 'article', 1, 'how-to-choose-the-right-food'),
	('cs', 'article', 2, 'prochazky-v-zime'), ('en', 'article', 2, 'winter-walks'),
	('cs', 'article', 3, 'recenze-pelisek-comfort'), ('en', 'article', 3, 'review-comfort-dog-bed'),
	('cs', 'article', 4, 'rozpracovany-clanek'),
	('cs', 'article', 5, 'otevreli-jsme-novou-pobocku'), ('en', 'article', 5, 'we-opened-a-new-branch'),
	('cs', 'article', 6, 'den-otevrenych-dveri'), ('en', 'article', 6, 'open-day'),
	('cs', 'article', 7, 'nove-oteviraci-hodiny'), ('en', 'article', 7, 'new-opening-hours');

-- ---------------------------------------------------------------- stránky (O nás > Náš tým, Kontakt, Obchodní podmínky, koncept)
INSERT INTO `firecms_pages` (`id`, `parentId`, `createdBy`, `active`, `status`, `public`, `position`) VALUES
	(1, NULL, 1, 1, 'publish', 1, 1),
	(2, 1, 1, 1, 'publish', 1, 1),
	(3, NULL, 1, 1, 'publish', 1, 2),
	(4, NULL, 1, 1, 'publish', 1, 3),
	(5, NULL, 1, 1, 'draft', 1, 4);

INSERT INTO `firecms_pageDescriptions` (`pageId`, `languageId`, `title`, `content`, `seoTitle`, `seoDescription`) VALUES
	(1, 'cs', 'O nás', '<p>Jsme malý tým nadšenců, který se o vaše mazlíčky postará jako o vlastní.</p>', 'O nás', 'Kdo jsme a co děláme'),
	(1, 'en', 'About us', '<p>We are a small team of enthusiasts who care for your pets like our own.</p>', 'About us', 'Who we are'),
	(2, 'cs', 'Náš tým', '<p>Seznamte se s lidmi, kteří se o vše starají.</p>', 'Náš tým', 'Lidé za naší prací'),
	(2, 'en', 'Our team', '<p>Meet the people behind our work.</p>', 'Our team', 'People behind our work'),
	(3, 'cs', 'Kontakt', '<p>E-mail: info@example.com<br>Telefon: +420 123 456 789</p>', 'Kontakt', 'Jak nás kontaktovat'),
	(3, 'en', 'Contact', '<p>E-mail: info@example.com<br>Phone: +420 123 456 789</p>', 'Contact', 'How to reach us'),
	(4, 'cs', 'Obchodní podmínky', '<p>Ukázkové obchodní podmínky.</p>', 'Obchodní podmínky', NULL),
	(4, 'en', 'Terms and conditions', '<p>Sample terms and conditions.</p>', 'Terms and conditions', NULL),
	(5, 'cs', 'Připravovaná stránka', '<p>Koncept - na webu se nezobrazí.</p>', NULL, NULL);

INSERT INTO `firecms_urls` (`languageId`, `type`, `key`, `url`) VALUES
	('cs', 'page', 1, 'o-nas'), ('en', 'page', 1, 'about-us'),
	('cs', 'page', 2, 'nas-tym'), ('en', 'page', 2, 'our-team'),
	('cs', 'page', 3, 'kontakt'), ('en', 'page', 3, 'contact'),
	('cs', 'page', 4, 'obchodni-podminky'), ('en', 'page', 4, 'terms-and-conditions'),
	('cs', 'page', 5, 'pripravovana-stranka');

-- ---------------------------------------------------------------- menu (main-menu = homepage Pelíšku, footer-menu = patička)
INSERT INTO `firecms_menus` (`id`, `active`, `location`, `createdBy`) VALUES
	(1, 1, 'main-menu', 1),
	(2, 1, 'footer-menu', 1);

INSERT INTO `firecms_menuDescriptions` (`menuId`, `languageId`, `title`) VALUES
	(1, 'cs', 'Hlavní menu'), (1, 'en', 'Main menu'),
	(2, 'cs', 'Informace'), (2, 'en', 'Information');

INSERT INTO `firecms_menuItems` (`id`, `menuId`, `parentId`, `active`, `position`, `linkType`, `target`, `newWindow`) VALUES
	(1, 1, NULL, 1, 1, 'route', ':Front:Homepage:default', 0),
	(2, 1, NULL, 1, 2, 'section', '1', 0),
	(3, 1, 2, 1, 1, 'category', '1', 0),
	(4, 1, 2, 1, 2, 'category', '3', 0),
	(5, 1, NULL, 1, 3, 'section', '2', 0),
	(6, 1, NULL, 1, 4, 'page', '1', 0),
	(7, 1, 6, 1, 1, 'page', '2', 0),
	(8, 1, NULL, 1, 5, 'page', '3', 0),
	(9, 2, NULL, 1, 1, 'page', '4', 0),
	(10, 2, NULL, 1, 2, 'page', '3', 0),
	(11, 2, NULL, 1, 3, 'article', '1', 0),
	(12, 2, NULL, 1, 4, 'url', 'https://nette.org', 1);

INSERT INTO `firecms_menuItemDescriptions` (`menuItemId`, `languageId`, `label`) VALUES
	(1, 'cs', 'Domů'), (1, 'en', 'Home'),
	(11, 'cs', 'Rada měsíce'), (11, 'en', 'Tip of the month'),
	(12, 'cs', 'Postaveno na Nette');
