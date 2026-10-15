# Index změn

Chronologický přehled (nejnovější nahoře). Každý řádek odkazuje na detailní záznam v `Changelog/`.

## 2026-10-15

- **E-mail a telefon webu v globálním nastavení i po jazycích.** `firecms_settings.mainEmail` a
  `firecms_settingDescriptions.contactPhone` (migrace `structures/20261015120000.sql`), prázdná hodnota jazyka = globální.
  Viz [2026-10-15-settings-email-phone.md](2026-10-15-settings-email-phone.md).

- **Přepínače aktivity v gridech na PostgreSQL.** Status sloupce gridů uživatelů, článků (i hlavní kategorie), menu,
  jazyků a domén ukazovaly na PostgreSQL jen `1`; příznak se teď do gridu posílá jako `int` z SQL.
  Viz [2026-10-15-datagrid-status-pgsql.md](2026-10-15-datagrid-status-pgsql.md).

- **Detail článku: kategorie, pole URL, přepínač aktivity.** Obrázky a grid kategorií za formulářem článku (datagrid
  by vnořil formulář), „Přidat“ v okně kategorií už neotevírá okno obrázků, přidání obrázku nemaže pole URL,
  přepínač aktivity jen u článků s právem `edit` a bez chybové stránky.
  Viz [2026-10-15-articles-detail-fixes.md](2026-10-15-articles-detail-fixes.md).

- **Společný návrh URL z názvu.** Rozhraní `UrlValidation` + `UrlValidationTrait` místo čtyř kopií `handleValidateUrl()`
  (články, kategorie, stránky, sekce). U editovaného článku a kategorie se už nenavrhuje `…-1` místo vlastní URL.
  Viz [2026-10-15-url-validation.md](2026-10-15-url-validation.md).

- **`#[Secured('Zdroj', 'právo')]` místo `#[Secured]` + `#[Resource]` + `#[Privilege]`.** Opakovatelný atribut s povinnými
  parametry, `recordIdParameter:` pustí autora záznamu ke všemu (zatím `ArticlesPresenter::actionDetail()`). Bez zpětné kompatibility,
  pluginy projektů je potřeba převést. Viz [2026-10-15-secured-attribute.md](2026-10-15-secured-attribute.md).

## 2026-10-14

- **Mapa autorů záznamů pro ACL.** Služba `App\Security\ResourceOwners` (`resourceOwners` v `config.neon`) vrátí
  podle zdroje ACL a ID záznamu jeho autora (`createdBy`), pluginy přidají zdroj přes `setup: - add()`. Zatím ji nic nevolá.
  Viz [2026-10-14-resource-owners.md](2026-10-14-resource-owners.md).

- **Flash zprávy administrace vpravo dole.** Zásobník v pravém dolním rohu, zprávy se zavřou po 5 s, najetí myší
  odpočet pozastaví. Viz [2026-10-14-admin-flash-toasts.md](2026-10-14-admin-flash-toasts.md).

- **PHPStan level 5 bez baseline.** Celé `app/` prochází s prázdnou `phpstan-baseline.neon`, uzly maker obrázků
  (`app/FileStorage/Macro/Nodes/*`) jsou `final`. Nová chyba se opravuje, ne přidává do baseline.
  Viz [2026-10-14-phpstan-empty-baseline.md](2026-10-14-phpstan-empty-baseline.md).

- **URL a přesměrování bez `UrlModule`.** `App\Model\UrlManager`, modely `App\Model\Database\Urls`/`Redirections`,
  presenter a formuláře v `app/AdminModule`. `PresenterFactory` už neprohledává `app/Modules`.
  Viz [2026-10-14-urls-from-module.md](2026-10-14-urls-from-module.md).

- **VisualPaginator 4.0.2 pro PHP 8.3, Nette 3.3 a Latte 3.** `libs/VisualPaginator-4.0.2` bez zastaralých API
  (session, `@persistent`, `{php}`), typovaná šablona `VisualPaginatorTemplate`. Opraveno: hodnota v session při
  vypnutém výběru počtu položek už nevyhodí výjimku a změna počtu položek přes AJAX platí hned. Nové
  `setDefaultItemsPerPage()` (pevný počet bez výběru), komponenty Articles, Categories, SearchControl a Comments převedené z 3.0. Nová
  šablona `bootstrap5.latte`, v `config.neon` nastavená pro všechny paginatory.
  Viz [2026-10-14-visualpaginator-4-php83.md](2026-10-14-visualpaginator-4-php83.md).

- **Formuláře administrace na `AdminFormFactory`.** Všechny formuláře v `app/AdminModule/Forms/` (článek, kategorie,
  stránka, sekce, menu, mety, štítky, slidery, nastavení, dynamické formuláře, upload souborů) mají formát
  `DomainFormFactory`: pole v `data`, překlad v `locales[jazyk]`, tlačítka v `buttons`, typy `AdminForm`/`AdminFormValues`.
  Šablony odkazují na `{input data-active:}` apod.
  Viz [2026-10-14-admin-forms-adminformfactory.md](2026-10-14-admin-forms-adminformfactory.md).

## 2026-10-09

- **Šablony komponent z tématu (`TemplateLookupTrait`), zatím u Menu.** Šablona komponenty se hledá v
  `theme/FrontModule/Components/<Komponenta>/` projektu, pak u komponenty. `{control menu:<view> <location>}` vykreslí
  menu vlastní šablonou `<view>.latte`.
  Viz [2026-10-09-menu-custom-template.md](2026-10-09-menu-custom-template.md).

## 2026-10-08

- **Potvrzovací okno v gridech administrace.** `main.js` hledal `data-target` místo `data-bs-target`
  (Bootstrap 5): „Smazat“ v gridu mazalo hned bez potvrzení a okno pro výběr obrázku zůstávalo prázdné.
  Opraveno i pro gridy překreslené AJAXem. Viz [2026-10-08-admin-confirm-modal.md](2026-10-08-admin-confirm-modal.md).

## 2026-10-07

- **Přímé odkazy na náhledy (`directThumbnails`).** Lokální úložiště může odkazovat přímo na náhledy;
  existující pošle web server, chybějící (i smazaný) vytvoří aplikace na jeho adrese
  (`NamingScheme::parseThumbnailPath()`, `FlysystemStorage::thumbnailFromPath()`). Kořen úložiště potřebuje
  `.htaccess` jako `www/files/.htaccess`. Viz [2026-10-07-direct-thumbnails.md](2026-10-07-direct-thumbnails.md).

- **Obecná úložiště: schéma názvů a náhledy pro každé úložiště.** Každá položka pod `fileStorage:` je
  úložiště (`FlysystemStorage`) s vlastním schématem názvů (`NamingScheme`, správce souborů `HashNamingScheme`)
  a vlastním seznamem náhledů (`thumbnails:` u úložiště, aliasy rozměrů). Generátor náhledů obslouží každé
  úložiště (`/files/thumbnail/<úložiště>/<klíč originálu>/<náhled>`, bez DB). Globální `fileStorage: thumbnails:`
  shodí start aplikace, staré `FileStorage`/`HashFileStorage` jsou smazané. Viz
  [2026-10-07-storage-naming-schemes.md](2026-10-07-storage-naming-schemes.md).

- **`ImageRequest` přijímá libovolnou `ImageEntity`.** Makra `n:src`/`n:image`/`n:crop`/`n:bg` umí i obrázky
  mimo správce souborů (bez id a hashe). Viz
  [2026-10-07-imagerequest-image-entity.md](2026-10-07-imagerequest-image-entity.md).

- **Správce souborů v `app/FileStorage`, DI rozšíření `fileStorage:`.** Namespace `App\Components\FileManager`
  → `App\FileStorage`, sekce neonu `fileManager:` → `fileStorage:` (služby `@fileStorage.filesystem.<název>`).
  Projekty musí upravit kód pluginů a šablon a neon včetně `config.local.neon` na serveru, jinak web po deployi
  nenastartuje. Viz [2026-10-07-filestorage-namespace.md](2026-10-07-filestorage-namespace.md).

## 2026-10-02

- **Nasazení projektů přes GitHub Actions.** Vzor `.github/workflows/deploy.yml.dist` (CI → rsync přes SSH →
  migrace), každý projekt si vytvoří vlastní `deploy.yml`. Dokumentace projektu patří do `theme/docs/`. Návody
  `doc/github-deploy.md` a `doc/update-project.md` nahradily GitLab verze. Viz
  [2026-10-02-github-deploy.md](2026-10-02-github-deploy.md).

- **Pluginy Stalker a Statistics na PostgreSQL.** Migrace a `deactivate` skript zvlášť pro MariaDB a PostgreSQL
  (`data/migrations/<driver>/`), migrace přejmenované na `2026100200000x`. Projekty se zapnutým pluginem musí
  přejmenovat záznam v tabulce `migrations`. Viz [2026-10-02-plugins-postgresql.md](2026-10-02-plugins-postgresql.md).

## 2026-09-30

- **Výchozí stav jádra.** Historie repozitáře sloučená do jednoho výchozího commitu, changelog začíná znovu.
  Přehled funkcí jádra, konvence DB schématu a co čeká projekty založené na starší verzi jádra. Viz
  [2026-09-30-initial-core-state.md](2026-09-30-initial-core-state.md).
