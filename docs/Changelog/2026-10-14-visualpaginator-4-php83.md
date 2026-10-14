# 2026-10-14 — VisualPaginator 4.0.2 pro PHP 8.3, Nette 3.3 a Latte 3

**Co:** Vendorovaná knihovna `libs/VisualPaginator-4.0.2` (`AlesWita\VisualPaginator`, autoload přes `psr-4`
v `composer.json` projektu) běží na PHP 8.3, nette/application 3.3, nette/utils 4, nette/http 3.4 a Latte 3
bez zastaralých API. Opravené jsou dvě chyby v práci s počtem položek na stránku.

**Proč:** Knihovna byla psaná pro PHP 7.4, Latte 2 a nette/http 3.1 (ArrayAccess na `SessionSection`,
`{php}` v šablonách, `@persistent` anotace).

**Dotčené soubory/oblasti:**
- `src/VisualPaginator.php` — `#[Persistent]` místo `@persistent`, `SessionSection::get()/set()/remove()` místo
  `ArrayAccess` (deprecated v nette/http 3.2+), `Arrays::invoke()` místo `Helpers::callEvents()`, `getPresenter()`
  místo `$this->presenter`, typovaná konstanta, `#[\Override]`, `readonly` vlastnosti.
  - Oprava: hodnota v session při `canSetItemsPerPage = false` dřív v `loadState()` vyhodila výjimku z
    `setItemsPerPage()`. Teď se ignoruje a ze session odstraní.
  - Oprava: `setItemsPerPage()` přenastaví i vnitřní `Paginator`, takže změna počtu položek přes AJAX (bez
    přesměrování) platí hned v téže odpovědi.
  - Výchozí hodnota selectu se nastavuje při vytvoření formuláře, ne vynuceným vytvořením formuláře v `loadState()`.
- `setDefaultItemsPerPage(int)` (nové) — pevný počet položek na stránku bez výběru a bez session, náhrada za
  `setItemsPerPage()` z verze 3.0. `setItemsPerPage()` ve 4.0.2 vyžaduje `$canSetItemsPerPage` (zobrazí na stránce
  formulář s výběrem) a ukládá volbu do session. Volba uložená v session má přednost před výchozí hodnotou.
- Komponenty `Articles`, `Categories`, `SearchControl` (`app/FrontModule/Components/`) a `Comments`
  (`app/Modules/CommentsModule/Components/`) převedené z `AlesWita\Components\VisualPaginator` (3.0, smazaný) na 4.0.2:
  `VisualPaginatorFactory` v konstruktoru (promoted `readonly`), `->setDefaultItemsPerPage(10)` jako dřív bez výběru
  počtu. Z baseline odstraněné záznamy pro neexistující třídu 3.0.
- `src/templates/bootstrap5.latte` (nová) — stránkování a výběr počtu položek pro Bootstrap 5 (`form-select`,
  `input-group` bez `-prepend/-append`). Konstanty `VisualPaginator::TEMPLATE_NORMAL|BOOTSTRAP4|BOOTSTRAP5` a parametr
  `templateFile` u `VisualPaginatorFactory`. V `app/config/config.neon` je globálně `TEMPLATE_BOOTSTRAP5` (jako dřív
  `TEMPLATE_BOOTSTRAP_V4` přes extension verze 3.0) — bez něj by se použila holá šablona `normal.latte`.
- `src/VisualPaginatorTemplate.php` (dřív `Template.php`) — dědí z `Nette\Bridges\ApplicationLatte\Template`,
  Nette ji pro komponentu najde samo podle jména. Vlastní šablona: `{templateType AlesWita\VisualPaginator\VisualPaginatorTemplate}`.
- `src/VisualPaginatorFactory.php` — `readonly` třída, překlad zpráv přetypovaný na `string`
  (`Translator::translate()` vrací `string|Stringable`).
- `src/Helpers.php` — odstraněné `callEvents()` (nahrazeno `Arrays::invoke()`).
- `src/templates/*.latte` — Latte 3: `{var}` místo `{php}`, `getNextValue()`, gettery `Paginator`, řetězce v `n:class`.
  Vykreslené HTML se nemění.
- `tests/` — `Nette\Bootstrap\Configurator`, presenter s constructor injection, testy obou oprav. `composer.json`,
  `phpstan.neon` a `.github/workflows/build.yml` knihovny pro PHP 8.3, PHPStan 2 a Tester 2.5+.

**Rozhodnutí a kompromisy:**
Veřejné API komponenty (`setItemsPerPage()`, `setItemCount()`, `getOffset()`, `$canSetItemsPerPage`, `$ajax`,
`$templateFile`, `$onPaginate`) zůstává, přibylo `setDefaultItemsPerPage()`. Mění se jen název třídy šablony pro
`{templateType}`. Proti 3.0 se liší výstup šablony `normal` (`<span>` místo `<em>` u šipek a výpustky) a výběr kroků
(okolí ±2 stránky + první a poslední, ve 3.0 ±3 a 5 záchytných bodů).

Ověření: testy knihovny se dají pustit proti `vendor/` projektu (kopie knihovny se symlinkem `vendor` →
`../../vendor`, `vendor/bin/tester -C tests`), PHPStan na úrovni max nad `src` a `tests` bez chyb.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne
