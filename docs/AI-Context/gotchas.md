# Gotchas

Konkrétní pasti objevené při práci na tomto repozitáři. Číst před laděním něčeho, co "by mělo fungovat".

## Routing: presenter jméno v URL musí být malými písmeny

`Nette\Application\Routers\Route` defaultně vyžaduje `[a-z][a-z0-9.-]*` pro `<presenter>` segment masky a
teprve `path2presenter`/`presenter2path` filtr převádí na/z PascalCase (`dynamic-forms` ↔ `DynamicForms`).
URL jako `/administrace/Sliders/default` (velké S) **nikdy nematchne** — spadne jako "no route", i když
presenter `Sliders` reálně existuje a je aktivní. Při ručním testování přes `curl` vždy použijte lowercase
(případně s pomlčkami), ne název třídy 1:1. Tohle se dá snadno splést s daleko vážnějším problémem (viz
další bod) — než začnete hledat bug v kódu, ověřte URL casing.

## `theme/Plugins/*` presentery registrované přes `search:` bez `tags: [nette.inject]`

Automatická registrace presenterů `theme/Plugins/*` balíčku (viz `Architecture/plugins.md`, `search:`
blok v `config.plugin.neon`) BEZ `tags: [nette.inject]` vypadá zpočátku, že funguje — kontejner
zkompiluje, `PresenterFactory` presenter najde a vytvoří — ale spadne hned na první akci s `Error: Typed
property Nette\Application\UI\Presenter::$httpResponse must not be accessed before initialization`
(NE s chybějící službou). Příčina: `Nette\DI\Extensions\SearchExtension` (co `search:` implementuje)
zaregistruje třídu jako službu, ale NEPŘIDÁ tag `nette.inject` — bez něj `InjectExtension` nikdy
nezavolá `Presenter::injectPrimary()`. `Nette\Bridges\ApplicationDI\ApplicationExtension` (auto-registrace
`app/Plugins/*`/`app/Modules/*` presenterů) tenhle tag přidává samo, proto tam stejný problém nikdy nevidíte.

## Neexistující adresář v neonu jádra shodí čistý klon

Adresáře, které žijí jen v klientském projektu (`theme/Plugins/`, `theme/data/localization/`), v repozitáři
jádra nejsou. Sledovaný neon jádra (`config.neon`, `theme/config/theme.neon`) na ně proto nesmí odkazovat:
- `search: <klíč>: in:` s neexistujícím adresářem shodí kompilaci kontejneru na „Option 'search › … › in'
  must be valid directory name“,
- `ReadOnlyFileStorage` (`LiveTranslator\Storage\File`) s neexistujícím adresářem vyhodí
  `DirectoryNotFoundException` při vytvoření `translatorStorage`, tedy prakticky u každého požadavku.

Takové zapojení patří do `config.plugin.neon` balíčku, případně do `theme.neon` klientského projektu. V lokálním
checkoutu jádra s nesledovanými klientskými soubory se chyba neprojeví, ověřte ji v čistém klonu.

## Vypnutý plugin a kompilace kontejneru — POUZE `app/Plugins/*` a `app/Modules/*`

Když je plugin vypnutý v `theme/config/plugins.neon`, jeho třída presenteru pořád fyzicky existuje na
disku. `Nette\Bridges\ApplicationDI\ApplicationExtension` při KOMPILACI kontejneru (ne za běhu) skenuje
presentery a ověřuje, že jejich `@inject`/konstruktor jdou naautowirovat — o `theme/config/plugins.neon`
nic neví. Výsledek: `Nette\DI\MissingServiceException` na presenteru vypnutého pluginu **shodí kompilaci
CELÉHO kontejneru**, ne jen stránku toho pluginu — jakákoliv jiná stránka administrace i frontendu
přestane fungovat.

**Týká se to ale JEN `app/Plugins/*` (a `app/Modules/*`), NE `theme/Plugins/*`.** Ověřeno přímo v
`vendor/nette/bootstrap/src/Bootstrap/Configurator.php`: výchozí registrace `ApplicationExtension` je
`['%debugMode%', ['%appDir%'], '%tempDir%/cache/nette.application']` — druhý argument (`scanDirs`) je
PEVNĚ `%appDir%`, nekonfigurovatelné z `config.neon` a nezávislé na `App\Application\PresenterFactory`'s
vlastním `setScanDirs()` (to je JINÁ, samostatná RobotLoader instance s vlastní cache v `temp/cache/
nette.application`, viz `findPresenters()` v `ApplicationExtension`). Presentery pod `theme/Plugins/<Name>/`
proto tahle eager validace NIKDY nenajde — celá tahle třída bugů se `theme/Plugins/*` balíčků strukturálně
netýká. Praktický důsledek: klientsky/projektově specifickou funkcionalitu, kterou chcete bezpečně
zapínat/vypínat, umisťujte do `theme/Plugins/`, ne do `app/Plugins/`.

**Routing-time gating v `App\Application\PresenterFactory` neexistuje.** `resolveFallback()` kandidáty podle
aktivních pluginů nefiltruje, takže vypnutý `app/Plugins/*` plugin s presenterem může spadnout na
`MissingServiceException` místo čistého 404, pokud RobotLoaderova cache (`temp/cache/nette.application`) jeho
presenter najde. Gating (filtrování `resolveFallback()` kandidátů podle `App\Model\Plugin\PluginRepository
::isActive()`) je samostatná, lehká oprava NEZÁVISLÁ na těžší opravě kompilace níže.

**Vyzkoušené opravy kompilace** — pro `app/Plugins/*`/`app/Modules/*`, kde se `theme/Plugins/*` obchvat
výše nepoužije:
- Omezení `application.scanDirs` na core adresáře / `scanDirs: false` v `config.neon`.
- Vyloučení neaktivních pluginů z hlavního RobotLoaderu (`$robotLoader->excludeDirectory(...)` v
  `bootstrap.php`).
- Vlastní `Nette\DI\CompilerExtension`, která po `ApplicationExtension` a před `InjectExtension`
  odstraní definice presenterů patřících neaktivním pluginům (`removeDefinition()`).

Všechny tři v izolaci fungovaly, v repozitáři ale **žádná oprava není** (poslední byla na žádost zadavatele
vrácená). Pokud na problém narazíte, třetí přístup (vlastní CompilerExtension) je nejčistší a nejméně
rizikový — nedotýká se RobotLoaderu ani `application.scanDirs` (obojí se v testech ukázalo nečekaně
křehké v kombinaci s dalšími extensions), operuje čistě na úrovni `ContainerBuilder` definic. Klíčový
detail: `Nette\DI\Compiler::processExtensions()` vždy přesune `InjectExtension` na konec pořadí
(`$this->extensions = array_merge(array_diff_key($this->extensions, $last), $last);`), takže libovolná
normálně zaregistrovaná extension (přes `extensions:` v `config.neon`) proběhne dřív než `InjectExtension`.

## Eager stavba služeb v `initialize()` kontejneru spadne na prázdné DB

Cokoliv, co extension vloží do generované metody `initialize()` kontejneru (`afterCompile()`), běží
NEPODMÍNĚNĚ při každém bootu — web i CLI. Typicky služba, která přes závislosti dojde až k `security.user`:
`App\Security\User` (potřebuje `authorizator`) → `App\Security\AuthorizatorFactory::create()`, která OKAMŽITĚ
čte `firecms_roles` (dřív to tak dělala extension dynamických formulářů přes `ContactFormControl` →
`ContactFormFactory` → `App\Modules\CommentsModule\Comment`). Na
prázdné DB (před prvním `migrations:reset`/`continue`) tak spadne i samotný konzolový příkaz na `Table
'firecms_roles' doesn't exist` — nejde namigrovat DB, protože boot kontejneru čte tabulky, které migrace
teprve založí.

Extension, která něco staví v `initialize()`, proto potřebuje guard na `%consoleMode%` (v CLI tělo do
`initialize()` nepřidat). Řetězec závislostí je vidět jen
ve VYGENEROVANÉM kontejneru (`temp/cache/nette.configurator/Container_*.php`), ze zdrojových tříd samotných
zjevný není. Po změně extension je nutné kontejner smazat (viz níže, `bin/console` a produkční režim).

## RobotLoader vs. PSR-4 — neodvozujte cestu souboru z namespace

`bootstrap.php` indexuje třídy tokenizací (`createRobotLoader()`), ne podle Composer psr-4 mapy. Composer
psr-4 (`App\ -> app`, ...) je dodržovaná konvence, ne vynucené pravidlo — RobotLoader porušení nezachytí,
ale Composer autoloader (na kterém stojí `tests/` i PHPStan) třídu mimo odpovídající cestu nenajde. Když
narazíte na nesoulad, `find`/`grep`, nehádejte cestu z namespace. Pro test třídy mimo PSR-4 cestu jde jako
dočasná výpomoc přidat její adresář do `composer.json` → `autoload.classmap` a spustit `composer dump-autoload`,
správné řešení je třídu přesunout.

## `ublaboo/datagrid` → `contributte/datagrid`

Starý kód (a `composer.json` require sekce u pár starších pluginů) odkazuje na `Ublaboo\DataGrid\DataGrid`.
Skutečně nainstalovaný a funkční balíček je přejmenovaný nástupce `Contributte\Datagrid\Datagrid` (třída se
navíc jmenuje `Datagrid`, ne `DataGrid` — jiná velikost písmen!). Nový kód vždy `Contributte\Datagrid\Datagrid`.

## Šablona sloupce datagridu nemá proměnné presenteru

Vlastní šablona sloupce (`$column->setTemplate(...)`) nedostane proměnné, které presenter nastavuje své
šabloně. Typicky chybí `$__imagestore` (proměnná maker `n:src`/`n:image`) a grid spadne na „Undefined variable
$__imagestore“. Předejte ji explicitně: `setTemplate($file, ['__imagestore' => $this->fileManager])`.

## `IList::getList()` — phpDoc-only návratový typ

`App\Model\Database\IList::getList()` deklaruje `@return array|\Nette\Database\Table\Selection` jen v phpDoc, metoda
sama nemá nativní typ. Když implementace přidá nativní typ `: array` (bez union), PHPStan nahlásí
`method.childReturnType`/`return.type` neshodu s rozhraním — i když metoda dělá přesně to, co ostatní
implementace v repozitáři. Řešení: buď žádný nativní typ (a `@return array|\Nette\Database\Table\Selection`
v docblocku implementace, starší styl — viz `Model\Modules`/`Model\Roles`), nebo nativní `: array|Selection`
(nový styl, preferovaný kvůli "Tvrdá pravidla" v `CLAUDE.md`). NEDĚLAT `: array` samotné.

## `Vodacek\Forms\Controls\DateInput::addOwnDate()` vs. nativní `addDate()`

`DateInput::register()` (volané např. v `MenusPresenter::startup()`) registruje extension metodu
`addOwnDate($name, $label, $type)` na `Nette\Forms\Container` — NE `addDate()`. Nette Forms má VLASTNÍ
nativní `addDate($name, $label)` (2 argumenty, ne 3, vrací `DateTimeControl` bez `setNullable()`). Tyhle
dvě metody nejsou zaměnitelné a ani žádná z nich zjevně "vítězí" jako jediná správná — `addOwnDate` se
nikde reálně nevolá (jen se registruje), takže pro nový kód stačí nativní `addDate($name, $label)` bez
třetího argumentu a bez `setNullable()`.

## `Form::Min`/`Max`/`Range` u `addDate()` — `www/vendors/netteForms.min.js` je verze 2.4

Layouty jádra (`app/FrontModule/templates/@layout.latte`, administrace) načítají `www/vendors/netteForms.min.js`
ve verzi 2.4 (2016). Ten validátor `min`/`max`/`range` řeší přes `parseFloat()`, takže u `<input type="date">`
porovná `2026 >= "2026-09-23"` → `false` a **odmítne každé platné datum**, formulář v prohlížeči nejde odeslat.
Server (Nette Forms 3.3) to validuje správně, takže `curl` test projde a chyba je vidět jen v prohlížeči.
U datumových polí proto dávejte `min`/`max` jen jako HTML atribut (`setHtmlAttribute('min', ...)`) a kontrolu
dělejte v `onValidate`. Systémové řešení je aktualizovat netteForms.js v jádru (`www/vendors/`) na verzi
odpovídající Nette Forms 3.x.

## `App\Components\FileManager\Macro\ImageRequest` — konstruktor a dimenze

Konstruktor `__construct(IFile $file, $dimensions = IRequest::ORIGINAL, ...)` má netypovaný parametr s
`int` defaultem (`IRequest::ORIGINAL = 0`), ale property `$dimensions` je typovaná `string` a v šablonách
(`n:src="$file, '200x150'"`) se reálně předávají stringy typu `"200x150"`. PHPStan z toho odvodí, že
parametr má být `int`, a nahlásí chybu při přímém volání `new ImageRequest($file, '200x150')`. Řešení:
volejte přes `ImageRequest::fromMacro($file, ['200x150'])` (statická tovární metoda, stejná jako používají
latte makra interně) — PHPStan si na args-array nestěžuje.

## `addFileUpload()` / `IFile::getHash()` — PHPStan false positive (akceptovaný)

`addFileUpload()` je runtime-registrovaná extension metoda (`Contributte\FileUpload`), `File` interface
nedeklaruje `getHash()` (mají ho jen konkrétní implementace jako `HashImageEntity`). Oboje PHPStan hlásí
jako "undefined method" — je to ale STEJNÝ, už dřív akceptovaný vzor jako v `app/AdminModule/Forms/
FilesManagerUploadFormFactory.php` (viz `app/config/phpstan-baseline.neon`). Nový kód se stejným vzorem
tenhle warning taky dostane a nemusíte to řešit — jen ověřte, že jde opravdu o STEJNÝ vzor (upload file
entity, ne skutečná chyba v typu).

## Úložiště souborů (`FlysystemStorage`) — na co si dát pozor

Podrobně viz `Architecture/file-storage.md`.
- **Nový rozměr obrázku v šabloně = povolit náhled v neonu.** `n:src="$file, '320x240'"` bez
  `fileManager: thumbnails: resize: [320x240]` v debug režimu vyhodí `InvalidThumbnailException`, na produkci
  zobrazí originál a zaloguje warning. Náhledy pluginu patří do jeho `config.plugin.neon`, projektu do
  `theme.neon`. `{crop}`/`n:crop` = seznam `crop:`.
- **Nesahejte na soubory přes `%wwwDir%/files/...`.** Soubor může ležet v S3. Čtení a zápis jen přes
  `FlysystemStorage` (`original()`, `modifyOriginal()`, `getFilesystem()`) nebo přes služby
  `@fileManager.filesystem.<název>`. `getOriginalPath()` vrací klíč v úložišti, ne cestu na disku.
- **Originál upravujte jen přes `FlysystemStorage::modifyOriginal()`**, ne vlastním `Image::save()`. GD zahodí
  EXIF, `modifyOriginal()` ho přenese zpět (s orientací 1 a novými rozměry) a smaže náhledy. Novou velikost pak
  uložte přes `Files::updateSize()`. URL se nemění: prohlížeče drží náhledy z S3 až 1 den.
- **Přenesené EXIF musí mít orientaci 1.** Obrázek je po uploadu fyzicky narovnaný. Kdyby zůstala původní
  orientace (6, 8…), prohlížeč by ho otočil podruhé. Proto `JpegMetadata::forImage()`, ne `applyTo()` rovnou.
- **`publicUrl` u S3 musí obsahovat `prefix`** (`https://cdn/files/` pro `prefix: files`). Flysystem skládá
  URL z cesty bez prefixu. Při přepnutí projektu na S3 `publicUrl` vždy přepište, jinak se zdědí `/files/`
  z `config.neon`.
- **Starší struktury souborů na disku jsou dvě, obě jiné než `<h0>/<h1>/<hash>.<ext>`:**
  `<h01>/<h23>/<hash>.<ext>` (nejstarší data) a `<h0>/<h1>/<původní název>.<ext>` (`HashFileStorage`).
  `FlysystemStorage` je nenajde, převod `bin/console files:migrate`.
- **Změna v `fileManager:` se na webu v produkčním režimu neprojeví bez smazání `temp/cache`** (viz níže).

## `config.local.neon` a přístupové údaje

`../../theme/config/config.local.neon` (lokální DB, SMTP, S3 klíče, `cronToken`) je v `.gitignore`. Přístupové údaje
patří jen sem, nikdy do verzovaného neonu (`config.neon`, `theme.neon`, `config.plugin.neon`). Před plošným
`git add` stejně zkontrolujte `git status` — `.gitignore` nepokrývá jiné lokální konfigurace (serverové neony,
deploy konfigurace), které si projekt může přidat.

## `firecms_modules` a ACL

ACL (`#[Secured]`/`#[Resource]`/`#[Privilege]`) potřebuje odpovídající řádek v `firecms_modules` (viz
`App\Model\Database\Modules`/`Roles`). Moduly jádra zakládá `basic-data`, pluginy své moduly ve vlastní migraci. Nový
`#[Secured]` presenter bez odpovídajícího řádku se chová podle výchozího chování ACL (ověřte konkrétně v
`Acl`/`AuthorizatorFactory`, nespoléhejte na to naslepo v testu/demu). Nový resource je potřeba přiřadit rolím
(Nastavení → Role).

## DI tagované služby se sbírají napříč VŠEMI config soubory, ne jen z "vlastního"

`nextras/migrations`'s `MigrationsExtension` sbírá migrační skupiny přes `$builder->findByTag('nextras.
migrations.group')` — to najde OTAGOVANOU službu bez ohledu na to, ve kterém NEON souboru/extension byla
zaregistrovaná. Díky tomu si každý plugin může zaregistrovat vlastní `Nextras\Migrations\Entities\Group`
službu přímo ve svém `config.plugin.neon` (viz `Architecture/plugins.md`) a nemusí se vůbec zasahovat do
core `app/config/config.neon` — funguje to stejně, jako by ta služba byla deklarovaná přímo v hlavním
configu. Neplatí to univerzálně pro všechny extensions (některé čtou svoji vlastní config sekci, ne tagy),
ale kdykoliv extension dokumentuje "discovery via tag", je bezpečné tag zaregistrovat z libovolného configu
včetně pluginového.

**Klíč skupiny v `migrations: groups:` musí být unikátní napříč VŠEMI `config.plugin.neon`.** NEON sekce
`migrations:` z více souborů se slučují podle klíče, takže dvě skupiny se stejným klíčem se tiše spojí
do jedné a použije se `directory` jen jednoho z nich — migrace druhého pluginu se nikdy nespustí.
Pojmenovávejte skupinu podle pluginu (`stalker`, `statistics`, …).

## Už spuštěnou migraci neupravujte

`nextras/migrations` si u každého spuštěného souboru ukládá kontrolní součet do tabulky `migrations`. Změna
obsahu už spuštěné migrace skončí na „Previously executed migration has been changed“ a v existujících DB
nevznikne sloupec/tabulka, kterou jste do starého souboru dopsali. Změna schématu = nový soubor (`ALTER TABLE`,
`RENAME TABLE`). Přepsat historickou migraci jde jen tehdy, když se všechny DB resetují (`migrations:reset`).

## Migrace jádra jsou dvakrát: MariaDB (`data/migrations/`) a PostgreSQL (`data/migrations-pgsql/`)

Každá nová migrace jádra potřebuje dvojče se STEJNÝM názvem souboru ve stejné skupině v obou adresářích,
jinak se databáze na MariaDB a PostgreSQL rozejdou (který adresář se použije, určuje parametr `migrations`
v `config.local.neon`, viz `Architecture/configuration.md`). PostgreSQL verzi napište ručně podle pravidel převodu tamtéž; nejčastější
chyby: neuvozovkovaný camelCase identifikátor (PostgreSQL ho převede na malá písmena), `0`/`1` do `boolean`
sloupce (chyba typu - pište `false`/`true`), chybějící `setval()` po INSERTu s pevným `id` (další INSERT pak
spadne na duplicitním klíči) a nový index bez předpony tabulky (kolize názvu v rámci schématu). Ověření:
`migrations:reset` proti oběma databázím, počty řádků musí sedět.

Totéž platí pro pluginy, které běží na obou databázích (`data/migrations/mysql/` + `data/migrations/pgsql/`,
`data/deactivate/mysql.sql` + `pgsql.sql`, viz `Architecture/plugins.md`). Plugin jen se staršími MySQL
migracemi (`data/migrations/*.sql` bez `%migrations.driver%`) na PostgreSQL spustí MySQL SQL a migrace spadne.
Pokud plugin `%migrations.driver%` používá a adresář `pgsql/` mu chybí, spadne už načtení skupiny (viz
"Neexistující adresář v neonu jádra shodí čistý klon").

## Ruční SQL pro MariaDB i PostgreSQL: `BaseModel::delimite()`, ne backticky

Nette Explorer v `select()`/`where()`/`order()` sám obaluje identifikátory podle databáze, ale výraz
`tabulka.sloupec` vykládá jako odkaz na navázanou tabulku (zkusí z něj udělat JOIN). Obalený identifikátor
(`` `x` `` i `"x"`) nechá být. Pro poddotazy a `query()` proto identifikátory obalujte přes
`$model->delimite('tabulka.sloupec')` (MariaDB `` `x` ``, PostgreSQL `"x"`, po částech) - backticky na
PostgreSQL neprojdou a camelCase bez uvozovek PostgreSQL převede na malá písmena (`languageId` → `languageid`).

- `Selection::alias()` je jen pro NAVÁZANOU tabulku (řetězec `:book_tag.tag`), hlavní tabulku dotazu
  nepřejmenuje: `alias('firecms_xDescriptions', 'translation')` vyrobí prázdný
  `LEFT JOIN "translation" ON . = "translation".`. Korelovaný poddotaz se proto skládá ručně
  (`TranslatedTitleTrait::getTitleSql()`), `where('x', $idColumn)` by navíc `$idColumn` dosadil jako hodnotu.
- PostgreSQL neurčí typ parametru ve funkci s přetíženými variantami: `CONCAT(..., ?)`, `LOWER(?)` skončí na
  `could not determine data type of parameter`. Skládejte text v PHP (`Service\Tag::withLabels()`), hledaný
  text převeďte na malá písmena v PHP (`LOWER(sloupec) LIKE ?`). `LIKE` je v PostgreSQL citlivý na velikost
  písmen, MariaDB s kolací `_ci` ne.
- Místo `IF(a IS NULL, b, a)`/`IFNULL()` pište `COALESCE()`, místo `IF(podmínka, a, b)` `CASE WHEN ... END`
  (obě databáze), alias sloupce v ručním SQL obalte taky (`AS ' . $model->delimite('categoryTitle')`).
- `SqlLiteral` Nette neobaluje: `new SqlLiteral('viewCount + 1')` hledá v PostgreSQL `viewcount`, `left`/`right`
  (nested set) jsou vyhrazená slova - `new SqlLiteral($model->delimite('viewCount') . ' + 1')`.
- `INSERT IGNORE` → `BaseModel::insertIfNotExists($table, $data, $keyColumns)`, `INSERT ... ON DUPLICATE KEY
  UPDATE` → `BaseModel::updateRowsById()` (existující řádky) nebo kontrola existence a insert/update. Chybu
  duplicity NEzachytávejte - v PostgreSQL nechá probíhající transakci v chybovém stavu („current transaction is
  aborted“) a další dotazy v ní spadnou.
- Příští pozice/maximum přes `Selection::max('position')` (+1 v PHP), ne `SELECT IFNULL(MAX(...),0)+1`.
- PostgreSQL nezná `UPDATE ... ORDER BY` a odmítne `ORDER BY` neagregovaného sloupce vedle `MAX()`/`COUNT()`:
  `update()` ani agregace nevolejte na Selection s `order()` (proto `Categories::findForMenu()` vedle
  `getAllForMenu()`).
- Příznaky jsou v PostgreSQL `boolean`: `where('default', false)`, ne `0`; do `isMain` apod. zapisujte `bool`
  (`count(...) === 0`), ne `IF(COUNT(...)=0, 1, 0)` - MariaDB `bool` uloží jako 1/0, PostgreSQL `0`/`1` odmítne.

## Nová migrace musí mít časové razítko za POSLEDNÍ provedenou migrací ze VŠECH skupin

`nextras/migrations` řadí migrace napříč všemi skupinami (`structures`, `basic-data`, skupiny pluginů, …) podle
názvu souboru. `migrations:continue` odmítne novou migraci, která je starší než nejnovější už provedená, i když je
z jiné skupiny: `New migration "<plugin>-structures/20260930120000.sql" must follow after the latest executed
migration "structures/20261001000000.sql"`. Nový soubor proto pojmenujte aktuálním datem a časem, ne „někam do
dne“. Kontrola: `SELECT file FROM migrations ORDER BY file DESC LIMIT 1`. Stejný název souboru ve dvou různých
skupinách je povolený (jádro má `20261001000000.sql` ve `structures` i `basic-data`). Při ručním porovnávání
souborů s tabulkou `migrations` proto porovnávejte dvojici skupina + soubor, ne jen název.

Pluginy: migrace pluginu musí být novější než výchozí migrace jádra (`20261001…`), proto mají `Stalker` a
`Statistics` soubory `20261002000001.sql`/`20261002000002.sql`. Stejné pravidlo platí i pro opětovné zapnutí
pluginu: vypnutí (`PluginMigrator::deactivate()`) smaže záznamy pluginu z tabulky `migrations` a jeho migrace
jsou pak pro Nextras zase „nové“. Pokud mezitím jádro nebo jiný plugin dostal novější migraci, opětovné zapnutí
spadne na „must follow after the latest executed migration“. Nextras to obejít neumí, řešení je ruční zásah
v DB, nebo nová migrace pluginu s aktuálním datem.

## PK sloupce se jmenují `id`, FK `<entita>Id` — `getColumnId()` vs. `getForeignKeyColumn()`

Konvence schématu viz `Architecture/orm.md`. Na co si dát pozor:

- `BaseModel::getColumnId()` vrací vlastní PK (`id`). Pro dotaz do překladové nebo vazební tabulky
  (`firecms_articleDescriptions`, `firecms_articleTags`, …) použijte `getForeignKeyColumn()` (`articleId`).
  `->where($this->getColumnId(), …)` nad `getTranslationTable()` tiše nic nenajde.
- Joinované selecty typu `getAllWithTranslation()` / `getRelationFile()` (`překlad.*` + `article.*`) mají v řádku
  oba sloupce: `id` (z hlavní tabulky) i `articleId` (z překladu/vazby). Na alias hlavní tabulky se odkazuje
  `"article.id"`, ne `"article.articleId"`.
- Datagrid akce: `array($paramKey => $primaryKey)` s `$paramKey = $model->getForeignKeyColumn()`. Nikdy
  `array('id' => …)` na signálu (`delete!`, `addCategory!`, …) v presenteru, který má vlastní `$id`,
  protože parametr signálu je parametr presenteru a přepsal by ho. `array('id' => $primaryKey)` je v pořádku
  jen u odkazů na jinou akci (`detail`).
- `Settings` má záměrně veřejné snake_case klíče (`main_title`, `seo_title`, …), které převádí na
  camelCase sloupce. Nejde o zapomenutý převod.
- Hlavní tabulky (`firecms_articles`, `firecms_categories`, `firecms_tags`) nemají sloupec s názvem. Název pro
  grid nebo řazení se bere z `*Descriptions` přes
  `TranslatedTitleTrait::selectTitle($selection, "`tabulka`.`id`", $this->language)`, který přidá alias `title`.
  `$language` je jazyk administrace (persistentní parametr presenteru), `null` znamená výchozí jazyk webu.
  Když položka nemá překlad v žádném z nich, vezme se překlad s nejnižším `languageId`. Do gridů nepřidávejte
  `JOIN` na `*Descriptions` s `GROUP BY`, protože MySQL/MariaDB pak vrací náhodný překlad a mění se počítání
  řádků datagridu (použijte korelovaný poddotaz).
- Model s překladovou tabulkou `*Descriptions` implementuje `App\Model\Database\Translatable` (`getTranslationTable()`,
  `getForeignKeyColumn()`) a definuje konstantu `TRANSLATION_TABLE_NAME`. Nový takový model má implementovat
  totéž, a pokud se jeho název zobrazuje v gridu, i `TranslatedTitleTrait`.
- ID ze signálu datagridu (closure `onChange` u `addColumnStatus`) a ze skrytého pole `editId` přichází jako
  **řetězec**. Modely mají `getById(int)`/`update(int, array)` a soubory `strict_types`, takže netypovaný parametr
  handleru předaný dál spadne na `TypeError`. Parametry handlerů typujte (`handleDelete(int $articleId)`), v
  closure přetypujte (`(int) $id`). `BaseFormFactory::setEditId()` číselné ID převádí sám. Hodnoty formuláře
  jsou `ArrayHash`, do `update()` je předávejte jako `(array) $values` — `BaseModel::update(int, array)` se
  záměrně nerozšiřuje, modely v navazujících projektech ho přepisují se stejnou signaturou.
- Raw SQL fragment v `select()`/`where()` Nette Exploreru: identifikátory pište v backtickách
  (`` `t`.`sloupec` ``). Jinak je `SqlBuilder` vykládá jako odkazy na navázané tabulky (`t.sloupec` → hledá
  referenci `t`). Řetězcové literály do fragmentu nevkládejte: `tryDelimite()` obalí backtickami i slova uvnitř
  `'cs'`. Hodnoty předávejte jako parametr `?` (`select($sql, $param)`, `where($sql, ...$params)`).
- Na kategorii/článek/stránku z položek menu nevede cizí klíč (jeden sloupec `target`). Nová cesta mazání
  obsahu musí položky menu uklidit sama (`Menus::deleteItemsByTarget()`), jako `Categories::delete()` a
  `Pages::delete()`.

## Past při převodu snake_case → camelCase

Když funkce měla parametr `$foo_id` a lokální proměnnou `$fooId` s jiným významem, po převodu splynou a lokální
proměnná přepíše parametr. PHPStan to najde jen tehdy, když se liší typy. Před převodem vyhledejte páry
`$foo_bar` + `$fooBar` ve stejné funkci. Raw SQL převádějte kontextově (po `FROM`/`JOIN`/`INTO`/`UPDATE`,
`tabulka.sloupec`, `->table(...)`), ne plošným nahrazením slova — stejná slova bývají i názvy polí formuláře.

## `bin/console` běží v produkčním režimu a změny v `*.neon` nevidí

`app/bootstrap.php` zapíná debug režim jen pro konkrétní cookie a IP (`setDebugMode('…@IP')`). CLI (`bin/console`)
ho proto nikdy nemá a používá produkční kontejner `temp/cache/nette.configurator/Container_*.php` s
`'debugMode' => false`. V produkčním režimu Nette **nekontroluje změny v neon souborech** (`Nette\DI\ContainerLoader
::loadOnce()` vs. `loadCurrent()` podle `debugMode`). Kontejner zkompilovaný jednou zůstává, dokud ho někdo
nesmaže. Web v debug režimu má vlastní kontejner, který se obnovuje sám, takže v prohlížeči všechno funguje a v
CLI ne.

Typický projev: nová skupina migrací v `config.plugin.neon` se při `migrations:reset`/`continue` vůbec nespustí.
Kontrola: `grep -o "Plugins/[A-Za-z]*/data/migrations" temp/cache/nette.configurator/Container_*.php`.

Po každé změně konfigurace (nový plugin, skupina migrací, služba, přepis presenteru v `theme/`) smažte před
spuštěním CLI `temp/cache/nette.configurator/`. Adresář patří `www-data`, takže přes `sudo`. Nepřesouvejte ho
ani nezakládejte pod jiným uživatelem, protože web by do něj pak nemohl zapisovat.

## V produkčním režimu se změněné Latte šablony nepřekompilují

`LatteExtension` volá `setAutoRefresh(%debugMode%)`. Mimo debug režim (web bez debug cookie/IP i `bin/console`)
Latte nekontroluje, jestli se šablona od kompilace změnila, a dál používá zkompilovanou verzi z `temp/cache/latte/`.
Po změně šablony se tak může vykreslovat stará verze, včetně odkazů na smazané soubory nebo sloupce, které
migrace odstranila. Po nasazení změn šablon smažte `temp/cache/latte/` (patří `www-data`, takže přes `sudo`),
stejně jako `temp/cache/nette.configurator/` po změně konfigurace.

## Po resetu DB nebo změně schématu smazat cache struktury Nette Database

Nette Explorer si ukládá strukturu DB (sloupce, primární klíče, cizí klíče) do `temp/_Nette.Database.Structure.*`
a použité sloupce do `temp/_Nette.Database.*`. Po resetu DB s přejmenovanými sloupci se cache sama neobnoví.
Dotazy pak skládají SQL se starými názvy sloupců, přestože v kódu ani v DB takový sloupec není. Po každé změně
schématu smažte `temp/_Nette.Database*` (adresáře patří `www-data`, takže je potřeba `sudo`, nebo je přesuňte
stranou, protože `temp/` je zapisovatelné).

Nově přidaný sloupec čtený přes `$row->sloupec ?? ''` (tj. `ActiveRow::__isset()`) zůstane prázdný, dokud se
cache použitých sloupců nesmaže: `__isset()` chybějící sloupec nedotáhne a dotaz dál vybírá jen dřív použité
sloupce. Na produkci by se to projevilo po každém nasazení s novým sloupcem. Kde se čtou sloupce dynamicky
(mapa klíč → sloupec, `?? ''`), použijte `$row->toArray()` - načte všechny sloupce (viz
`Settings::getAllForLanguage()`). Přímé `$row->sloupec` (`__get()`) sloupec dotáhne samo.

## Testování přes `curl` na sdíleném/multi-tenant boxu

Vývojový box může hostovat víc projektů přes stejný Apache. Souběžná práce jiné session/uživatele na sdílených
souborech (`CLAUDE.md`, `config.neon`, ...) se může projevit jako "systém se chová jinak, než by měl" — než
začnete hledat bug ve vlastní změně, zvažte, jestli něco jiného zrovna needituje stejné soubory. Force-refresh
cache (`temp/cache/*`) mezi testy jen tehdy, když víte, co přesně invaliduje (viz produkční režim výše).

## Cron výraz s krokem v PHPDoc komentáři ukončí komentář

Krokový zápis cron výrazu (hvězdička, lomítko, číslo) obsahuje `*` a `/` za sebou. Uvnitř `/** ... */` komentáře to
PHP vezme jako konec komentáře a zbytek řádku parsuje jako kód: `Parse error: syntax error, unexpected integer "5"`.
Takový výraz nepište do komentářů (jen do řetězců), nebo ho opište slovy.

## Ukázková data (`dummy-data`) jen s parametrem `dummyData`

Skupina migrací `dummy-data` má `enabled: %dummyData%`, ne `%debugMode%` (to by se z `bin/console` nikdy
nespustilo, protože CLI běží v produkčním režimu). V `config.neon` je `dummyData: false`, lokálně ho zapněte v
`config.local.neon`. Po změně parametru smažte `temp/cache/nette.configurator/` (viz výše). Ukázková data
počítají s prázdnou DB (`migrations:reset`) a pevnými id. Sekce zakládají až ukázková data (`basic-data` žádnou
nemá), takže bez `dummyData` je potřeba před prvním článkem nebo kategorií založit sekci v administraci.

## Databáze běží v jiné časové zóně než PHP (`NOW()` ≠ `new \DateTimeImmutable()`)

`bootstrap.php` nastavuje PHP na `Europe/Prague`. Lokální MariaDB běží v UTC (`@@time_zone` = `SYSTEM`,
`@@system_time_zone` = `UTC`) a připojení časovou zónu session nenastavuje. Nette Database posílá `DateTime`
jako lokální čas bez zóny. `NOW()` a výchozí `current_timestamp()` (např. `createDate`, `SqlLiteral('NOW()')`)
proto ukládají čas o 1–2 h jiný než hodnoty z PHP — i `createDate` zobrazený uživateli je posunutý.

Časy, které se zobrazují uživateli nebo porovnávají s časem z PHP (`dateFrom <= ?`), ukládejte z PHP
(`new \DateTimeImmutable()`), ne přes `NOW()`/výchozí hodnotu sloupce. Systémové řešení (nastavit `time_zone`
session DB, např. v `PDO::MYSQL_ATTR_INIT_COMMAND`) zatím chybí. Pojmenované zóny potřebují naplněné
`mysql.time_zone_*` tabulky (lokálně prázdné), pevný offset nezvládne letní čas a časová zóna produkční DB
není ověřená.

## LiveTranslator a session

`LiveTranslator` je Composer balíček `vladahejda/livetranslator` (`^3.0`) z forku
`https://github.com/Fire-man-x/LiveTranslator`, zapojený jako `type: vcs` repozitář v `composer.json`. Pozor:
stejnojmenný starý balíček existuje i na Packagistu (opuštěný, jen `dev-master` z doby Nette 2) — rozsah
`^3.0` ho nevybere, ale volnější rozsah (`*`, `dev-master`) by mohl. Přidáváte-li podobně knihovnu z vlastního
forku, vždy nejdřív zkontrolujte `https://packagist.org/packages/<vendor>/<name>.json`.

Verze 2.x volala `$session->start()` v konstruktoru translatoru, takže session vznikala u každého požadavku.
Verze 3.0 session nepoužívá, nepřeložené řetězce ukládá do `data/localization/<jazyk>.<namespace>.untranslated`
(JSON, volitelně do session nebo DB, viz `app/config/config.neon`). Důsledek: komponenty, které session spouštějí
nebo do ní zapisují až při vykreslování šablony (formulář s `addProtection()` vytvořený v `{control}`), už nemají
session spuštěnou předem. Po prvních 4 kB výstupu pak skončí chybou „Session cannot be started after headers have
already been sent“. Pojistka v jádru zatím není — session potřebnou pro vykreslení spusťte dřív (v presenteru
před renderem), nebo formulář vytvořte v `action*`/`render*`.

Regresní testy knihovny jsou v `tests/LiveTranslator/` (pád na PHP 8.3, `unserialize()` řádku s koncovým `\n`,
úklid `*.untranslated`).

## `Translator::setAvailableLanguages()`: hodnota je pravidlo plurálů, ne popisek

Pole je buď seznam kódů, nebo `kód => pravidlo`. U seznamu kódů (`['cs', 'en']`) dodá pravidla knihovna sama
(`LiveTranslator\PluralRules`, podle gettextu). Explicitní pravidlo
(`'cs' => 'nplurals=3; plural=(n==1) ? 0 : ((n>=2 && n<=4) ? 1 : 2);'`) se vyhodnocuje přes `eval()`, takže vnořené
ternární operátory musí mít závorky (PHP 8). Plurálový je jen text zadaný polem tvarů (`{_['%d den', '%d dny'], $n}`),
jen u něj panel nabízí víc políček. Mapa `['cs' => 'cs', ...]` (popisek místo pravidla) shodí panel i plurálové
překlady na „ParseError: syntax error, unexpected end of file“, verze 3.0 ji odmítne srozumitelnou
`TranslatorException`.

## Oprašování staré knihovny na PHP 8.3 / aktuální Nette

Pasti, na které narazíte u staré vendorované knihovny:
- **Výchozí hodnota property neodpovídající deklarovanému typu je pod `declare(strict_types=1)` tichá bomba** —
  spadne až při prvním přístupu přes getter s návratovým typem, bez ohledu na konfiguraci. Stejně mezivýsledek
  (`realpath()` vrací `false`) přiřazený do typované property vybouchne ještě před kontrolou `false === ...`.
- **`Nette\Application\Application::$presenter` je privátní**, veřejná cesta je `getPresenter()`. Grep na
  `->presenter` (bez `get`) při portování starého Nette kódu.
- **Za jedním opraveným pádem často čeká další, dosud nikdy neprovedený kód.** Neberte jednu opravu jako
  hotovo, dokud neprojde celá cesta, a na každý pád přidejte regresní test.

## Antispam (`addAntiSpam()`) padá při vykreslení formuláře

`Zet\AntiSpam\AntiSpamControl::__construct()` (`app/Components/jzechy/nette-antispam`) volá `monitor(Presenter::class)`
bez callbacku. Současná `nette/component-model` to odmítne výjimkou „At least one handler is required.“, takže každý
formulář s antispamem (kontaktní formulář DynamicForms) spadne už při vytvoření. Oprava: předat callback
(`$this->monitor(Presenter::class, fn(Presenter $p) => $this->validator->setSession($p->getSession()))`) místo
spoléhání na přepsané `attached()`. Neopraveno.

## Testování přes Nette Tester (`composer test`)

`tests/` je samostatný strom mimo `app/` — `composer stan` ho nekontroluje (`app/config/phpstan.neon`
má `paths` jen na `app/`), takže na testy spouštějte `composer stan -- tests` ručně, pokud tam přidáváte
netriviální logiku. PHPStan defaultně analyzuje jen `*.php`, ne `*.phpt` — pomocné třídy pod `tests/Helpers`
kontroluje, samotné scénáře ne, jejich kontrolou je úspěšný běh `composer test`. Autoload testovacích tříd
(`Tests\*`) jde přes `autoload-dev.psr-4` v `composer.json` (`Tests\\` → `tests`) — po přidání nové třídy pod
`tests/` je potřeba `composer dump-autoload`, jinak Tester spadne na "Class not found" (RobotLoader se testů
netýká, ten indexuje jen `%appDir%`).

**Proč integrační testy nad in-memory SQLite, ne mock DB ani reálná MySQL:** `App\Model\Database\BaseModel` je tenký
obal přímo nad `Nette\Database\Explorer` (konkrétní třída, ne interface) — mockovat by šlo jen přes
partial mock frameworku, což je křehké. `tests/Helpers/SqliteDatabase::create()` postaví `Explorer` ručně
(Connection + Structure + MemoryStorage cache + `DiscoveredConventions` jako aplikace), BEZ DI kontejneru, BEZ
`config.local.neon` (tam bývají ostrá přihlašovací data). Na co pamatovat:
- **Fixture data vkládejte přímo přes `Explorer::query('INSERT INTO ...')`, ne přes model** — `BaseModel::insert()`
  prázdného řádku používá `(id) VALUES (DEFAULT)` (MariaDB i PostgreSQL), které SQLite nezná.
- **Cizí klíče v SQLite DDL deklarujte** (`REFERENCES tabulka(id)`), jinak je `Structure` nenajde a zápis přes
  cizí klíč (`page.status`) skončí na „no such table: page“, i když v aplikaci funguje.
- `Nette\Http\Request` pro testy routerů staví `tests/Helpers/RequestFactory.php`. `UrlScript` musí dostat
  explicitní `scriptPath = "/"` (ne prázdný řetězec), jinak `getPathInfo()` vrací vždy prázdný string.
- Čisté unit testy bez DB jdou jen tam, kde třída na `Explorer` nezávisí (`App\Security\Role`, správce souborů
  nad `InMemoryAdapter`). Test proti S3 (`tests/Components/FileManager/S3StorageTest.phpt`) se bez proměnných
  `FILEMANAGER_S3_*` přeskočí.

Chování zdokumentované testy:
- `UrlManager::validateUrl()` hlídá unikátnost URL napříč CELOU tabulkou `firecms_urls`, ignoruje `type` i
  `languageId`.
- `CustomRouter::match()`: segment tvaru `xx/...` se vždy zkusí jako jazykový prefix (viz `Architecture/routing.md`).
- `App\Security\Role`: jednoargumentová varianta konstruktoru (`Nette\Security\User`/`App\Security\Identity`) je
  mrtvý kód — odkazuje na neexistující `App\Security\Identity` a `App\Security\Exception` a spadla by na fatální
  chybu. V repu se volá jen dvouargumentová `new Role($roleName, $userId)`, test pokrývá jen ji. Neopraveno.
