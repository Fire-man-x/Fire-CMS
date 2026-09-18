# Index změn

Chronologický přehled (nejnovější nahoře). Každý řádek odkazuje na detailní záznam v `Changelog/`.

## 2026-09-18

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
