# Přehled architektury

Fire CMS je interní CMS jádro/produkt Small Street Studio na Nette Frameworku. Klientské projekty (Pet
Hotel, SDH, SSS, ...) vznikají naklonováním tohoto jádra a zůstávají s ním propojené přes git remote
`fire-cms`, díky čemuž lze do nich zpětně mergovat opravy jádra (viz `doc/update-project-with-gitlab-deploy.md`).
Proto se `app/Modules/**` a vše mimo `app/Plugins/**` bere jako jádro — úpravy tam se mají promítnout do
všech klientských projektů, klientsky specifické chování patří do `app/Plugins/`.

## Bootstrap a vrstvení konfigurace

Jediný front controller je `www/index.php`, který zavolá `app/bootstrap.php`. Ten postupně sestaví Nette DI
kontejner z:

1. `app/config/config.neon` — sdílená/verzovaná základní konfigurace (services, priority routování,
   extensions).
2. `app/config/config.local.neon` — přepisy specifické pro vývojáře (DB DSN, mailer, debug flagy). Není
   sledovaný gitem, ale **není** skutečně pokrytý `.gitignore` — bývají tam lokální přihlašovací údaje, před
   plošným `git add` ověřte, co se přidává.
3. `theme/config/plugins.neon` — includuje `config.plugin.neon` každého aktivního pluginu.
4. `theme/config/theme.neon` — zapojení specifické pro téma/web (vzniká přejmenováním `*.theme.neon.dist`
   podle `doc/product-packages.md`).

Třídy se načítají přes Nette **RobotLoader**, ne striktní PSR-4 sken Composeru — `bootstrap.php` volá
`createRobotLoader()` nad celým `app/` a indexuje třídy tokenizací souborů nezávisle na jejich fyzické
cestě. Composerí `psr-4` mapa (`App\ -> app`, `App\Plugins\ -> app/Plugins`+`theme/Plugins`, `Theme\ ->
theme`) se většinou dodržuje jako konvence, ale RobotLoader ji nevynucuje — např. celé `App\Security\*`
fyzicky leží v `app/Components/Security/`. Necesta souboru z namespace se tedy nedá spolehlivě odvodit,
vždy je potřeba dohledat skutečné umístění (`find`/grep), ne hádat podle jmenného prostoru.

Nový kód (viz `data/migrations/`, nové pluginy) by se měl PSR-4 mapě/RobotLoaderu držet i tak — jde jen o
to, že historický kód tuto disciplínu nemá a nebude se kvůli tomu retroaktivně přesouvat.

## Vysokoúrovňový pohled

```
www/index.php
  → app/bootstrap.php  (RobotLoader + Nette DI Container)
    → Nette\Application\Application::run()
      → Router (viz routing.md)   — najde presenter + akci
        → PresenterFactory (viz plugins.md) — instancuje presenter
          → BasePresenter::checkRequirements()  — ACL (#[Secured]/#[Resource]/#[Privilege])
            → action*/render*/handle* — typicky načte Model, sestaví Datagrid/Form komponentu
              → *.latte šablona
```

Vrstva Model nemá ORM — je to tenký obal nad `Nette\Database\Explorer` (viz `orm.md`). Presentery v
administraci obvykle kombinují datagrid (výpis + akce) s modálním formulářem pro add/edit (viz
`presenters.md`).

## Příkazy

```
composer install                 # instalace PHP závislostí
composer stan                    # PHPStan level 5, app/config/phpstan.neon; lze zadat cestu:
composer stan -- app/Model/Articles.php
composer stan9 / composer stan10 # stejná analýza vynucená na level 9 / 10 (spouštět na NOVÝ/měněný kód)
composer stan-generate-baseline  # znovu vygeneruje app/config/phpstan-baseline.neon
composer stan-report             # tabulkový report do log/phpstan-report.txt
composer stan-report-json        # JSON report do log/phpstan-report.json
```

Projekt nemá vlastní automatizovanou sadu testů (`nette/tester` je závislost, ale jediné `*Test*.php`
soubory jsou uvnitř vendorovaných komponent třetích stran a testují je, ne aplikaci). Chybí jakékoliv
JS/CSS build nástroje — frontendové assety pod `www/` se servírují tak, jak jsou. Aplikace běží na Apache +
mod_rewrite (`www/.htaccess`); v repozitáři není příkaz pro PHP built-in server ani CLI runtime mimo
Composer skripty výše.

## Kde hledat dál

- [Vrstva Model](orm.md)
- [Komponenty](components.md)
- [Presentery](presenters.md)
- [Modules vs. Plugins](plugins.md)
- [Routing](routing.md)
- [Konfigurace](configuration.md)
