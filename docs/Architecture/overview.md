# Přehled architektury

Fire CMS je interní CMS jádro/produkt Small Street Studio na Nette Frameworku. Klientské projekty vznikají
naklonováním tohoto jádra a zůstávají s ním propojené přes git remote `fire-cms`, díky čemuž lze do nich
zpětně mergovat opravy jádra (viz `doc/update-project.md`).
Proto se `app/Modules/**` a vše mimo `app/Plugins/**` bere jako jádro — úpravy tam se mají promítnout do
všech klientských projektů, klientsky specifické chování patří do `app/Plugins/`.

## Bootstrap a vrstvení konfigurace

Jediný front controller je `www/index.php`, který zavolá `app/bootstrap.php`. Ten postupně sestaví Nette DI
kontejner z:

1. `app/config/config.neon` — sdílená/verzovaná základní konfigurace (services, priority routování,
   extensions).
2. `../../theme/config/config.local.neon` — přepisy specifické pro prostředí (DB DSN, mailer, debug flagy, přístupové
   údaje). Je v `.gitignore` a do gitu nepatří.
3. `theme/config/plugins.neon` — includuje `config.plugin.neon` každého aktivního pluginu.
4. `theme/config/theme.neon` — zapojení specifické pro téma/web (vzniká přejmenováním `*.theme.neon.dist`
   podle `doc/product-packages.md`).

Třídy se načítají přes Nette **RobotLoader**, ne striktní PSR-4 sken Composeru — `bootstrap.php` volá
`createRobotLoader()` nad celým `app/` a indexuje třídy tokenizací souborů nezávisle na jejich fyzické
cestě. Composerí `psr-4` mapa (`App\ -> app`, `App\Plugins\ -> app/Plugins`+`theme/Plugins`, `Theme\ ->
theme`) se dodržuje jako konvence, ale RobotLoader ji nevynucuje, takže třída fyzicky mimo cestu
odpovídající jejímu namespace za běhu aplikace funguje. Cestu souboru se tedy nedá spolehlivě odvodit jen
z namespace, vždy je potřeba dohledat skutečné umístění (`find`/grep) — Composer autoloader (na kterém stojí
`tests/` i PHPStan, na rozdíl od RobotLoaderu za běhu appky) na takový nesoulad narazí a třídu nenajde.

Nový kód (viz `data/migrations/`, nové pluginy) by se měl PSR-4 mapě/RobotLoaderu držet i tak.

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
composer stan -- app/Model/Database/Articles.php
composer stan9 / composer stan10 # stejná analýza vynucená na level 9 / 10 (spouštět na NOVÝ/měněný kód)
composer stan-generate-baseline  # znovu vygeneruje app/config/phpstan-baseline.neon
composer stan-report             # tabulkový report do log/phpstan-report.txt
composer stan-report-json        # JSON report do log/phpstan-report.json
```

`composer test` spouští Nette Tester nad `tests/` (viz `AI-Context/gotchas.md` sekce "Testování přes Nette
Tester"). Většinou jde o integrační testy nad in-memory SQLite Explorerem sestaveným ručně (bez DI
kontejneru) — vhodné pro `Model`/business-logic třídy nezávislé na presenterech. Pokryté jsou vybrané části
(`UrlManager`, `CustomRouter`, menu, stránky, jazyky, překlady, správce souborů), ne celé `app/`. Chybí jakékoliv JS/CSS build nástroje —
frontendové assety pod `www/` se servírují tak, jak jsou. Aplikace běží na Apache + mod_rewrite
(`www/.htaccess`); v repozitáři není příkaz pro PHP built-in server ani CLI runtime mimo Composer skripty
výše.

## Kde hledat dál

- [Vrstva Model](orm.md)
- [Komponenty](components.md)
- [Presentery](presenters.md)
- [Modules vs. Plugins](plugins.md)
- [Routing](routing.md)
- [Konfigurace](configuration.md)
- [Cron](cron.md)
- [Sessions](sessions.md)
- [Úložiště souborů](file-storage.md)
