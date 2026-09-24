# 2026-09-23 — Položky menu oddělené od kategorií (`linkType` + `target`)

**Co:** `firecms_menuItems` už není vazba menu → kategorie, ale samostatná položka s typem odkazu
`category` | `article` | `url` | `route`, cílem v jednom sloupci `target`, rodičem (`parentId`), aktivitou,
„otevřít v novém okně“ a přeložitelným popiskem (`firecms_menuItemDescriptions`). Administrace menu má
formulář položky místo výběru ze stromu kategorií.

**Proč:** Na žádost zadavatele, první krok k tomu, aby kategorie byly čistě kategorie (bez typů existujících
jen kvůli menu: `url`, `categoryLink`, `homepage`). Menu teď umí odkázat na článek, URL/kotvu i presenter
pluginu (např. `:Front:Properties:default`).

**Dotčené soubory/oblasti (jádro, dopadá na všechny projekty):**
- `data/migrations/structures/20260923200000.sql` — nová `firecms_menuItems` + `firecms_menuItemDescriptions`.
  Stávající položky se převedou na `linkType = category`, pořadí zůstává. Otestováno na kopii DB.
- `app/Components/Menu/Model/`:
  - `MenuLinkType` (enum), `MenuItem` (typovaný řádek);
  - `Menus` — nové metody `getItemsTree`, `getActiveItemsByLocation`, `insertItem`, `updateItem`, `deleteItem`,
    `moveItem`, `deleteItemsByTarget`, `getItemLabels`, `getSubtreeIds`. Staré `*RelationMenuItem*`,
    `getAllMenuItemsWithTranslation`, `updatePositionOfMenuItem` a `getRelationMenu` jsou smazané.
- `app/Components/Menu/Menu.php` + `Menu.latte` — skládá menu z položek. Proměnné šablony (`categories`,
  `activeCategory`) a klíče položek (`categoryId`, `type`, `title`, `link`, `childs`) zůstávají kvůli vlastním
  šablonám v projektech. Nové klíče `id` a `newWindow`.
- `app/Forms/MenuItemFormFactory.php` (nový, registrovaný v `config.neon`).
- `app/AdminModule/Presenters/MenusPresenter.php` + `templates/Menus/detail.latte`:
  - grid položek, modal formulář, jazykové záložky pro popisky;
  - smazané handlery `addCategory!`, `removeCategory!`, `setCategoryAsMain!` a modal se stromem kategorií.
- `app/Model/Categories.php` — `delete()` uklízí položky menu odkazující na kategorii (dřív cizí klíč CASCADE).
  Konstruktor má nový parametr `Menus`.
- `app/config/phpstan-baseline.neon` — odstraněno 16 záznamů pro smazaný kód.
- `data/localization/cs.admin` — překlady nových textů.
- `tests/Model/MenusItemsTest.phpt`.

**Rozhodnutí a kompromisy:**
- **Jeden sloupec `target`** (rozhodnutí zadavatele) místo `targetId`/`url`/`route`. Na kategorii/článek proto
  nevede cizí klíč, úklid dělá aplikace (viz výše). Stejný vzor je potřeba i u dalších cest mazání, pokud
  vzniknou (hromadné mazání kategorií mimo `Categories::delete()`).
- **Položka kategorie dál sama vykreslí podkategorie** (`showInMenu`) za svými vlastními podpoložkami.
  Zachovává to chování stávajících webů. Kategorie se `showInMenu = 0` se v menu neukáže ani jako položka.
- **Řazení v administraci jen mezi sourozenci** (drag & drop). Datagrid neumí řadit strom, rodič se mění
  ve formuláři.
- **Typy kategorií `url`, `categoryLink`, `homepage` zatím zůstávají.** Jejich převod na položky menu je krok 2.
- **Odkaz typu route** dostane `locale` automaticky, pokud míří na `:Front:` nebo je relativní.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `components.md` (Menu).
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ano — `data/migrations/structures/20260923200000.sql` (bez ní padá každá stránka s
  `{control menu}` i administrace menu)
