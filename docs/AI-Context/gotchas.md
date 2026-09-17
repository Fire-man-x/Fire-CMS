# Gotchas

Konkrétní pasti objevené při práci na tomto repozitáři. Číst před laděním něčeho, co "by mělo fungovat".

## Routing: presenter jméno v URL musí být malými písmeny

`Nette\Application\Routers\Route` defaultně vyžaduje `[a-z][a-z0-9.-]*` pro `<presenter>` segment masky a
teprve `path2presenter`/`presenter2path` filtr převádí na/z PascalCase (`dynamic-forms` ↔ `DynamicForms`).
URL jako `/administrace/Sliders/default` (velké S) **nikdy nematchne** — spadne jako "no route", i keď
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
(viz `Architecture/plugins.md`, "product packages" jako `PetHotel`/`SDH*`) proto tahle eager validace
NIKDY nenajde — celá tahle třída bugů se `theme/Plugins/*` balíčků strukturálně netýká. Praktický důsledek:
klientsky/projektově specifickou funkcionalitu, kterou chcete bezpečně zapínat/vypínat, umisťujte do
`theme/Plugins/`, ne do `app/Plugins/`.

**Routing-time gating v `App\Application\PresenterFactory` aktuálně NEEXISTUJE.** Dřív (kvůli původnímu
Sliders bugu) tahle třída měla `PluginRepository`/`isPluginActive()` kontrolu, co aspoň vypnutému pluginu
zajišťovala čisté 404 misto pádu PO úspěšné kompilaci — ověřeno 2026-09-16 čtením aktuálního zdroje, tahle
kontrola tam už není (pravděpodobně vedlejší efekt `git revert` "Plugins Gate" commitu, který ji možná
obsahoval spolu s hlubší opravou, nebo uživatelova vlastního branch resetu — přesná příčina nezjištěna).
Riziko: vypnutý `app/Plugins/*` plugin (např. `Sliders`) může znovu spadnout na `MissingServiceException`
misto čistého 404, pokud RobotLoaderova cache (`temp/cache/nette.application`) jeho presenter najde. Pokud
na tohle narazíte, `isPluginActive()` gating (filtrování `resolveFallback()` kandidátů podle `PluginRepository
::isActive()`) je snadné znovu přidat — jde o samostatnou, lehkou opravu NEZÁVISLOU na těžší
`PluginPresenterGateExtension` níže.

**Zkoušené a zavržené opravy** (viz `Changelog/_index.md`, "Plugins Gate" + revert) — pro `app/Plugins/*`/
`app/Modules/*`, kde se `theme/Plugins/*` obchvat výše nepoužije:
- Omezení `application.scanDirs` na core adresáře / `scanDirs: false` v `config.neon`.
- Vyloučení neaktivních pluginů z hlavního RobotLoaderu (`$robotLoader->excludeDirectory(...)` v
  `bootstrap.php`).
- Vlastní `Nette\DI\CompilerExtension`, která po `ApplicationExtension` a před `InjectExtension`
  odstraní definice presenterů patřících neaktivním pluginům (`removeDefinition()`).

Všechny tři byly funkčně otestovány jako fungující v izolaci, ale POSLEDNÍ z nich byl na žádost uživatele
revertován ("možná to nebudu takto potřebovat používat") — momentálně tedy v repozitáři **žádná oprava
není**. Pokud narazíte na tenhle problém znovu, třetí přístup (vlastní CompilerExtension) je z nich
nejčistší a nejméně rizikový — nedotýká se RobotLoaderu ani `application.scanDirs` (obojí se v testech
ukázalo nečekaně křehké/rizikové v kombinaci s dalšími extensions), operuje čistě na úrovni
`ContainerBuilder` definic. Klíčový detail k zapamatování: `Nette\DI\Compiler::processExtensions()` vždy
přesune `InjectExtension` na konec pořadí (`$this->extensions = array_merge(array_diff_key($this->extensions,
$last), $last);`), takže libovolná normálně zaregistrovaná extension (přes `extensions:` v `config.neon`)
proběhne dřív než `InjectExtension` — netřeba řešit pořadí ručně.

## `DynamicForms` eagerně stavěl ACL při KAŽDÉM bootu kontejneru — spadlo to na prázdné DB

`App\Plugins\DynamicForms\DI\Extension::afterCompile()` vkládal do generovaného `initialize()` metody
kontejneru (běží NEPODMÍNĚNĚ při každém bootu — web i CLI, viz `Architecture/configuration.md`) volání
`setContactFormControl($this->getByType(ContactFormControl::class), $this->getService('application.
application'))`. To eagerly staví celý řetězec: `ContactFormControl` → `ContactFormFactory` →
`App\Modules\CommentsModule\Comment` (constructor arg `security.user`) → `App\Security\User`
(constructor arg `authorizator`) → `App\Security\AuthorizatorFactory::create()`, jejíž tělo OKAMŽITĚ
(ne líně) volá `Roles::getListWithName()` — tj. SELECTuje tabulku `firecms_roles`. Na čerstvé/prázdné DB
(před prvním `bin/console migrations:continue`/`migrations:reset`) tohle spadne na `SQLSTATE[42S02]: Base
table or view not found: 1146 Table 'firecms_roles' doesn't exist` — a to DŘÍV, než konzolový příkaz (nebo
jakýkoliv web request) vůbec dostane šanci cokoliv udělat. Chicken-and-egg past: nejde namigrovat
prázdnou DB, protože bootstrap kontejneru se sám o sobě pokusí přečíst ACL tabulky, o kterých vůbec
neví, že ještě neexistují.

Zjištěno čtením VYGENEROVANÉHO kontejneru (`temp/cache/nette.configurator/Container_*.php`), ne jen
zdrojového kódu — teprve tam je vidět skutečný řetězec `getService()`/`getByType()` napříč
`createService*()` metodami; ze zdrojových tříd samotných to není zjevné (`Nette\Application\Application`
ani `ContactFormControl` samy o sobě `security.user` nepotřebují — schová se to o dvě úrovně hlouběji,
v `CommentsModule\Comment`).

Opraveno guardem na `%consoleMode%` v `Extension::afterCompile()` — v CLI (`$builder->parameters
['consoleMode']`) se tělo do `initialize()` vůbec nepřidá (konzolový skript stejně nikdy nevykresluje
Latte shortcode "contactForm", o nic tak nepřichází). **Po týhle opravě je nutné invalidovat zkompilovaný
kontejner** (smazat `temp/cache/nette.configurator/Container_*.php*`) — v produkčním módu Nette
nekontroluje mtime configu/kódu při každém requestu (viz níže "Testování přes `curl`..."), takže stará
zkompilovaná verze by se použila dál beze změny. Na tomhle sandboxu jsou ty soubory vlastněné `www-data`
a nejde je smazat jako běžný uživatel z tohoto shellu — případně smazat mimo něj.

## RobotLoader vs. PSR-4 — necesta souboru z namespace

`bootstrap.php` indexuje třídy tokenizací (`createRobotLoader()`), ne podle Composer psr-4 mapy. Composer
psr-4 (`App\ -> app`, ...) je dodržovaná konvence, ne vynucené pravidlo. Známý příklad: celé `App\Security\*`
(`User`, `AuthorizatorFactory`, `Acl`, `Role`, `FacebookLogin`) fyzicky leží v `app/Components/Security/`.
Když hledáte třídu, `find`/`grep`, nehádejte cestu z namespace.

## `ublaboo/datagrid` → `contributte/datagrid`

Starý kód (a `composer.json` require sekce u pár starších pluginů) odkazuje na `Ublaboo\DataGrid\DataGrid`.
Skutečně nainstalovaný a funkční balíček je přejmenovaný nástupce `Contributte\Datagrid\Datagrid` (třída se
navíc jmenuje `Datagrid`, ne `DataGrid` — jiná velikost písmen!). Nový kód vždy `Contributte\Datagrid\Datagrid`.

## `IList::getList()` — phpDoc-only návratový typ

`App\Model\IList::getList()` deklaruje `@return array|\Nette\Database\Table\Selection` jen v phpDoc, metoda
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

## `App\Components\FileManager\Macro\ImageRequest` — konstruktor a dimenze

Konstruktor `__construct(IFile $file, $dimensions = IRequest::ORIGINAL, ...)` má netypovaný parametr s
`int` defaultem (`IRequest::ORIGINAL = 0`), ale property `$dimensions` je typovaná `string` a v šablonách
(`n:src="$file, '200x150'"`) se reálně předávají stringy typu `"200x150"`. PHPStan z toho odvodí, že
parametr má být `int`, a nahlásí chybu při přímém volání `new ImageRequest($file, '200x150')`. Řešení:
volejte přes `ImageRequest::fromMacro($file, ['200x150'])` (statická tovární metoda, stejná jako používají
latte makra interně) — PHPStan si na args-array nestěžuje.

## `addFileUpload()` / `IFile::getHash()` — PHPStan false positive (akceptovaný)

`addFileUpload()` je runtime-registrovaná extension metoda (`Zet\FileUpload`), `IFile` interface
nedeklaruje `getHash()` (mají ho jen konkrétní implementace jako `HashImageEntity`). Oboje PHPStan hlásí
jako "undefined method" — je to ale STEJNÝ, už dřív akceptovaný vzor jako v `app/Forms/
FilesManagerUploadFormFactory.php` (viz `app/config/phpstan-baseline.neon`, baseline entries pro tenhle
soubor). Nový kód se stejným vzorem tenhle warning taky dostane a nemusíte to řešit — jen ověřte, že jde
opravdu o STEJNÝ vzor (upload file entity, ne skutečná chyba v typu).

## `config.local.neon` obsahuje credentials, ale není v `.gitignore`

`app/config/config.local.neon` bývá needitovaný gitem prakticky vždy (lokální DB/SMTP), ale skutečně
**není** pokrytý `.gitignore`. Před jakýmkoliv plošným `git add`/commit ověřte `git status`, jestli tam
tenhle soubor nefiguruje.

## `firecms_modules` DB tabulka bývá nenaseedovaná

ACL (`#[Secured]`/`#[Resource]`/`#[Privilege]`) potřebuje odpovídající řádek v `firecms_modules` tabulce (viz
`App\Model\Modules`/`Roles`). Čerstvý checkout (a i tento repozitář historicky) tuhle tabulku prázdnou —
nový `#[Secured]` presenter je syntakticky správně, ale bez seed dat se chová podle výchozího chování ACL
(ověřte konkrétně v `Acl`/`AuthorizatorFactory`, nespoléhejte na to naslepo v testu/demu).

## DI tagované služby se sbírají napříč VŠEMI config soubory, ne jen z "vlastního"

`nextras/migrations`'s `MigrationsExtension` sbírá migrační skupiny přes `$builder->findByTag('nextras.
migrations.group')` — to najde OTAGOVANOU službu bez ohledu na to, ve kterém NEON souboru/extension byla
zaregistrovaná. Díky tomu si každý plugin může zaregistrovat vlastní `Nextras\Migrations\Entities\Group`
službu přímo ve svém `config.plugin.neon` (viz `Architecture/plugins.md`) a nemusí se vůbec zasahovat do
core `app/config/config.neon` — funguje to stejně, jako by ta služba byla deklarovaná přímo v hlavním
configu. Neplatí to univerzálně pro všechny extensions (některé čtou svoji vlastní config sekci, ne tagy),
ale kdykoliv extension dokumentuje "discovery via tag", je bezpečné tag zaregistrovat z libovolného configu
včetně pluginového.

## Testování přes `curl` na sdíleném/multi-tenant boxu

Tenhle vývojový box hostuje víc projektů (fire-cms, pet-hotel, další klientské projekty) přes stejný
Apache. Souběžná práce jiné session/uživatele na sdílených souborech (`CLAUDE.md`, `config.neon`, ...) se
může projevit jako "systém se chová jinak, než by měl" — než začnete hledat bug ve vlastní změně, zvažte,
jestli něco jiného zrovna needituje stejné soubory. Force-refresh cache (`temp/cache/*`) mezi testy jen
tehdy, když víte, co přesně invaliduje — v produkčním (ne debug) módu Nette container cache nekontroluje
mtime configu při každém requestu (`Nette\DI\ContainerLoader::loadOnce()` vs. `loadCurrent()` podle
`debugMode`), takže změna configu se nemusí projevit hned.

## DB tabulky mají prefix `firecms_` (jádro) / `firecms_plugin_` (plugin) a název za prefixem je camelCase

Od 2026-09-17 mají všechny tabulky Fire CMS jádra prefix `firecms_` a název za prefixem v camelCase
(`firecms_articles`, `firecms_categories`, `firecms_users`, `firecms_menuItems`, `firecms_articleMetas`,
`firecms_roleModule`, ...) a všechny tabulky pluginů (`app/Plugins/*` i `theme/Plugins/*`) prefix
`firecms_plugin_` stejným způsobem (`firecms_plugin_sliders`, `firecms_plugin_statistics`,
`firecms_plugin_stalkers`, `firecms_plugin_dynamicForms`, `firecms_plugin_dynamicFormDescriptions`,
`firecms_plugin_properties`/`customers`/`pets`/`petFiles`/`facilities`/`reservations` u `PetHotel`). Prefix
se řídí tím, KDO tabulku vlastní, ne tím, ve kterém souboru je CREATE TABLE fyzicky napsaná —
`firecms_plugin_sliders`/`stalkers`/`statistics` jsou definované přímo v
`data/migrations/structures/20161115000000.sql` (core migrace), protože ty pluginy existovaly už při
založení repa, ale patří `app/Plugins/Sliders`/`Stalker`/`Statistics`, takže mají plugin prefix, ne core.
Jen samotný prefix (`firecms_`/`firecms_plugin_`) zůstává s podtržítkem — camelCase se týká pouze části za
ním.

Historické CREATE TABLE migrace (core `data/migrations/structures|basic-data/*.sql`,
`app/Plugins/DynamicForms/data/migrations/20171115000000.sql`) byly přepsány přímo na finální prefixované
názvy — bezpečné jen proto, že v době přejmenování nebyly ještě nikde nasazené (`nextras/migrations`
hlídá checksum souboru přes tabulku `migrations` a při změně obsahu už spuštěné migrace tvrdě spadne s
"Previously executed migration has been changed"). Pro libovolnou DALŠÍ core/DynamicForms migraci, která
by se objevila AŽ PO nasazení na produkci, se historické `CREATE TABLE` soubory nesmí editovat — přejmenování
by muselo jít přes novou migraci s `RENAME TABLE`.

**SDH pluginy (`SDHAttendance`/`SDHCalendar`/`SDHTowns`/`SDHEvents`/`SDHTests`) jsou z tohoto přejmenování
VĚDOMĚ VYNECHANÉ.** Jejich modely jedou přes samostatnou DB connection `@database.databaseSdh.context`
(`app/config/config.local.neon` → `database.databaseSdh`, samostatná databáze `sdh`, fyzicky oddělená od
`fire-cms`) a nemají v tomto repu žádnou migraci, která by jejich ~50 tabulek (`actions`, `who`, `leagues`,
`towns`, `attendance`, `tests`, ...) vytvářela — jsou to zjevně legacy tabulky existující v produkční `sdh`
databázi odjakživa, se stovkami ručně psaných JOIN/UPDATE/INSERT výskytů napříč pluginy bez jediného testu.
Přejmenování téhle části je samostatný navazující úkol (potřebuje zálohu DB a možnost to ověřit před
nasazením na živý klubový web) — dokud neproběhne, `SDH*` pluginy zůstávají BEZ prefixu. `SDHGallery` do DB
vůbec nesahá (souborové úložiště), takže se ho přejmenování netýká.

Interní bookkeeping tabulka `migrations` (vytváří ji `nextras/migrations`, eviduje spuštěné soubory) je
záměrně BEZ prefixu — je to infrastruktura knihovny, ne obsahová data aplikace (`PluginMigrator::
deactivate()` na ni má natvrdo napsaný raw SQL dotaz, přejmenování by vyžadovalo patchnout i to).
