# 2026-09-24 — Kategorie bez typů `url`, `categoryLink`, `textBox`; „Kategorie článků“

**Co:** Kategorie mají už jen typy `site`, `homepage` a `gallery`. Odkazové typy (`url`, `categoryLink`) a
text v menu (`textBox`) jsou odstraněné, odkazy řeší položky menu (`MenuLinkType`). Sekce kategorií se v
administraci jmenuje „Kategorie článků“ (klíč `Article categories`).

**Proč:** Na žádost zadavatele, krok 2 oddělení menu od kategorií (viz
[2026-09-23-menu-items-separated.md](2026-09-23-menu-items-separated.md)). Kategorie mají sloužit jen jako
kategorie stránek/článků.

**Dotčené soubory/oblasti (jádro):**
- `data/migrations/structures/20260924090000.sql`:
  - položky menu na `categoryLink` se přesměrují na cílovou kategorii (id z `firecms_urls`);
  - položky na `url` se převedou na `linkType = url` (adresa z `firecms_urls`, přednostně výchozí jazyk);
  - položky na `textBox` se smažou;
  - převedené položky dostanou popisek z názvu původní kategorie (vlastní popisek má přednost);
  - kategorie těchto typů jdou do koše (typ `site`), výčet `type` má jen tři hodnoty.
  - Otestováno na kopii DB se syntetickými daty.
- Smazané `app/Forms/CategorySubtype/UrlFormPart.php`, `CategoryLinkFormPart.php`, `TextBoxFormPart.php`, jejich
  registrace v `config.neon` a záznamy v `phpstan-baseline.neon`.
- `app/Forms/CategoryFormFactory.php`:
  - `$types` bez odstraněných typů;
  - oprava: `GalleryFormPart` dostával v konstruktoru model kategorií místo `UrlManager`.
- `app/Components/Menu/Menu.php`, `Menu.latte` — bez větve `categoryLink` (a detekce zacyklení) a `textBox`.
- `app/AdminModule/templates/Categories/default.latte` — bez větví pro odstraněné typy.
- Přejmenování na „Kategorie článků“: `app/AdminModule/templates/@layout.latte` (menu administrace),
  `CategoriesPresenter` (drobečková navigace), nadpis `Categories/default.latte`, `MenuLinkType::Category` a pole
  ve formuláři položky menu (`Article category`). Překlady v `data/localization/cs.admin`.
- `CLAUDE.md`, `docs/Architecture/orm.md` — seznam podtypů kategorií.

**Rozhodnutí a kompromisy:**
- **Převod odkazů je best effort.** Typ `url` ukládal adresu přes `UrlManager::saveUrl()`, který ji převádí na
  slug (`https://x.cz` → `https-x-cz`). Převedená položka proto může mít adresu v tomto tvaru a je potřeba ji
  opravit v administraci menu. U `categoryLink` se převede jen číselný cíl.
- **Text v menu (`textBox`) nemá náhradu.** Obsah zůstává v koši u kategorie.
- **Kategorie do koše, ne smazat.** Obsah se neztratí. Podkategorie takové kategorie se nepřesouvají
  (vnořené množiny `categoryLeft`/`categoryRight` se v SQL migraci neopravují), v menu proto zmizí s rodičem.
  Před nasazením do projektu zkontrolujte:
  `SELECT id FROM firecms_categories WHERE parentId IN (SELECT id FROM firecms_categories WHERE type IN ('url','categoryLink','textBox'))`.
- **Oprávnění (`#[Resource('Categories')]`) a URL administrace (`/administrace/categories`) se nemění**, jde
  jen o popisek. Klíč `Categories` zůstává tam, kde jde o kategorie přiřazené článku (detail článku, komentáře).
- **`homepage` a `gallery` zůstávají.** `homepage` čte `HomepagePresenter`, `gallery` je šablona detailu.

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `orm.md` + `CLAUDE.md` (seznam podtypů).
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ano — `data/migrations/structures/20260924090000.sql`
