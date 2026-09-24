# 2026-09-24 — Stránky: obrázky a vnořování; popisky položek menu pro všechny jazyky

**Co:**
- **Stránky mají obrázky** ze správce souborů (přidání v modalu, řazení přetažením, odebrání). Hlavní obrázek
  je první v pořadí.
- **Stránky jdou vnořovat** (nadřazená stránka ve formuláři, strom v administraci s řazením mezi sourozenci,
  drobečková navigace a výpis podstránek na webu).
- **Formulář položky menu** má pole popisku pro každý jazyk najednou (dřív jen pro jazyk zvolený záložkou).

**Proč:** Na žádost zadavatele. Navazuje na [2026-09-24-pages.md](2026-09-24-pages.md) a
[2026-09-23-menu-items-separated.md](2026-09-23-menu-items-separated.md).

**Dotčené soubory/oblasti (jádro):**
- `data/migrations/structures/20260924120000.sql` — `firecms_pages.parentId` (FK `SET NULL`), `position`,
  tabulka `firecms_pageFiles`.
- `app/Model/Pages.php`:
  - `insertPage`, `updatePage` (nový rodič = konec sourozenců, kontrola cyklu), `movePage`, `getTree`,
    `getSubtreeIds`, `getPublishedParents`, `findPublishedChildren`;
  - obrázky: `getFiles`, `addFiles`, `removeFile`, `sortFiles`;
  - `delete()` přesune podstránky o úroveň výš;
  - `getDatagridSource()` nahrazen `getTree()`.
- `app/Forms/PageFormFactory.php` — výběr nadřazené stránky (bez stránky samotné a jejích podstránek).
- `app/AdminModule/Presenters/PagesPresenter.php` + `templates/Pages/`:
  - grid ve stromovém pořadí s přetahováním (`sort!`);
  - handlery `addImages!`, `removeImage!`, `sortImages!`.
- `app/FrontModule/Presenters/PagesPresenter.php`, `app/FrontModule/templates/Pages/detail.latte`,
  `theme/FrontModule/templates/Pages/detail.latte` — drobečková navigace přes nadřazené stránky, hlavní obrázek,
  galerie ostatních obrázků, výpis podstránek.
- Menu:
  - `Menus::insertItem($menuId, $data, $labels)` a `updateItem($itemId, $data, ?$labels)` berou popisky pro
    všechny jazyky (`null` = beze změny);
  - `MenuItem::$defaultLabel` (popisek ve výchozím jazyce);
  - `MenuItemFormFactory` — kontejner `labels` s polem pro každý jazyk, výběr stránky ve stromu.
- Překlady `data/localization/cs.admin`, `theme/data/localization/cs.front`; testy `PagesTest`, `MenusItemsTest`.

**Rozhodnutí a kompromisy:**
- **URL zůstávají ploché** (`/tym`, ne `/o-nas/tym`). Router a `UrlManager` pracují s jedním slugem.
  Hierarchické adresy by znamenaly změnu routeru a přesměrování při přesunu stránky.
- **Smazání rodiče nemaže podstránky**, přesune je o úroveň výš. DB kaskáda by obešla úklid URL a položek menu.
- **Menu nepřidává podstránky automaticky** (na rozdíl od kategorií). Vnořené položky menu se zakládají ručně.
- **Řazení v gridu jen mezi sourozenci** (datagrid neumí řadit strom), rodič se mění ve formuláři.
- **Obrázky jde přidat až uložené stránce** (vazba potřebuje id).
- **Popisek položky URL/route** je povinný ve výchozím jazyce. Jazyk bez popisku ho na webu převezme. Kategorie,
  článek a stránka bez popisku dál zobrazí vlastní název v daném jazyce.

**Ověřeno po migraci (2026-09-24)**, dočasná data (rodič + podstránka, položky menu), potom smazaná:
- podstránka se zobrazí (200) s drobečkovou navigací „Domů › rodič › podstránka“;
- rodič vypíše podstránku v „Další v této sekci“;
- menu v `cs` ukáže české popisky, v `en` anglický popisek stránky s `/en/…` URL a u položky URL náhradní český
  popisek.
- Layout Pelíšku drobečkovou navigaci nevykresluje, proto je `{control breadCrumb}` přímo v
  `theme/FrontModule/templates/Pages/detail.latte`.
- Zobrazení obrázků na webu ověřené není, ve správci souborů zatím žádný obrázek není (pokrývá jen `PagesTest`).

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `orm.md`, `CLAUDE.md`
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ano — `structures/20260924120000.sql` (bez ní padá detail stránky a menu s odkazem na stránku)
