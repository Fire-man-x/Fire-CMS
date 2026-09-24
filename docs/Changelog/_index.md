# Index změn

Chronologický přehled (nejnovější nahoře). Každý řádek odkazuje na detailní záznam v `Changelog/`.

## 2026-09-24

- **Názvy položek menu v administraci** (výběr nadřazené položky místo „Page: 2“) přes `MenuItemTitles`; `LanguageService::existLanguage(?string)`. Viz
  [2026-09-24-menu-item-titles.md](2026-09-24-menu-item-titles.md).
- **Obrázky u položek menu** (`firecms_menuItemFiles`, sloupec „Obrázky“ v gridu položek, `$category->image`/`files` v šabloně menu). Vyžaduje migraci. Viz
  [2026-09-24-menu-item-images.md](2026-09-24-menu-item-images.md).
- **Sekce (Blog, Novinky, …) pro kategorie a články, kategorie bez typů (`homepage`/`gallery`), ukázková data (`dummyData`).** Bez převodu dat, vyžaduje `migrations:reset`. Viz
  [2026-09-24-sections.md](2026-09-24-sections.md).
- **Stránky: obrázky a vnořování; popisky položek menu pro všechny jazyky najednou.** Vyžaduje migraci. Viz
  [2026-09-24-pages-images-nesting.md](2026-09-24-pages-images-nesting.md).
- **Nový typ obsahu Stránka (`Pages`)**: vlastní tabulky, SEO, URL typu `page`, administrace, web, odkaz z menu. Opravená PHPStan brána (odkaz na smazaný soubor v baseline). Viz
  [2026-09-24-pages.md](2026-09-24-pages.md).
- **Kategorie bez typů `url`, `categoryLink`, `textBox` (odkazy řeší položky menu), sekce přejmenovaná na „Kategorie článků“.** Jádro, vyžaduje migraci. Viz
  [2026-09-24-category-types-cleanup.md](2026-09-24-category-types-cleanup.md).

## 2026-09-23

- **Překlady menu: nadpis `title` po jazycích (`firecms_menuDescriptions`), odstraněný `firecms_menus.name`.** Existující DB potřebují ruční krok (upravená původní migrace). Viz
  [2026-09-23-menu-translations.md](2026-09-23-menu-translations.md).
- **Položky menu oddělené od kategorií** (`linkType` category/article/url/route + `target`, vnořování, popisky po jazycích). Jádro, vyžaduje migraci. Viz
  [2026-09-23-menu-items-separated.md](2026-09-23-menu-items-separated.md).
- **Oprava: formulář jazyka mohl vytvořit dva výchozí jazyky.** Nově `Languages::setDefault()` (jádro). Viz
  [2026-09-23-languages-single-default.md](2026-09-23-languages-single-default.md).
- **Překlady tématu v `theme/data/localization/` (tag `translator.themeStorage`, přednost před pluginy) + přeložená homepage a layout.** Viz
  [2026-09-23-theme-translations.md](2026-09-23-theme-translations.md).
- **Překlady pluginů v `<plugin>/data/localization/` (`App\Localization\ChainTranslatorStorage`, jádro) + PetHotel přeložený** (`cs.admin`, `cs.front`, anglické zdrojové texty). Viz
  [2026-09-23-plugin-translations-pethotel.md](2026-09-23-plugin-translations-pethotel.md).
- **PetHotel: maximální váha psa u jednotek (`maxPetWeight`) a filtr „Velikost psa“ ve vyhledávání.** Viz
  [2026-09-23-pethotel-facility-max-weight.md](2026-09-23-pethotel-facility-max-weight.md). Vyžaduje migraci.
- **PetHotel: vyhledávání ubytování podle města a termínu** (komponenta `hotelSearch`, volná kapacita z kalendáře rezervací). Viz
  [2026-09-23-pethotel-hotel-search.md](2026-09-23-pethotel-hotel-search.md).
- **PetHotel: výpis hotelů přes komponentu HotelList s filtrem podle města**, chipy měst na homepage na něj odkazují. Viz
  [2026-09-23-pethotel-hotel-list-town-filter.md](2026-09-23-pethotel-hotel-list-town-filter.md).
- **PetHotel: bezpečnější přihlášení kódem.** Vstup majitele jen s vlastním ubytováním, e-mail v session místo URL,
  kód hned po registraci, ubytování na existující účet až po ověření kódem. Viz
  [2026-09-23-pethotel-login-hardening.md](2026-09-23-pethotel-login-hardening.md).
- **Presenter v `theme/` přepíše stejnojmenný presenter z `app/`** (`Theme\X` místo `App\X`, šablony se berou
  i z původního). Viz [2026-09-23-theme-presenter-override.md](2026-09-23-theme-presenter-override.md).
- **SDH pluginy převedeny na konvence jádra (camelCase, PK `id`, `createDate`/`updateDate`) + import ze staré DB `sdh`.** Viz
  [2026-09-23-sdh-camelcase-id.md](2026-09-23-sdh-camelcase-id.md). Opravena kolize klíče skupiny migrací `statistics`
  (SDHBase vs. plugin Statistics), `who_subtypes` → `firecms_plugin_whoSubtypes`.
- **Plugin PetHotel převeden na camelCase sloupce.** Viz [2026-09-23-pethotel-camelcase.md](2026-09-23-pethotel-camelcase.md).
  Migrace přepsané (reset DB), kód pluginu převedený včetně vypočtených aliasů.
- **Tabulky Stalker, Statistics a Sliders přesunuty z core migrace do vlastních migrací pluginů** (Stalker/Statistics nově registrované). Viz
  [2026-09-23-stalker-statistics-plugin-migrations.md](2026-09-23-stalker-statistics-plugin-migrations.md).
  Smazána core migrace `structures/20200324212900.sql` (její ALTER je zapracovaný do plugin migrace).
- **Všechny tabulky mají `createDate` + `updateDate` hned za `id` a cizími klíči.** Viz
  [2026-09-23-create-update-date-columns.md](2026-09-23-create-update-date-columns.md). Migrace přepsány (reset DB).
- **Plugin DynamicForms převeden na camelCase sloupce a PK `id`.** Viz
  [2026-09-23-dynamicforms-camelcase-id.md](2026-09-23-dynamicforms-camelcase-id.md). Migrace pluginu přepsána
  (nutný reset jeho tabulek), model implementuje `Translatable`.
- **Proklikání administrace po přejmenování sloupců + rozhraní `App\Model\Translatable`.** Viz
  [2026-09-23-admin-smoke-test-fixes.md](2026-09-23-admin-smoke-test-fixes.md). Opraveny zbytky přejmenování
  a starší chyby, které blokovaly ukládání, aktivaci a mazání (ID z datagridu jako řetězec, `makeBackup()`,
  staré názvy tabulek v joinech). Modely s `*Descriptions` implementují `Translatable`.
- **Zrušen duplicitní sloupec `gridName` (articles/categories/tags/dynamicForms).** Viz
  [2026-09-23-remove-gridname.md](2026-09-23-remove-gridname.md). Gridy a výpisy berou název z `*Descriptions`
  v jazyce nastaveném v administraci (fallback výchozí jazyk) přes nový
  `App\Model\TranslatedTitleTrait\TranslatedTitleTrait`. Grid tagů už nepoužívá `GROUP BY` s náhodným překladem.
- **DB sloupce jádra převedené na camelCase, AUTO_INCREMENT PK přejmenované na `id`.** Viz
  [2026-09-23-db-columns-camelcase-id.md](2026-09-23-db-columns-camelcase-id.md). Migrace přepsané přímo
  (DB se resetuje), FK sloupce se jmenují `<entita>Id`. `BaseModel` má nový `getForeignKeyColumn()`,
  datagrid akce posílají FK název místo `id`. Kód pod `theme/` (klientské pluginy) převedený jen v migracích.

## 2026-09-22

- **`firecms_options` převedeno na `firecms_settings` + `firecms_settingDescriptions` (per-jazyk).** Viz
  [2026-09-22-options-to-settings.md](2026-09-22-options-to-settings.md). Dvě nové migrace —
  `data/migrations/structures/20260922130000.sql` (DDL) + `data/migrations/basic-data/20260922140000.sql`
  (přenos dat + drop staré tabulky, schválně odděleno kvůli pořadí skupin structures→basic-data) —
  `App\Model\Options` → `App\Model\Settings`, `SettingFormFactory` přepsán na kontejner-na-jazyk
  (vzor `TagFormFactory`). `$options` template proměnná má beze změny stejný tvar, žádná šablona se
  neupravovala. Migrace zatím nespuštěné proti dev DB (jen otestované v izolovaných zahazovacích
  databázích pro scénáře "fresh install" i "už naseedované prostředí").

## 2026-09-18

- **`LiveTranslator` přesunut z ruční kopie v `libs/` na skutečný Composer balíček.** Viz
  [2026-09-18-livetranslator-composer-package.md](2026-09-18-livetranslator-composer-package.md).
  `composer.json` → `repositories` (vcs fork `Fire-man-x/LiveTranslator`) + `require
  vladahejda/livetranslator: "2.0"` (zamčeno natvrdo, na Packagistu existuje stejnojmenný starý balíček).
  `libs/LiveTranslator/` zatím ponecháno na disku, ale odpojeno.
- **`libs/LiveTranslator` opraven pro PHP 8.3/aktuální Nette — spadal na každém requestu.** Viz
  [2026-09-18-livetranslator-php83-nette-compat.md](2026-09-18-livetranslator-php83-nette-compat.md).
  Root cause: `$presenterLanguageParam` default `array()` vs. návratový typ `string`; `Application::$presenter`
  je teď privátní (`getPresenter()`). Přidán regresní test.
- **`App\Security\*` přesunuto z `app/Components/Security/` do `app/Security/` (PSR-4 konečně sedí).**
  Viz [2026-09-18-security-app-security-move.md](2026-09-18-security-app-security-move.md). Nahrazuje
  dopolední `composer.json` classmap workaround z položky níže — ten je teď zase prázdný.
- **Rozšířeny testy o `CustomRouter`, `App\Security\Role` a zbytek `UrlManager`.** Viz
  [2026-09-18-nette-tester-more-tests.md](2026-09-18-nette-tester-more-tests.md). Nový
  `composer.json` classmap pro `App\Security\*` (nahrazeno přesunem, viz položka výše). Mimochodem
  zjištěno: jednoargumentová varianta konstruktoru `Role` je mrtvý/rozbitý kód (chybějící třídy
  `Identity`/`Exception`) — needěláno, k rozhodnutí.
- **Založena testovací infrastruktura (Nette Tester, `composer test`).** Viz
  [2026-09-18-nette-tester-setup.md](2026-09-18-nette-tester-setup.md). Integrační testy nad in-memory
  SQLite Explorerem (bez DI kontejneru), pilotně `UrlManager::validateUrl`. Mimochodem zdokumentovaná
  past: `validateUrl()` hlídá unikátnost URL napříč celou tabulkou, ignoruje `type`/`language_id`.

## 2026-09-17

- **DB tabulky jádra a pluginů dostaly prefix `firecms_`/`firecms_plugin_` (název za prefixem camelCase).** Viz
  [2026-09-17-db-table-prefix.md](2026-09-17-db-table-prefix.md). Core (`app/Model`, `app/Modules`,
  `app/Components/Menu`) + bundlované pluginy (`Sliders`/`Statistics`/`Stalker`/`DynamicForms`) +
  `PetHotel` přejmenovány (migrace i modely).
- **... a dodatečně i zbylé SDH pluginy v `theme/Plugins`.** Viz
  [2026-09-17-sdh-db-table-prefix.md](2026-09-17-sdh-db-table-prefix.md). `SDHAttendance`/`SDHCalendar`/
  `SDHTowns`/`SDHEvents`/`SDHTests` (~40 tabulek v samostatné legacy DB `sdh`) přejmenovány na žádost
  zadavatele i přes vyšší riziko (bez testů, žádný DB přístup k ověření zde) — `RENAME TABLE` skripty
  musí někdo spustit ručně přímo proti `sdh` před nasazením, `nextras/migrations` tam nedosáhne.
- **Jazykové mutace jde přiřadit na vlastní doménu (nová tabulka `firecms_domains`).** Viz
  [2026-09-17-language-domains.md](2026-09-17-language-domains.md). Bez konfigurace se chování nemění;
  starý `/xx/` prefixový odkaz na jazyk s přiřazenou doménou dostane 301 na tu doménu. Mimochodem opraven
  bug v `CustomRouter::match()`, kde se spočtený presenter nikdy nezapisoval do vráceného pole.
- **Nový plugin `SDHBase` zakládá sdílené schéma legacy SDH tabulek (`sdh` databáze) jedním místem.** Viz
  [2026-09-17-sdh-base-plugin.md](2026-09-17-sdh-base-plugin.md). Nahrazuje duplicitní/kolidující CREATE
  TABLE pokusy v jednotlivých SDH pluginech (`.sql.bac` soubory) - `bin/console sdh:install-schema`, mimo
  `nextras/migrations` (ten na `sdh` databázi nedosáhne). `who_subtype` sjednoceno na `who_subtypes`,
  tabulka `places` (chyběla v exportu) odvozena z kódu - k ověření proti produkci.

## 2026-09-16

- **`DynamicForms` blokoval `bin/console` na prázdné DB — eager ACL build v `initialize()`.** Viz
  [2026-09-16-dynamicforms-eager-acl-cli-fix.md](2026-09-16-dynamicforms-eager-acl-cli-fix.md).
  `migrations:reset` na čerstvé DB padal na "Table 'roles' doesn't exist", protože
  `App\Plugins\DynamicForms\DI\Extension::afterCompile()` eagerly stavěl `ContactFormControl` →
  ... → `security.user` → `authorizator` → `Roles::getListWithName()` v KAŽDÉM bootu kontejneru (web i
  CLI), ještě před tím, než migrace stihly `roles` tabulku vytvořit. Opraveno guardem na `%consoleMode%`
  (plugin, ne jádro). Vyžaduje smazat `temp/cache/nette.configurator/Container_*.php*`, aby se projevilo.
- **`Hotel` plugin (`app/Plugins/Hotel/`): marketplace pro ubytování zvířat, postavený na frontendovém
  self-service místo admin-spravovaného CRUD.** Nahrazuje předchozí verzi (viz záznam níže "Port
  funkcionality Pets/Owners/..." — ten kód byl smazán, ne přesunut, protože vlastnický model byl jinak).
  Majitelé nemovitostí a zákazníci se registrují a přihlašují na frontendu (`App\Model\UserManager` +
  dvě nové role `property_owner`/`customer`, žádný nový auth systém), spravují svá vlastní data
  (`Property`/`Customer`/`Pet`/`Facility`), zákazník vytváří `Reservation` s validací kapacity
  (`Reservations::countOverlapping()`). Hlavní administrátor má nad vším plný CRUD přes nové resources
  `HotelProperties`/`HotelCustomers`/`HotelPets`/`HotelFacilities`/`HotelReservations` (přiřadit ručně
  přes Settings → Roles, stejně jako u všech ostatních resources v repu). `ContractTemplates`, skutečné
  vyhledávání a platby zůstávají mimo rozsah. Migrace `app/Plugins/Hotel/data/migrations/20260916140000.sql`
  (obsahuje i seed pro obě nové role), registrovaná jako vlastní `nextras/migrations` skupina přímo v
  `config.plugin.neon` (žádný zásah do core `config.neon`) — **není ještě aplikovaná** na žádnou DB, admin
  ACL grant taky ne. Viz `docs/Architecture/plugins.md` a `docs/AI-Context/gotchas.md` pro detaily.
- **Znalostní báze `docs/` opravena, aby odpovídala tomuto projektu.** Adresář `docs/` byl omylem zkopírován
  z jiného projektu (jiné ORM — Record/Repository/Collection místo `BaseModel`, jiný počet pluginů, jiná
  moduly). Přepsán tak, aby popisoval skutečnou architekturu Fire CMS. `CLAUDE.md` "Tvrdá pravidla" opravena
  (`bin/phpstan.neon` neexistuje, skutečná brána je `app/config/phpstan.neon` level 5).
- **Port funkcionality Pets/Owners/Reservations/Facilities/ContractTemplates z `pet-hotel`.** Porovnáno
  s `/var/www/pet-hotel` (starší klientský projekt forknutý z tohoto jádra), chybějící třídy/šablony
  přeneseny a převedeny na PSR-4 + aktuální konvence jádra (constructor injection, PHP atributy pro ACL,
  `Contributte\Datagrid`). Vynechána mrtvá/nedokončená vedlejší větev (tisk rezervace do PDF, "Projects"
  multi-tenancy stub, starší nahrazená core infrastruktura typu `RouterFactory`/`ExtensionLoader`). Nová DB
  migrace `data/migrations/20260916120000.sql`. Nic z tohohle nebylo commitnuto (na žádost uživatele —
  čeká na jeho vlastní review a funkční test).
- **Správa pluginů v administraci (`:Admin:Plugins:`) + revertovaný pokus o opravu kompilace kontejneru
  při vypnutém pluginu.** Přidán `App\AdminModule\Presenters\PluginsPresenter` +
  `App\Model\Plugin\PluginRepository` — datagrid nad nalezenými pluginy s zapnout/vypnout přepínačem
  (přepisuje `theme/config/plugins.neon`). Následně objeven hlubší, nezávislý problém: vypnutý plugin
  shazuje kompilaci CELÉHO DI kontejneru (viz `AI-Context/gotchas.md`, "Vypnutý plugin a kompilace
  kontejneru"). Oprava (`App\DI\PluginPresenterGateExtension`) byla funkčně otestovaná a fungovala, ale na
  žádost uživatele byla revertována (`git revert` commitu "Plugins Gate") — momentálně tedy jen routing-time
  gating v `App\Application\PresenterFactory` zůstává, hlubší problém je jen zdokumentovaný, ne opravený.
