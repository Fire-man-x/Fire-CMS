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

## Příkazy

```
composer install                 # instalace PHP závislostí
composer stan                    # PHPStan (úroveň 5, app/config/phpstan.neon); lze zadat i konkrétní cestu:
composer stan -- app/Model/Articles.php
composer stan9 / composer stan10 # stejná analýza vynucená na úroveň 9 / 10
composer stan-generate-baseline  # znovu vygeneruje app/config/phpstan-baseline.neon
composer stan-report             # tabulkový report zapsaný do log/phpstan-report.txt
composer stan-report-json        # JSON report zapsaný do log/phpstan-report.json
```

- PHPStan baseline je fakticky prázdný (1 řádek), takže spuštění `composer stan` nad celým stromem `app/`
  aktuálně odhalí i již existující chyby nesouvisející s vaší konkrétní změnou (např. v
  `app/Model/Articles.php`). Analýzu proto směřujte jen na soubory/adresáře, kterých se vaše změna skutečně
  týká, a neberte šum z celého repozitáře jako chybu, kterou jste způsobili vy.
- Projekt nemá vlastní automatizovanou sadu testů. `nette/tester` je sice vývojová závislost, ale jediné
  soubory `*Test*.php` v repozitáři leží uvnitř vendorovaných komponent třetích stran (např.
  `app/Components/VisualPaginator-3.0/tests`, `app/Components/mail-panel-master/tests`) a testují tyto
  knihovny, ne tuto aplikaci.
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
`Theme\ -> theme`) se většinou dodržuje jako konvence, ale není závazná: např. vše pod `App\Security\*`
(User, AuthorizatorFactory, Acl, Role, FacebookLogin) fyzicky leží v `app/Components/Security/`, ne v
`app/Security/`. Neodvozujte cestu k souboru třídy jen z jejího namespace.

`libs/nextras/datagrid` je lokálně vendorovaná kopie knihovny pro datagrid, zapojená přes ruční `psr-4`
záznam navíc v `composer.json` (`Nextras\Datagrid\`) — nejde o skutečný Composer balíček, takže ho
nenajdete ve `vendor/` ani v `composer.lock`.

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
`App\Security\User` / `AuthorizatorFactory` / `Acl` / `Role` (fyzicky v `app/Components/Security/`)
sestavují Nette ACL z modelů `Roles` a `Modules` (oprávnění řízená databází). Řízení přístupu na
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
