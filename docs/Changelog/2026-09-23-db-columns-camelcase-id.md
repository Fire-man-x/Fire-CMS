# 2026-09-23 — DB sloupce jádra v camelCase, AUTO_INCREMENT PK přejmenované na `id`

**Co:** Všechny sloupce tabulek jádra jsou nově v camelCase (`create_date` → `createDate`,
`language_id` → `languageId`, …). Každý `AUTO_INCREMENT` primární klíč se jmenuje `id` (`firecms_articles.article_id`
→ `firecms_articles.id`) a cizí klíče na něj odkazují jako `<entita>Id` (`firecms_articleDescriptions.articleId`
→ `firecms_articles.id`). Kód jádra (`app/`, `tests/`) je převedený na nové názvy.

**Proč:** Sjednocení konvence pojmenování (tabulky už camelCase byly, sloupce ne) na žádost zadavatele.
Migrace byly přepsané přímo (bez nových migračních skriptů), protože databáze se resetuje — viz
`docs/AI-Context/gotchas.md`, sekce o přepisování historických migrací.

**Dotčené soubory/oblasti:**
- `data/migrations/**` — sloupce, názvy indexů a `REFERENCES` na nové názvy; prefix constraintu v camelCase,
  přípona `_ibfk_N` zůstává (vzor `settingDescriptions_ibfk_1`).
- Migrace pluginů (`app/Plugins/*/data/migrations`, `theme/Plugins/*/data/migrations`) — jen odkazy na sloupce
  jádra (`INSERT INTO firecms_modules (parentId, …)`, `REFERENCES firecms_users (id)`, …). Vlastní tabulky
  pluginů v `theme/Plugins` (`PetHotel`, SDH…) zůstávají beze změny v původním pojmenování. Plugin
  `app/Plugins/DynamicForms` byl převeden dodatečně, viz `2026-09-23-dynamicforms-camelcase-id.md`.
- `app/Model/BaseModel.php` — `$columnId` má výchozí hodnotu `'id'`; nový `setForeignKeyColumn()` /
  `getForeignKeyColumn()` = název sloupce, pod kterým na tabulku odkazují ostatní tabulky. Modely místo
  `setColumnId('article_id')` volají `setForeignKeyColumn('articleId')`. Výjimky: `Languages` (PK zůstává
  `languageId`, není AUTO_INCREMENT), `SliderItems` (tabulka bez vlastního PK, `setColumnId('sliderId')`).
- Modely/služby — dotazy na překladové a vazební tabulky (`insertTranslation`, `findTranslationBy`,
  `insertRelationTags`, …) používají `getForeignKeyColumn()` místo `getColumnId()`.
- Admin presentery s datagridem — parametr akce je `array($paramKey => $primaryKey)` s
  `$paramKey = $model->getForeignKeyColumn()`, handlery mají parametr `$articleId`, `$roleId`, …
- `app/Model/Users.php` — `COLUMN_ID = 'id'`, `COLUMN_ROLE = 'roleId'`.
- `app/Plugins/Stalker|Statistics/Instalation.php` — inline `CREATE TABLE` na `id` + `REFERENCES firecms_users (id)`.
- `tests/**` — SQLite schéma testů (`id INTEGER PRIMARY KEY …`).
- `app/config/phpstan-baseline.neon` — texty chybových hlášek přepsané na nové názvy vlastností.
- `theme/Plugins/PetHotel/Forms/PetFormFactory.php` — insert do `firecms_files` (tabulka jádra).

**Rozhodnutí a kompromisy:**
- `Settings` si záměrně nechává veřejné snake_case klíče (`main_title`, `seo_title`, `image_resolution`)
  s převodní mapou na sloupce. Jde o rozdělanou práci z 2026-09-22 a o API pro šablony, ne o názvy sloupců.
- JS ve `www/administration/js/` (`file_id`, `is_image`, `parent_id`) se neměnilo. Jde o klientská data
  a o parametry vendorovaného `datagrid.js` / `nestedSortable`, které PHP jako sloupce nečte.
- Datagrid akce nepoužívají jako název parametru `id`, protože by přepsal parametr presenteru `$id`
  (např. v detailu článku `addCategory!` s `id` kategorie by změnil i aktuální článek).
- Kód pod `theme/` (klientské pluginy, mimo git jádra) se kromě migrací a `PetFormFactory` nepřeváděl.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — gotchas.md, sekce "PK sloupce se jmenují `id`"
- DB migrace potřeba? ne, historické migrace přepsané (DB se resetuje)
