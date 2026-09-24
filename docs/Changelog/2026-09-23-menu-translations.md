# 2026-09-23 — Překlady menu (`firecms_menuDescriptions`), odstraněný `firecms_menus.name`

**Co:** Menu má přeložitelný nadpis `title` pro každý jazyk. Ve formuláři menu je pole „Titulek (CS/EN/…)“
pro každý jazyk, ve výchozím jazyce povinné. Komponenta `Menu` ho předává šabloně jako `$menuTitle`.
Sloupec `firecms_menus.name` je odstraněný: název menu v administraci je nadpis ve výchozím jazyce
(`Menus::getDisplayName()`, v gridu přes `TranslatedTitleTrait`), bez nadpisu se zobrazí `location`.

**Proč:** Na žádost zadavatele, navazuje na
[2026-09-23-menu-items-separated.md](2026-09-23-menu-items-separated.md). Typické použití: nadpisy sloupců
odkazů v patičce („Pro klienty“, „Pro hotely“) spravované z administrace a přeložené.

**Dotčené soubory/oblasti (jádro):**
- `data/migrations/structures/20260923200000.sql` — tabulka `firecms_menuDescriptions` (`menuId`, `languageId`,
  `title`). Zadavatel ji přesunul ze samostatné `20260923210000.sql` do migrace položek menu.
- `data/migrations/structures/20161115000000.sql` — z `CREATE TABLE firecms_menus` odebrán `name` (úprava
  zadavatele, viz Rozhodnutí).
- `app/Components/Menu/Model/Menus.php`:
  - `implements Translatable` + `TranslatedTitleTrait`;
  - `getTitle()`, `getTitles()`, `saveTitles()`, `getDisplayName()`;
  - konstruktor má nový parametr `LanguageService`.
- `app/AdminModule/Presenters/MenusPresenter.php`, `templates/Menus/detail.latte` — název menu z nadpisu
  místo `name`.
- `app/Forms/MenuFormFactory.php`:
  - bez pole `name`, kontejner `titles` s polem pro každý jazyk;
  - záložní `location` se počítá z nadpisu ve výchozím jazyce (dřív z neexistujícího `$values->title`).
- `app/Components/Menu/Menu.php`, `Menu.latte` — proměnná `$menuTitle`.
- `data/localization/cs.admin`, `tests/Model/MenusItemsTest.phpt`.

**Rozhodnutí a kompromisy:**
- **`name` odstraněn úpravou původní migrace `20161115000000.sql`, ne novou migrací.** Rozhodnutí zadavatele
  (doporučená byla nová migrace, která zkopíruje `name` do `title` a sloupec smaže). Důsledky:
  - funguje jen pro **nové instalace**;
  - v každém existujícím projektu (včetně forků po mergi jádra) nahlásí `migrations:continue` změněný
    kontrolní součet `20161115000000.sql` („Previously executed migration … has been changed“);
  - sloupec `name` v existujících DB zůstane jako `NOT NULL` bez výchozí hodnoty, takže vytvoření menu z
    administrace spadne na „Field 'name' doesn't have a default value“. V každém existujícím projektu je
    potřeba ručně: zkopírovat `name` do `firecms_menuDescriptions.title` výchozího jazyka, `ALTER TABLE
    firecms_menus DROP COLUMN name` a opravit kontrolní součet záznamu migrace (nebo DB resetovat).
  - **Platí i pro tento checkout:** v DB je `name` stále, kontrolní součty `20161115000000.sql` a
    `20260923200000.sql` se neshodují se záznamy v tabulce `migrations`.
- **Výchozí `Menu.latte` nadpis nevykresluje**, aby se nezměnil vzhled stávajících webů. Použijte ho ve
  vlastní šabloně menu.
- **Popisek „Title“ má v jádře překlad „Titulek“** (`data/localization/cs.admin`), a ten se použije.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `components.md` (Menu).
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ano — `firecms_menuDescriptions` je v `20260923200000.sql`. U existujících DB je navíc
  nutný ruční krok kvůli `name` (viz Rozhodnutí).
