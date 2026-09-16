# Gotchas

Konkrétní pasti objevené při práci na tomto repozitáři. Číst před laděním něčeho, co "by mělo fungovat".

## Routing: presenter jméno v URL musí být malými písmeny

`Nette\Application\Routers\Route` defaultně vyžaduje `[a-z][a-z0-9.-]*` pro `<presenter>` segment masky a
teprve `path2presenter`/`presenter2path` filtr převádí na/z PascalCase (`dynamic-forms` ↔ `DynamicForms`).
URL jako `/administrace/Sliders/default` (velké S) **nikdy nematchne** — spadne jako "no route", i keď
presenter `Sliders` reálně existuje a je aktivní. Při ručním testování přes `curl` vždy použijte lowercase
(případně s pomlčkami), ne název třídy 1:1. Tohle se dá snadno splést s daleko vážnějším problémem (viz
další bod) — než začnete hledat bug v kódu, ověřte URL casing.

## Vypnutý plugin a kompilace kontejneru

Když je plugin vypnutý v `theme/config/plugins.neon`, jeho třída presenteru pořád fyzicky existuje na
disku. `Nette\Bridges\ApplicationDI\ApplicationExtension` při KOMPILACI kontejneru (ne za běhu) skenuje
`app/` a ověřuje, že `@inject` vlastnosti všech nalezených presenterů jdou naautowirovat — o
`theme/config/plugins.neon` nic neví. Výsledek: `Nette\DI\MissingServiceException` na presenteru
vypnutého pluginu **shodí kompilaci CELÉHO kontejneru**, ne jen stránku toho pluginu — jakákoliv jiná
stránka administrace i frontendu přestane fungovat.

`App\Application\PresenterFactory` (routing-time gating, viz `Architecture/plugins.md`) tohle NEŘEŠÍ —
ten běží až PO úspěšné kompilaci kontejneru.

**Zkoušené a zavržené opravy** (viz `Changelog/_index.md`, "Plugins Gate" + revert):
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

## `modules` DB tabulka bývá nenaseedovaná

ACL (`#[Secured]`/`#[Resource]`/`#[Privilege]`) potřebuje odpovídající řádek v `modules` tabulce (viz
`App\Model\Modules`/`Roles`). Čerstvý checkout (a i tento repozitář historicky) tuhle tabulku prázdnou —
nový `#[Secured]` presenter je syntakticky správně, ale bez seed dat se chová podle výchozího chování ACL
(ověřte konkrétně v `Acl`/`AuthorizatorFactory`, nespoléhejte na to naslepo v testu/demu).

## Testování přes `curl` na sdíleném/multi-tenant boxu

Tenhle vývojový box hostuje víc projektů (fire-cms, pet-hotel, další klientské projekty) přes stejný
Apache. Souběžná práce jiné session/uživatele na sdílených souborech (`CLAUDE.md`, `config.neon`, ...) se
může projevit jako "systém se chová jinak, než by měl" — než začnete hledat bug ve vlastní změně, zvažte,
jestli něco jiného zrovna needituje stejné soubory. Force-refresh cache (`temp/cache/*`) mezi testy jen
tehdy, když víte, co přesně invaliduje — v produkčním (ne debug) módu Nette container cache nekontroluje
mtime configu při každém requestu (`Nette\DI\ContainerLoader::loadOnce()` vs. `loadCurrent()` podle
`debugMode`), takže změna configu se nemusí projevit hned.
