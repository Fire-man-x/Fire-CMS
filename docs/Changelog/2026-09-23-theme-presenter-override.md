# 2026-09-23 — Přepis presenterů z `app/` třídou v `theme/`

**Co:** Presenter v `theme/` se stejnou relativní cestou a názvem jako presenter v `app/` má přednost.
Namespace je `Theme\` místo `App\`. Příklad: `theme/FrontModule/Presenters/SignPresenter.php`
(`Theme\FrontModule\Presenters\SignPresenter`) se použije místo `App\FrontModule\Presenters\SignPresenter`.
Přepis typicky z původní třídy dědí a mění jen část chování. Šablony, které přepis nemá, se berou
z původního presenteru v `app/`.

**Proč:** Na žádost zadavatele. Klientský projekt má mít možnost upravit presenter jádra bez zásahu do
`app/` (viz konvence v `CLAUDE.md`), a přitom dál mergovat aktualizace jádra.

**Dotčené soubory/oblasti:**
- `app/Application/PresenterFactory.php`:
  - nová `themeOverride()`: k třídě `App\X`, kterou vrátí mapování, `setMap()` nebo fallback, se použije
    `Theme\X`, pokud existuje (načítá ji Composer psr-4 `Theme\ → theme`);
  - platí pro Front, Admin i presentery modulů.
- `app/config/config.neon` — nový blok `search: themePresenters`:
  - presentery pod `theme/` (kromě `theme/Plugins`) se registrují jako DI služby s tagem `nette.inject`.
    Bez toho by `PresenterFactoryCallback` skončil na „No services of type …“;
  - `theme/Plugins` je vynechané, protože presentery theme pluginů registruje `search:` blok v
    `config.plugin.neon` daného pluginu, jen když je plugin zapnutý.
- `app/Presenters/BasePresenter.php` — `formatTemplateFiles()` / `formatLayoutTemplateFiles()`:
  - na konec seznamu přidávají šablony z adresáře neabstraktního předka, který presenter z `Theme\`
    přepisuje;
  - Nette samo hledá jen vedle souboru konkrétní třídy, takže by přepis musel kopírovat všechny šablony.
    Pořadí zůstává: nejdřív `theme/…/templates`, potom `www/theme`, nakonec `app/…/templates`.

**Rozhodnutí a kompromisy:**
- Zkoušeli jsme i `application: scanDirs: [%rootDir%/theme]`. Vlastní seznam ale nahradí výchozí
  `%appDir%` (nesloučí se), takže pak zmizí všechny presentery z `app/`. Kromě toho by registroval
  i presentery vypnutých theme pluginů. Proto `search:` s `exclude: files: [Plugins]`.
- Presentery pluginů z `app/Plugins/X` se takhle přepsat nedají. `Theme\Plugins\X…` by ležel v
  `theme/Plugins`, který se neskenuje. Třídu by šlo najít, ale nebyla by DI služba.
- Obecný přepis libovolné třídy (služby, formuláře, komponenty) tímhle mechanismem řešený není. Služby se
  dají přepsat v `theme/config/theme.neon` (`services:`), třídy vytvářené přes `new` se přepsat nedají.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `presenters.md`, sekce „Přepis presenteru z theme/“
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne (popsáno v Architecture)
- DB migrace potřeba? ne
