# 2026-10-09 — Šablony komponent z tématu (`TemplateLookupTrait`), zatím u Menu

**Co:** Nový `App\Components\TemplateLookupTrait\TemplateLookupTrait` hledá šablonu komponenty po vzoru
`Presenter::formatTemplateFiles()`: `theme/FrontModule/Components/<Komponenta>/<view>.latte` projektu, pak adresář
komponenty. Zatím ho používá `Menu`: výchozí šablonu jde v projektu přepsat (`theme/FrontModule/Components/Menu/Menu.latte`)
a `{control menu:<view> <location>}` vykreslí menu vlastní šablonou `<view>.latte`. Parametry i proměnné šablony
jsou stejné jako u `{control menu <location>}`.

**Proč:** Šablonu menu nešlo předat. `render()` volá `customTemplate()` bez parametru, takže šablonu nastavenou
zvenku vždy vrátí na výchozí. Šablona tématu z `FrontModule\BasePresenter::createComponentMenu()` se proto
neuplatní. Projekt (Pet hotel) potřeboval jiné vykreslení jednoho menu (řádek výhod na homepage).

**Dotčené soubory/oblasti:**
- `app/Components/TemplateLookupTrait/TemplateLookupTrait.php` — `formatTemplateFiles(?view)` (kandidáti v pořadí
  priority, název jen `[\w-]`, zkouší se i `lcfirst()`), `findTemplateFile(?view)` (první existující, jinak
  `Nette\FileNotFoundException`). Komponenta implementuje `getThemeDir()`. Šablony jádra se hledají u třídy, která
  trait používá, i když se vykresluje její potomek.
- `app/Components/Menu/Menu.php` — používá trait. `__call()` obslouží `render<View>()` (Latte ho volá pro
  `{control menu:<view>}`, název s pomlčkou dynamicky), vykreslení přesunuté do `printMenu()`, `customTemplate()`
  je bez účinku (deprecated). Nová závislost `App\Service\ProjectFolders` v konstruktoru (DI ji doplní).
- `docs/Architecture/components.md` — popis vyhledávání šablony a `{control menu:<view> …}`.

**Rozhodnutí a kompromisy:**
- Trait místo úpravy `App\Components\BaseControl`: z něj dědí jen `Slider` a `ContactFormControl` a jeho konstruktor
  vyžaduje `FileManager`. Komponenty se šablonou (`Menu`, `BreadCrumb`, `LanguageChanger`, …) dědí přímo z `Control`
  a trait si připojí bez změny rodiče a konstruktoru. Další komponenty se na něj převedou postupně.
- Šablona podle názvu v `{control}`, ne podle umístění menu: jedno menu jde na různých místech vykreslit jinak.
- `www/theme/<téma>/templates/Menu.latte` (nastavuje ho `createComponentMenu()` přes `customTemplate()`) se dál
  neuplatní, trait ho mezi kandidáty nemá. Témata `kotercovi`, `sdh` a `sss` ten soubor mají a nikdy se nepoužíval,
  jeho zapnutím by se jim změnilo vykreslení menu. `BreadCrumb`, `CategoriesMenu`, `FilesManagerMenu`, `Comments`,
  `Articles` mají stále původní `customTemplate()`, které `render()` přepíše.
- Latte název bez pomlčky předá s velkým prvním písmenem (`menu:uspInfo` → `renderUspInfo`), proto se hledá
  i `lcfirst()` varianta.

**Návaznost:**
- Update `docs/Architecture/*.md`? Ano, `components.md`.
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? Ne.
- DB migrace potřeba? Ne.
