# 2026-09-23 — Sloupce `createDate` a `updateDate` ve všech tabulkách

**Co:** Každá tabulka v migracích (`data/migrations/structures`, `app/Plugins/*/data/migrations`) má dva sloupce:
```sql
`createDate` datetime NOT NULL DEFAULT current_timestamp(),
`updateDate` datetime DEFAULT NULL ON UPDATE current_timestamp(),
```
Jsou hned za `id` a sloupci cizích klíčů. Tam, kde byly, jsou přesunuté a sjednocené: `timestamp` → `datetime`,
doplněný `DEFAULT current_timestamp()`, chybějící `updateDate` přidán.

**Proč:** Jednotné auditní sloupce na všech záznamech na žádost zadavatele.

**Dotčené soubory/oblasti:**
- `data/migrations/structures/20161115000000.sql`, `20260917103000.sql` (domains), `20260922130000.sql` (settings,
  které už `createDate`/`updateDate` měly).
- **Pořadí sloupců:** `id` (AUTO_INCREMENT), pak sloupce z `CONSTRAINT … FOREIGN KEY` v původním pořadí, pak
  `createDate`, `updateDate`, pak zbytek. Sloupce cizích klíčů, které byly dřív níž (např. `createdBy`
  v `firecms_articles`/`categories`), se tím přesunuly nahoru. Tabulky bez `id` i FK (`firecms_languages`,
  `firecms_options`) mají datumy hned za PK.
- **ALTER migrace s pozicí sloupce:**
  - `20200324212900.sql`: `session` jde `AFTER updateDate` místo `AFTER createDate`, jinak by skončil mezi
    datumy;
  - `20210601210900.sql`: `roleId` jde `AFTER id` místo `AFTER email`, aby cizí klíč zůstal na začátku.
- `app/Plugins/DynamicForms`, `Stalker`, `Statistics` (`data/migrations/*.sql`).

**Ověření:** Všechny migrace spuštěné v pořadí jako `nextras/migrations` (časové razítko napříč skupinami,
`foreign_key_checks = 0`) do dočasné databáze. Core, DynamicForms i Sliders projdou. U všech 38 tabulek je
kontrolované pořadí a definice obou sloupců. `createDate` se při INSERT vyplní sám a `updateDate` se nastaví při
UPDATE.

**Rozhodnutí a kompromisy:**
- Kód, který `createDate` nastavuje ručně (`new SqlLiteral("NOW()")` v modelech), zůstává. Je teď nadbytečný,
  ale neškodí. `updateTreePositions()` `createDate` v `ON DUPLICATE KEY UPDATE` nepřepisuje, protože seznam
  aktualizovaných sloupců se sestavuje dřív.
- Duplicitní tabulky Stalker/Statistics v core a v plugin migracích jsou vyřešené přesunem do pluginů, viz
  `2026-09-23-stalker-statistics-plugin-migrations.md`.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ne
- DB migrace potřeba? ne, historické migrace přepsané. Je nutný reset DB a smazání `temp/_Nette.Database*`.
