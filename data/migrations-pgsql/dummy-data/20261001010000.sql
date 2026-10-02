-- PostgreSQL varianta data/migrations/dummy-data/20261001010000.sql (stejná data).
-- Vkládá se v abecedním pořadí tabulek, cizí klíče se kontrolují až na konci transakce migrace.
SET CONSTRAINTS ALL DEFERRED;

INSERT INTO "firecms_articleComments" ("articleId", "commentId") VALUES
	(3, 1),
	(3, 2),
	(3, 3);

INSERT INTO "firecms_articleDescriptions" ("articleId", "languageId", "title", "excerpt", "content", "seoTitle", "seoDescription", "seoKeywords", "viewCount") VALUES
	(1, 'cs', 'Jak vybrat správnou obuv', '<p>Na co se zaměřit při výběru turistické obuvi.</p>', '<p>Terén, délka túry a roční období rozhodují o tom, jaká obuv je vhodná.</p>', 'Jak vybrat obuv', 'Rady k výběru obuvi', NULL, 0),
	(1, 'en', 'How to choose the right shoes', '<p>What to look for when choosing hiking shoes.</p>', '<p>Terrain, trip length and season decide which shoes fit best.</p>', 'Choosing hiking shoes', 'Advice on choosing shoes', NULL, 0),
	(2, 'cs', 'Procházky v zimě', '<p>Jak se obléknout do mrazu.</p>', '<p>Vrstvené oblečení, pevná obuv a termoska s teplým čajem.</p>', 'Procházky v zimě', 'Zimní procházky', NULL, 0),
	(2, 'en', 'Winter walks', '<p>How to dress for frost.</p>', '<p>Layered clothing, sturdy shoes and a flask of hot tea.</p>', 'Winter walks', 'Winter walks', NULL, 0),
	(3, 'cs', 'Recenze: batoh Comfort', '<p>Vyzkoušeli jsme nový batoh.</p>', '<p>Pevný, lehký a pohodlný i na celodenní túru. Hodnocení 4/5.</p>', 'Recenze batohu Comfort', 'Test batohu', NULL, 0),
	(3, 'en', 'Review: Comfort backpack', '<p>We tested a new backpack.</p>', '<p>Sturdy, light and comfortable even on a full-day hike. Rating 4/5.</p>', 'Comfort backpack review', 'Backpack test', NULL, 0),
	(4, 'cs', 'Rozpracovaný článek', '<p>Koncept - na webu se nezobrazí.</p>', '<p>Text v přípravě.</p>', NULL, NULL, NULL, 0),
	(5, 'cs', 'Otevřeli jsme novou pobočku', '<p>Nová pobočka v Brně.</p>', '<p>Od pondělí nás najdete i v Brně.</p>', 'Nová pobočka', 'Pobočka Brno', NULL, 0),
	(5, 'en', 'We opened a new branch', '<p>New branch in Brno.</p>', '<p>From Monday you can find us in Brno too.</p>', 'New branch', 'Brno branch', NULL, 0),
	(6, 'cs', 'Den otevřených dveří', '<p>Přijďte se podívat.</p>', '<p>V sobotu od 10 do 16 hodin, občerstvení zajištěno.</p>', 'Den otevřených dveří', 'Pozvánka', NULL, 0),
	(6, 'en', 'Open day', '<p>Come and see us.</p>', '<p>Saturday 10 am to 4 pm, refreshments provided.</p>', 'Open day', 'Invitation', NULL, 0),
	(7, 'cs', 'Nové otevírací hodiny', '<p>Změna od příštího měsíce.</p>', '<p>Nově otevřeno každý den 7-19 hodin.</p>', 'Otevírací hodiny', 'Změna otevírací doby', NULL, 0),
	(7, 'en', 'New opening hours', '<p>Changes from next month.</p>', '<p>Open daily 7 am to 7 pm.</p>', 'Opening hours', 'Opening hours change', NULL, 0);

INSERT INTO "firecms_articles" ("id", "sectionId", "historyId", "createdBy", "publishingDate", "expiringDate", "active", "status", "public") VALUES
	(1, 1, NULL, 1, '2026-09-21 18:52:15', NULL, true, 'publish', true),
	(2, 1, NULL, 1, '2026-09-24 18:52:15', NULL, true, 'publish', true),
	(3, 1, NULL, 1, '2026-09-28 18:52:15', NULL, true, 'publish', true),
	(4, 1, NULL, 1, '2026-10-01 18:52:15', NULL, true, 'draft', true),
	(5, 2, NULL, 1, '2026-09-26 18:52:15', NULL, true, 'publish', true),
	(6, 2, NULL, 1, '2026-09-29 18:52:15', NULL, true, 'publish', true),
	(7, 2, NULL, 1, '2026-09-30 18:52:15', NULL, true, 'publish', true);

INSERT INTO "firecms_articleTags" ("articleId", "tagId") VALUES
	(1, 1),
	(2, 1),
	(2, 2),
	(3, 1),
	(3, 3),
	(6, 4);

INSERT INTO "firecms_categories" ("id", "sectionId", "parentId", "historyId", "createdBy", "publishingDate", "expiringDate", "active", "status", "position", "level", "categoryLeft", "categoryRight", "showInMenu", "public") VALUES
	(1, 1, NULL, NULL, 1, '2026-10-01 18:52:15', NULL, true, 'publish', 1, 0, 1, 4, true, true),
	(2, 1, 1, NULL, 1, '2026-10-01 18:52:15', NULL, true, 'publish', 1, 1, 2, 3, true, true),
	(3, 1, NULL, NULL, 1, '2026-10-01 18:52:15', NULL, true, 'publish', 2, 0, 5, 6, true, true),
	(4, 2, NULL, NULL, 1, '2026-10-01 18:52:15', NULL, true, 'publish', 1, 0, 7, 8, true, true),
	(5, 2, NULL, NULL, 1, '2026-10-01 18:52:15', NULL, true, 'publish', 2, 0, 9, 10, true, true);

INSERT INTO "firecms_categoryArticle" ("categoryId", "articleId", "isMain") VALUES
	(1, 1, false),
	(1, 4, true),
	(2, 1, true),
	(2, 2, true),
	(3, 3, true),
	(4, 5, true),
	(4, 7, true),
	(5, 6, true);

INSERT INTO "firecms_categoryDescriptions" ("categoryId", "languageId", "title", "excerpt", "content", "seoTitle", "seoDescription", "seoKeywords", "viewCount") VALUES
	(1, 'cs', 'Tipy a rady', '<p>Praktické tipy pro každý den.</p>', '<p>Vybrané rady, které se hodí.</p>', 'Tipy a rady', 'Praktické tipy', NULL, 0),
	(1, 'en', 'Tips and advice', '<p>Practical everyday tips.</p>', '<p>Selected advice worth knowing.</p>', 'Tips and advice', 'Practical tips', NULL, 0),
	(2, 'cs', 'Turistika', '<p>Výlety, vybavení a bezpečnost.</p>', '<p>Jak se na výlet dobře připravit.</p>', 'Turistika', 'Výlety, vybavení a bezpečnost', NULL, 0),
	(2, 'en', 'Hiking', '<p>Trips, gear and safety.</p>', '<p>How to prepare well for a trip.</p>', 'Hiking', 'Trips, gear and safety', NULL, 0),
	(3, 'cs', 'Recenze', '<p>Vyzkoušeli jsme za vás.</p>', '<p>Poctivé recenze produktů.</p>', 'Recenze', 'Recenze produktů', NULL, 0),
	(3, 'en', 'Reviews', '<p>We tried it for you.</p>', '<p>Honest product reviews.</p>', 'Reviews', 'Product reviews', NULL, 0),
	(4, 'cs', 'Aktuality', '<p>Co je u nás nového.</p>', NULL, 'Aktuality', 'Novinky z provozu', NULL, 0),
	(4, 'en', 'Updates', '<p>What is new.</p>', NULL, 'Updates', 'Latest updates', NULL, 0),
	(5, 'cs', 'Akce', '<p>Pozvánky na akce.</p>', NULL, 'Akce', 'Pozvánky na akce', NULL, 0),
	(5, 'en', 'Events', '<p>Upcoming events.</p>', NULL, 'Events', 'Upcoming events', NULL, 0);

INSERT INTO "firecms_categoryTags" ("categoryId", "tagId") VALUES
	(2, 1),
	(3, 3),
	(5, 4);

INSERT INTO "firecms_comments" ("id", "parentId", "languageId", "createdBy", "left", "right", "status", "author", "authorEmail", "title", "text", "userIp", "userAgent") VALUES
	(1, NULL, 'cs', NULL, 1, 4, 'publish', 'Jana', 'jana@example.com', 'Díky za recenzi', 'Batoh mám taky a potvrzuji, že na celodenní túru je pohodlný.', '127.0.0.1', 'dummy-data'),
	(2, 1, 'cs', 1, 2, 3, 'publish', NULL, NULL, 'Re: Díky za recenzi', 'Děkujeme za zpětnou vazbu!', '127.0.0.1', 'dummy-data'),
	(3, NULL, 'cs', NULL, 5, 6, 'publish', 'Petr', 'petr@example.com', 'Dotaz na velikost', 'Je batoh vhodný i pro menší postavu?', '127.0.0.1', 'dummy-data');

INSERT INTO "firecms_menus" ("id", "active", "location", "createdBy") VALUES
	(1, true, 'top-menu', 1),
	(2, true, 'left-menu', 1),
	(3, true, 'text-box', 1);

INSERT INTO "firecms_menuDescriptions" ("menuId", "languageId", "title") VALUES
	(1, 'cs', 'Hlavní menu'),
	(1, 'en', 'Main menu'),
	(2, 'cs', 'Informace'),
	(2, 'en', 'Information'),
	(3, 'cs', 'Doporučujeme'),
	(3, 'en', 'Recommended');

INSERT INTO "firecms_menuItems" ("id", "menuId", "parentId", "active", "position", "linkType", "target", "newWindow") VALUES
	(1, 1, NULL, true, 1, 'route', ':Front:Homepage:default', false),
	(2, 1, NULL, true, 2, 'section', '1', false),
	(3, 1, 2, true, 1, 'category', '1', false),
	(4, 1, 2, true, 2, 'category', '3', false),
	(5, 1, NULL, true, 3, 'section', '2', false),
	(6, 1, NULL, true, 4, 'page', '1', false),
	(7, 1, 6, true, 1, 'page', '2', false),
	(8, 1, NULL, true, 5, 'page', '3', false),
	(9, 2, NULL, true, 1, 'page', '4', false),
	(10, 2, NULL, true, 2, 'page', '3', false),
	(11, 2, NULL, true, 3, 'article', '1', false),
	(12, 2, NULL, true, 4, 'url', 'https://nette.org', true),
	(13, 3, NULL, true, 1, 'article', '3', false),
	(14, 3, NULL, true, 2, 'category', '5', false),
	(15, 3, NULL, true, 3, 'page', '2', false);

INSERT INTO "firecms_menuItemDescriptions" ("menuItemId", "languageId", "label") VALUES
	(1, 'cs', 'Domů'),
	(1, 'en', 'Home'),
	(11, 'cs', 'Rada měsíce'),
	(11, 'en', 'Tip of the month'),
	(12, 'cs', 'Postaveno na Nette'),
	(12, 'en', 'Built on Nette'),
	(13, 'cs', 'Recenze měsíce'),
	(13, 'en', 'Review of the month');

INSERT INTO "firecms_pageDescriptions" ("pageId", "languageId", "title", "content", "seoTitle", "seoDescription", "seoKeywords") VALUES
	(1, 'cs', 'O nás', '<p>Jsme malý tým nadšenců, který dělá svou práci s radostí.</p>', 'O nás', 'Kdo jsme a co děláme', NULL),
	(1, 'en', 'About us', '<p>We are a small team of enthusiasts who love what we do.</p>', 'About us', 'Who we are', NULL),
	(2, 'cs', 'Náš tým', '<p>Seznamte se s lidmi, kteří se o vše starají.</p>', 'Náš tým', 'Lidé za naší prací', NULL),
	(2, 'en', 'Our team', '<p>Meet the people behind our work.</p>', 'Our team', 'People behind our work', NULL),
	(3, 'cs', 'Kontakt', '<p>Máte dotaz, nápad nebo se k nám chcete zastavit? Napište nám, zavolejte nebo přijďte osobně - kancelář najdete přímo v centru Brna.</p><p><strong>Otevírací doba:</strong> pondělí-pátek 9-17 hodin</p>', 'Kontakt', 'Jak nás kontaktovat', NULL),
	(3, 'en', 'Contact', '<p>Do you have a question, an idea or would you like to drop by? Write to us, call us or visit us in person - our office is right in the centre of Brno.</p><p><strong>Opening hours:</strong> Monday-Friday 9 am to 5 pm</p>', 'Contact', 'How to reach us', NULL),
	(4, 'cs', 'Obchodní podmínky', '<p>Ukázkové obchodní podmínky.</p>', 'Obchodní podmínky', NULL, NULL),
	(4, 'en', 'Terms and conditions', '<p>Sample terms and conditions.</p>', 'Terms and conditions', NULL, NULL),
	(5, 'cs', 'Připravovaná stránka', '<p>Koncept - na webu se nezobrazí.</p>', NULL, NULL, NULL);

INSERT INTO "firecms_pages" ("id", "parentId", "createdBy", "active", "status", "public", "position", "template") VALUES
	(1, NULL, 1, true, 'publish', true, 1, NULL),
	(2, 1, 1, true, 'publish', true, 1, NULL),
	(3, NULL, 1, true, 'publish', true, 2, 'contact'),
	(4, NULL, 1, true, 'publish', true, 3, NULL),
	(5, NULL, 1, true, 'draft', true, 4, NULL);

INSERT INTO "firecms_sections" ("id", "active", "position") VALUES
	(1, true, 1),
	(2, true, 2);

INSERT INTO "firecms_sectionDescriptions" ("sectionId", "languageId", "title", "content", "seoTitle", "seoDescription", "seoKeywords") VALUES
	(1, 'cs', 'Články', '<p>Tipy, rady a recenze z naší redakce.</p>', NULL, NULL, NULL),
	(1, 'en', 'Articles', '<p>Tips, advice and reviews from our editors.</p>', NULL, NULL, NULL),
	(2, 'cs', 'Novinky', '<p>Aktuality a pozvánky na akce.</p>', 'Novinky', 'Aktuality a akce', NULL),
	(2, 'en', 'News', '<p>Latest news and upcoming events.</p>', 'News', 'News and events', NULL);

INSERT INTO "firecms_sliders" ("id", "name", "location", "duration", "speed", "navigation", "manual") VALUES
	(1, 'Hlavní slider', 'main_menu', 5000, 1000, true, false);

INSERT INTO "firecms_tagDescriptions" ("tagId", "languageId", "name") VALUES
	(1, 'cs', 'turistika'),
	(1, 'en', 'hiking'),
	(2, 'cs', 'zima'),
	(2, 'en', 'winter'),
	(3, 'cs', 'recenze'),
	(3, 'en', 'reviews'),
	(4, 'cs', 'akce'),
	(4, 'en', 'events');

INSERT INTO "firecms_tags" ("id") VALUES
	(1),
	(2),
	(3),
	(4);

INSERT INTO "firecms_urls" ("id", "languageId", "type", "key", "url") VALUES
	(1, 'cs', 'section', 1, 'clanky'),
	(2, 'en', 'section', 1, 'articles'),
	(4, 'cs', 'section', 2, 'novinky'),
	(5, 'en', 'section', 2, 'news'),
	(6, 'cs', 'category', 1, 'tipy-a-rady'),
	(7, 'en', 'category', 1, 'tips-and-advice'),
	(8, 'cs', 'category', 2, 'turistika'),
	(9, 'en', 'category', 2, 'hiking'),
	(10, 'cs', 'category', 3, 'recenze'),
	(11, 'en', 'category', 3, 'reviews'),
	(12, 'cs', 'category', 4, 'aktuality'),
	(13, 'en', 'category', 4, 'updates'),
	(14, 'cs', 'category', 5, 'akce'),
	(15, 'en', 'category', 5, 'events'),
	(16, 'cs', 'article', 1, 'jak-vybrat-spravnou-obuv'),
	(17, 'en', 'article', 1, 'how-to-choose-the-right-shoes'),
	(18, 'cs', 'article', 2, 'prochazky-v-zime'),
	(19, 'en', 'article', 2, 'winter-walks'),
	(20, 'cs', 'article', 3, 'recenze-batoh-comfort'),
	(21, 'en', 'article', 3, 'review-comfort-backpack'),
	(22, 'cs', 'article', 4, 'rozpracovany-clanek'),
	(23, 'cs', 'article', 5, 'otevreli-jsme-novou-pobocku'),
	(24, 'en', 'article', 5, 'we-opened-a-new-branch'),
	(25, 'cs', 'article', 6, 'den-otevrenych-dveri'),
	(26, 'en', 'article', 6, 'open-day'),
	(27, 'cs', 'article', 7, 'nove-oteviraci-hodiny'),
	(28, 'en', 'article', 7, 'new-opening-hours'),
	(29, 'cs', 'page', 1, 'o-nas'),
	(30, 'en', 'page', 1, 'about-us'),
	(31, 'cs', 'page', 2, 'nas-tym'),
	(32, 'en', 'page', 2, 'our-team'),
	(33, 'cs', 'page', 3, 'kontakt'),
	(34, 'en', 'page', 3, 'contact'),
	(35, 'cs', 'page', 4, 'obchodni-podminky'),
	(36, 'en', 'page', 4, 'terms-and-conditions'),
	(37, 'cs', 'page', 5, 'pripravovana-stranka');

-- Ukázkové Nastavení (basic-data zakládá jen název webu a e-mail): popis webu, SEO a kontaktní údaje
-- pro šablonu stránky "Kontakt" (adresa po jazycích, telefon a GPS bod mapy společné)
UPDATE "firecms_settings" SET
	"contactPhone" = '+420 123 456 789',
	"mapLatitude" = 49.195060,
	"mapLongitude" = 16.606837
WHERE "id" = 1;

UPDATE "firecms_settingDescriptions" SET
	"mainDescription" = 'Ukázkový web postavený na Fire CMS',
	"contactAddress" = E'Náměstí Svobody 1\n602 00 Brno',
	"seoDescription" = 'Tipy, rady, recenze a novinky z naší redakce.',
	"seoKeywords" = 'tipy, rady, recenze, novinky'
WHERE "settingId" = 1 AND "languageId" = 'cs';

UPDATE "firecms_settingDescriptions" SET
	"mainDescription" = 'A sample website built on Fire CMS',
	"contactAddress" = E'Náměstí Svobody 1\n602 00 Brno\nCzech Republic',
	"seoDescription" = 'Tips, advice, reviews and news from our editors.',
	"seoKeywords" = 'tips, advice, reviews, news'
WHERE "settingId" = 1 AND "languageId" = 'en';

-- data mají pevná id: sekvence identity posunout za nejvyšší id, jinak by další INSERT kolidoval
SELECT setval(pg_get_serial_sequence('"firecms_comments"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_comments"), false);
SELECT setval(pg_get_serial_sequence('"firecms_sections"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_sections"), false);
SELECT setval(pg_get_serial_sequence('"firecms_articles"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_articles"), false);
SELECT setval(pg_get_serial_sequence('"firecms_categories"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_categories"), false);
SELECT setval(pg_get_serial_sequence('"firecms_tags"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_tags"), false);
SELECT setval(pg_get_serial_sequence('"firecms_menus"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_menus"), false);
SELECT setval(pg_get_serial_sequence('"firecms_menuItems"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_menuItems"), false);
SELECT setval(pg_get_serial_sequence('"firecms_pages"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_pages"), false);
SELECT setval(pg_get_serial_sequence('"firecms_sliders"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_sliders"), false);
SELECT setval(pg_get_serial_sequence('"firecms_urls"', 'id'), (SELECT COALESCE(MAX("id"), 0) + 1 FROM "firecms_urls"), false);
