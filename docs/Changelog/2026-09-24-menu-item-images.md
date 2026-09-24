# 2026-09-24 — Obrázky u položek menu

**Co:** Položky menu mají obrázky ze správce souborů. V administraci (detail menu, grid položek) je sloupec
„Obrázky“: náhledy, přidání ze správce souborů, odebrání a řazení přetažením. Hlavní obrázek je první.
Webové menu je předává šabloně jako `$category->image` (hlavní) a `$category->files` (všechny).

**Proč:** Na žádost zadavatele. Kontrola ukázala, že položky menu obrázky neměly: `firecms_menuItems` bez vazby
na soubory, žádné UI.

**Dotčené soubory/oblasti (jádro):**
- `data/migrations/structures/20260924170000.sql` — `firecms_menuItemFiles` (`menuItemId`, `fileId`, `position`),
  FK CASCADE na položku i soubor.
- `app/Components/Menu/Model/Menus.php` — `getItemFiles()` (hromadně pro více položek), `addItemFiles()`,
  `removeItemFile()`, `sortItemFiles()`.
- `app/AdminModule/Presenters/MenusPresenter.php`:
  - sloupec `files` se šablonou `templates/Menus/itemImages.latte`;
  - handlery `addItemImages!`, `removeItemImage!`, `sortItemImages!` (s kontrolou, že položka patří menu).
- `app/AdminModule/templates/Menus/detail.latte` — modal `#filesModal` se správcem souborů, JS řazení obrázků
  (znovu po AJAX překreslení gridu).
- `www/administration/js/main.js` — odkaz otevírající správce souborů může nést vlastní
  `data-selected-files-url` (má přednost před globálním `window.selectedFilesFromIframeUrl`). Potřeba pro
  obrázky jedné z mnoha položek na stránce, stávající stránky se nemění.
- `app/Components/Menu/Menu.php` — klíče `files` a `image` u položek, `Menu.latte` (komentář s příkladem).
- `tests/Model/MenusItemsTest.phpt` — pořadí, duplicity, smazání s položkou.

**Rozhodnutí a kompromisy:**
- **Výchozí `Menu.latte` obrázky nevykresluje**, aby se nezměnil vzhled stávajících webů. Použijte je ve vlastní
  šabloně menu, např. `<img n:if="$category->image" n:src="$category->image, '64x64'">`.
- **Obrázky se spravují v gridu, ne ve formuláři položky.** Formulář je v modalu a správce souborů je také modal,
  vnořené modaly Bootstrap nepodporuje.
- **Automatické podkategorie** (podkategorie položky typu kategorie) obrázky nemají, nejsou to položky menu.

**Ověřeno v Chromiu (2026-09-24, přes MCP):**
- detail menu → tlačítko obrázku u položky „Domů“ → správce souborů v modalu → nahrání testovacího obrázku →
  výběr → „Vložit do příspěvku“ → vazba uložená, náhled v gridu → odebrání křížkem (AJAX překreslení);
- homepage s obrázkem u položky vrací 200;
- 12 stránek administrace (sekce, články a kategorie po sekcích, stránky, menu, jazyky) 200 bez chyby;
- testovací obrázek potom smazaný (záznam i soubory).
- Řazení obrázků přetažením v prohlížeči ověřené není (s jedním obrázkem nejde), pokrývá ho `MenusItemsTest`.

Test odhalil dvě chyby, opravené:
- **Šablona sloupce gridu neměla `$__imagestore`** (proměnná makra `n:src`, šablony presenteru ji dostávají v
  `startup()`). Grid s obrázkem padal na „Undefined variable $__imagestore“. Předává se v
  `setTemplate(..., ['__imagestore' => $this->fileManager])`.
- **Prohlížeč držel starý `main.js` z cache** (servíroval se bez verze), úprava JS se neprojevila.
  `@layout.latte` administrace má u `main.js` a `style.css` `?v={filemtime(...)}`. Po každé změně souboru se
  stáhne nová verze, platí pro všechny budoucí úpravy JS/CSS administrace.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `components.md` (Menu)
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ano — `structures/20260924170000.sql` (bez ní padá každá stránka s `{control menu}`)
