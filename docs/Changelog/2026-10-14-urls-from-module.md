# 2026-10-14 — URL a přesměrování přesunuté z `app/Modules/UrlModule` do standardní struktury

**Co:** Správa hezkých URL a přesměrování už není samostatný modul. Modely jsou `App\Model\Database\Urls`
(dřív `App\Modules\UrlModule\Model`) a `App\Model\Database\Redirections` (dřív `RedirectionsModel`), služba
`App\Model\UrlManager`, formuláře `App\AdminModule\Forms\UrlFormFactory` a `RedirectionFormFactory`, presenter
`App\AdminModule\SettingsModule\Presenters\UrlsPresenter` se šablonami v `SettingsModule/Presenters/templates/Urls/`.
Presenter (`:Admin:Settings:Urls`) i URL v administraci se nemění.

**Proč:** URL jsou součást jádra jako ostatní obsah, samostatný modul nedával smysl.

**Dotčené soubory/oblasti:**
- `app/config/config.neon` — služby s novými názvy tříd. Ze `setScanDirs()` u `PresenterFactory` odstraněný
  adresář `%appDir%/Modules` (v jádře už není; komentáře se přesunuly také).
- `app/AdminModule/Forms/UrlFormFactory.php`, `RedirectionFormFactory.php` — dědí z `AdminFormFactory` (pole v `data`,
  tlačítko v `buttons`, typy `AdminForm`/`AdminFormValues`). Formulář URL měl pole zkopírovaná z formuláře jazyka
  (`active`, `default`, `name`, `shortcut`), která tabulka `firecms_urls` nemá, a uložení vždy spadlo. Teď upravuje jen
  `url` existujícího záznamu (přidání v šabloně není, URL vzniká s obsahem): ořízne okrajové mezery a lomítka
  a odmítne URL, kterou už má jiný záznam (unikátnost napříč jazyky jako `UrlManager::validateUrl()`).
- `app/AdminModule/templates/Categories/default.latte` — `{varType App\Model\UrlManager $urlManager}`.
- `app/AdminModule/SettingsModule/Presenters/templates/Urls/*.latte` — bez explicitního `{layout}`, layout administrace
  se najde stejně jako u ostatních šablon nastavení.
- `app/Application/PresenterFactory.php` — příklady v doc komentáři.
- `tests/Model/UrlManagerLookups.phpt`, `UrlManagerValidateUrl.phpt` (dřív `tests/Modules/UrlModule/`, namespace
  `Tests\Model`) a `tests/Router/CustomRouterTest.phpt`, `tests/Model/PagesTest.phpt` — nové názvy tříd.
- `composer.json` (popis `composer test`), `CLAUDE.md`, `docs/Architecture/{routing,orm,plugins,presenters}.md`,
  `docs/Modules/{admin,front}.md`, `docs/AI-Context/patterns.md`.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano (viz výše)
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? `patterns.md` ano
- DB migrace potřeba? ne
- Klientské projekty: kód v `theme/Plugins/`, který používá `App\Modules\UrlModule\*`, je potřeba po mergi jádra převést.
