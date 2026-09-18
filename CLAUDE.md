# CLAUDE.md

Tento soubor poskytuje nástroji Claude Code (claude.ai/code) informace pro práci s kódem v tomto repozitáři.

## O tomto repozitáři

Fire CMS je interní CMS jádro/produkt společnosti Small Street Studio postavený na frameworku Nette (PHP). Jednotlivé
klientské projekty (např. Pet Hotel, SDH, SSS — vidět jako git remotes v tomto checkoutu) vznikají
naklonováním tohoto jádra a zůstávají s ním propojené přes git remote `fire-cms`, díky čemuž lze do nich
později mergovat aktualizace/opravy jádra, a naopak. Přesný postup merge/rebase najdete v
`doc/update-project-with-gitlab-deploy.md`, nastavení automatického nasazení přes GitLab CI/CD pak v
`doc/gitlab-deploy.md` (oba dokumenty jsou v češtině).

Kvůli tomuto forkovému vztahu berte `app/Modules/**` a vše mimo `app/Plugins/**` jako jádro: neupravujte to
zlehka tak, jak byste to dělali v běžné jednoúčelové aplikaci — změny na těchto místech se mají promítnout
do všech navazujících klientských projektů. Klientsky specifické chování patří do balíčku pod
`app/Plugins/` (viz Konvence níže).

## Jazyk
Komentáře, dokumentace, vysvětlení → **česky**.

## Tvrdá pravidla
- `declare(strict_types=1);` v každém PHP souboru
- PHP 8.3 features (constructor promotion, readonly, enums, match)
- PHPStan: **level 5 přes `app/config/phpstan.neon` je aktuálně vynucovaná brána** (viz Příkazy níže) —
  baseline (`app/config/phpstan-baseline.neon`) má ale skoro 2000 řádků nasbíraného dluhu, takže level 5
  fakticky neznamená "bez chyb", jen "bez NOVÝCH chyb nad rámec baseline". `composer stan9` / `composer
  stan10` existují jako přísnější kontrola nad konkrétním souborem/adresářem, kterou spouštějte na nový kód
  (viz Příkazy) — nejde o vynucenou CI bránu, level 10 je dlouhodobý cíl, ne aktuální stav repozitáře.
- Explicitní typy, žádný `mixed` bez důvodu — nový kód piš tak, aby procházel `composer stan9`/`stan10`
- Nový kód: constructor injection (promoted properties) místo `/** @inject */` veřejných vlastností.
  Výjimka: staré presentery (napsané před timto pravidlem — Sliders, Menus, Articles apod.) `/** @inject */`
  pořád používají a nepřepisujeme je zpětně jen kvůli stylu; nový presenter pište pomocí constructor
  injection, i když sourozenecké presentery v okolí vypadají jinak.
- Latte šablony: `{varType Type $var}` na začátku pro každou proměnnou, kterou presenter do šablony posílá
  (existující šablony to typicky nemají — dopisujte to jen do nových/měněných šablon)

## Příkazy

```
composer install                 # instalace PHP závislostí
composer stan                    # PHPStan (úroveň 5, app/config/phpstan.neon); lze zadat i konkrétní cestu:
composer stan -- app/Model/Articles.php
composer stan9 / composer stan10 # stejná analýza vynucená na úroveň 9 / 10
composer stan-generate-baseline  # znovu vygeneruje app/config/phpstan-baseline.neon
composer stan-report             # tabulkový report zapsaný do log/phpstan-report.txt
composer stan-report-json        # JSON report zapsaný do log/phpstan-report.json
composer test                    # Nette Tester nad tests/; lze zadat i konkrétní cestu:
composer test -- tests/Modules/UrlModule
```

- PHPStan baseline je fakticky prázdný (1 řádek), takže spuštění `composer stan` nad celým stromem `app/`
  aktuálně odhalí i již existující chyby nesouvisející s vaší konkrétní změnou (např. v
  `app/Model/Articles.php`). Analýzu proto směřujte jen na soubory/adresáře, kterých se vaše změna skutečně
  týká, a neberte šum z celého repozitáře jako chybu, kterou jste způsobili vy.
- Od 2026-09-18 existuje `tests/` (Nette Tester, spouští se přes `composer test`) — integrační testy nad
  in-memory SQLite `Nette\Database\Explorer` sestaveným ručně bez DI kontejneru
  (`tests/Helpers/SqliteDatabase.php`), bez závislosti na `config.local.neon`. Pokrytí je zatím pilotní
  (`UrlManager::validateUrl`), ne kompletní regresní sada — `app/Model/BaseModel` je přímo svázaný s
  `Explorer` (konkrétní třída, ne interface), takže čisté unit testy bez DB jsou v jádru vzácné a smysluplné
  testy jsou většinou integrační. `BaseModel::insert()` navíc používá MySQL-specifické
  `SELECT LAST_INSERT_ID()`, které SQLite nezná — testovací fixture data vkládejte přímo přes
  `Explorer::query()`, ne přes model. Nové PHP soubory pod `tests/` vyžadují `composer dump-autoload`
  (`autoload-dev.psr-4: Tests\\ → tests`). `composer stan` tenhle strom nekontroluje (`paths` jen na
  `app/`) — nový test kód proto ověřte ručně přes `composer stan -- tests`. Podrobnosti a další pasti viz
  `docs/AI-Context/gotchas.md` sekce "Testování přes Nette Tester".
- Vendorované komponenty třetích stran mají vlastní testy nezávislé na tomhle (např.
  `app/Components/VisualPaginator-3.0/tests`, `app/Components/mail-panel-master/tests`) — testují tyto
  knihovny, ne aplikaci, a `composer test` je nespouští.
- Chybí jakékoliv JS/CSS build nástroje (žádný `package.json`) — frontendové assety pod `www/` se servírují
  tak, jak jsou.
- Aplikace je klasický cíl pro Apache + mod_rewrite (`www/.htaccess` přesměrovává vše kromě statických
  souborových přípon na `www/index.php`); v repozitáři není žádný příkaz pro spuštění přes PHP built-in
  server ani CLI.

## Architektura

### Bootstrap a vrstvení konfigurace
Jediný front controller `www/index.php` → `app/bootstrap.php`, který postupně sestaví Nette DI kontejner z:
1. `app/config/config.neon` — sdílená/verzovaná základní konfigurace (services, priority routování,
   extensions).
2. `app/config/config.local.neon` — přepisy specifické pro jednotlivého vývojáře (DB DSN, mailer, debug
   flagy). Tento soubor není v gitu sledovaný, ale **není** ve skutečnosti pokrytý `.gitignore` — před
   plošným `git add` si to raději ověřte, protože zde bývají uložené lokální přihlašovací údaje k DB a SMTP.
3. `theme/config/plugins.neon` — sdružuje `config.plugin.neon` každého nainstalovaného pluginu (viz
   `app/Plugins/*/config.plugin.neon`).
4. `theme/config/theme.neon` — zapojení specifické pro téma/web, vytváří se dle `doc/product-packages.md`
   přejmenováním souboru `*.theme.neon.dist` daného balíčku.

Načítání tříd zajišťuje Nette **RobotLoader**, ne striktní PSR-4 sken Composeru — `bootstrap.php` volá
`createRobotLoader()` nad celým adresářem app a indexuje třídy tokenizací souborů nezávisle na jejich
fyzické cestě. Composerí `psr-4` mapa (`App\ -> app`, `App\Plugins\ -> app/Plugins`+`theme/Plugins`,
`Theme\ -> theme`) se většinou dodržuje jako konvence, ale RobotLoader ji nevynucuje, takže se v repu
příležitostně objeví třída fyzicky mimo cestu odpovídající jejímu namespace (do 2026-09-17 tomu tak bylo
u `App\Security\*`, viz `docs/Changelog/2026-09-18-security-app-security-move.md` — od přesunu do
`app/Security/` už PSR-4 odpovídá). Neodvozujte cestu k souboru třídy jen z jejího namespace, vždy
dohledejte skutečné umístění (`find`/grep) — RobotLoader ho stejně najde napříč `app/` bez ohledu na to,
jestli je Composer autoloader (a s ním nástroje jako `tests/` nebo PHPStan, které na RobotLoaderu nestojí)
zrovna v souladu.

`libs/nextras/datagrid` je lokálně vendorovaná kopie knihovny pro datagrid, zapojená přes ruční `psr-4`
záznam navíc v `composer.json` (`Nextras\Datagrid\`) — nejde o skutečný Composer balíček, takže ho
nenajdete ve `vendor/` ani v `composer.lock`. `libs/` není součástí PHPStan `scanDirectories`/`paths`
(`app/config/phpstan.neon`), takže `composer stan` kód pod `libs/` vůbec nekontroluje — spouštějte
`composer stan -- libs/<balíček>` ručně, pokud tam děláte netriviální změnu.

`LiveTranslator` (vícejazyčnost, viz sekce "Obsahový model" níže) byl do 2026-09-18 stejným způsobem
ručně vendorovaný v `libs/LiveTranslator`, ale od téhož dne je to skutečný Composer balíček
`vladahejda/livetranslator`, natažený jako `type: vcs` repozitář z
`https://github.com/Fire-man-x/LiveTranslator` (fork s opravami pro PHP 8.3/aktuální Nette — viz
`docs/Changelog/2026-09-18-livetranslator-php83-nette-compat.md` a
`docs/Changelog/2026-09-18-livetranslator-composer-package.md`). Žije tedy normálně ve `vendor/` a je
pokrytý `composer stan` i `composer update` jako každý jiný balíček. **Pozor:** `vladahejda/livetranslator`
existuje i na Packagistu (starý, opuštěný balíček z roku 2013, jen `dev-master` bez PHP 8/Nette 3
podpory) — proto je v `composer.json` napevno zamčená přesná verze `"2.0"`, ne rozsah — s volným
rozsahem by `composer update` mohl sáhnout po tom starém.

### Routování a hledání presenterů
V `app/Router/` jsou tři poskytovatelé routerů, automaticky registrovaní přes Nette DI extension `search`
(cokoliv implementující `App\Router\RouterProvider` nalezené pod `%appDir%/Router`), seřazení podle mapy
`router.priorities` v `config.neon`:
- `AdminRouter` — `/administrace/<presenter>/<action>[/<id>]`, modul `Admin`.
- `FrontRouter` — deleguje na `CustomRouter`, který zjišťuje hezká URL proti databázově podloženému
  `UrlModule\UrlManager` (nejde o statickou route masku); jako záložní variantu používá
  `[<locale>/]<presenter>/<action>[/<id>]`, modul `Front`.
- `FileRouter` — nižší priorita, pro URL souborů/assetů.

Výchozí hledání třídy presenteru je `App\*Module\Presenters\*Presenter`, ale `application.presenterFactory`
je v `config.neon` přepsaná na `App\Application\PresenterFactory` (`app/Application/PresenterFactory.php`),
která — pokud výchozí mapování třídu nenajde — jako záložní variantu prohledá `app/Plugins`, `app/Modules` a
`theme/Plugins` na soubory `*Presenter.php`. Díky tomu můžou presentery ležet uvnitř stromů pluginů/modulů
pod vlastním namespace, např. `App\Plugins\Sliders\AdminModule\Presenters\SlidersPresenter` nebo
`App\Modules\CommentsModule\AdminModule\CommentsPresenter`. Pokud je krátký název presenteru nejednoznačný
napříč více pluginy/moduly, rozlišuje se porovnáním prostředního segmentu názvu presenteru s názvem složky
pluginu/modulu — než budete tuto logiku měnit, přečtěte si podrobnosti v doc komentáři třídy.

### Dva mechanismy rozšíření — Modules vs. Plugins
- `app/Modules/<Name>Module/` — funkční oblasti dodávané jako součást samotného jádra (např. `UrlModule`,
  `CommentsModule`). Každá obvykle má vlastní `Model/`, `Forms/`, `Components/` a podstrom `AdminModule/`
  (občas i `FrontModule/`) s presentery/šablonami.
- `app/Plugins/<Name>/` — schválený způsob, jak přidat volitelnou/klientsky specifickou funkcionalitu bez
  úpravy souborů jádra (viz Konvence níže); zapojuje se přes vlastní `config.plugin.neon`, který je
  includován z `theme/config/plugins.neon`.

Obě podoby prohledává `PresenterFactory` stejným způsobem. Vlastní `getPlugin($name)` /
`createComponent($name)` presenteru (v `app/Presenters/BasePresenter.php`) navíc umí natáhnout
služby/komponenty, které pluginy registrují pod DI tagy `presenter.plugin` / `presenter.component`, řešené
přes `App\DI\IPluginServiceLocator` / `IPluginComponentLocator` — takto se plugin naveze do presenteru
jádra, aniž by presenter znal konkrétní třídu pluginu.

### Autorizace (ACL)
`App\Security\User` / `AuthorizatorFactory` / `Acl` / `Role` (fyzicky v `app/Security/`) sestavují
Nette ACL z modelů `Roles` a `Modules` (oprávnění řízená databází). Řízení přístupu na
presenterech/akcích v administraci se deklaruje PHP atributy z `app/Attributes/`: `#[Secured]` +
`#[Resource('...')]` + `#[Privilege('...')]`. Vynucují se v
`App\AdminModule\Presenters\BasePresenter::checkRequirements()`, která přes reflexi přečte atributy na
třídě presenteru nebo na metodě action/render/handle a zavolá `$user->isAllowed($resource, $privilege)` —
buď přesměruje na `:Admin:Sign:in`, nebo při `ForbiddenRequestException` zobrazí flash zprávu a přesměruje.
Administrace používá pro session samostatný auth namespace (`'admin'`), odlišný od frontendu, nastavený v
téže `checkRequirements()`.

### Vrstva Model
Modely dědí z `App\Model\BaseModel` (tenký obal nad `Nette\Database\Explorer` — název tabulky + primární
klíč + základní CRUD, bez ORM). Modely pro výpisy běžně implementují `App\Model\IDatagridSource`, díky čemuž
mohou presentery administrace naplnit `ublaboo/datagrid` přímo z `Nette\Database\Table\Selection`.

### Obsahový model
Základní entity: Articles (články), Categories (kategorie) se zásuvnými „subtypy" přes
`app/Forms/CategorySubtype/*FormPart` — Default/Homepage/CategoryLink/Url/Gallery — implementujícími
`ICategoryFormType`, Files/FileFolders (správce souborů v administraci), Menus (menu), Tags (štítky), Metas
(SEO meta na úrovni článku/kategorie), Users/Roles (uživatelé/role), Languages (vícejazyčnost přes
`LiveTranslator` plus vlastní model `Languages`/`LanguageService`), `UrlModule` (vlastní hezká URL +
přesměrování, se kterými pracuje `CustomRouter`), `CommentsModule` (komentáře).

## Konvence (`doc/conventions.md`)
- Plugin/balíček vždy staví na Fire CMS a **nikdy** nesmí přímo upravovat zdrojové soubory jádra (např.
  neupravujte `HomepagePresenter.php` nebo `default.latte` z jádra — chování přepište z pluginu).
- Výchozí/ukázková data do DB pro balíček patří do `data/migrations/<název-balíčku>/`.
- Vlastní frontendové assety balíčku (CSS/LESS/obrázky) patří do `www/frontend/<název-balíčku>/`.
- Výchozí šablony balíčku patří pod `app/` (resp. do vlastního stromu balíčku), pokud nejde o
  sdílenou/přepisovatelnou šablonu — v takovém případě patří pod `theme/`.
- Pojmenování: `Interface` bez prefixu/postfixu; abstraktní třídy bez prefixu/postfixu (prefix `Base` jen
  pokud je to nutné); `Trait` ve vlastní složce s postfixem `Trait`; Nette factories vždy s postfixem
  `Factory`.

## Formátování
Odsazení je tabulátory, ne mezery (`.editorconfig`); kódování UTF-8, konce řádků LF.

## Po dokončení změny (dokumentace)
Znalostní báze pro vývojáře i AI je v `docs/` (viz `docs/README.md` pro navigaci). Po významnější změně:
1. Changelog entry: `docs/Changelog/YYYY-MM-DD-popis.md` podle `docs/Changelog/_template.md`
2. Update `docs/Changelog/_index.md`
3. Pokud změna architektury → update příslušný soubor v `docs/Architecture/`
4. Pokud nový pattern/gotcha, na který by AI mělo v budoucnu narazit → update `docs/AI-Context/gotchas.md`
   nebo `docs/AI-Context/patterns.md`

`docs/` je oddělené od `doc/` (bez "s") zmíněného výše v tomto souboru — `doc/` obsahuje provozní
návody (merge/rebase jádra, GitLab CI/CD nasazení, konvence balíčků), `docs/` je znalostní báze
architektury pro AI asistenty a nové vývojáře.