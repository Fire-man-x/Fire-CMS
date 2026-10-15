# 2026-10-15 — Přepínače aktivity v gridech administrace na PostgreSQL

**Co:** Status sloupce (`addColumnStatus()`) v gridech nad `Selection` dostávají příznak jako `int` 0/1 spočítaný
v SQL (`CASE WHEN … THEN 1 ELSE 0 END AS active_status`) místo surového sloupce.

**Proč:** Na PostgreSQL ukazoval sloupec „A.“ místo přepínače jen `1`. `ColumnStatus::getCurrentOption()` porovnává
hodnotu s volbami `0`/`1` striktně, PostgreSQL vrací `boolean` jako `bool`, MariaDB jako `int`.

**Dotčené soubory/oblasti:**
- `app/AdminModule/SettingsModule/Presenters/UsersPresenter.php` — `active` → `active_status`
- `app/AdminModule/Presenters/ArticlesPresenter.php` — grid článků `active` → `active_status`, grid kategorií článku
  `isMain` → `is_main_status`
- `app/AdminModule/Presenters/MenusPresenter.php` — `active` → `active_status`
- `app/AdminModule/SettingsModule/Presenters/LanguagesPresenter.php`, `DomainsPresenter.php` — `active` →
  `active_status`, navíc výslovný `select(<tabulka>.*)` (`select()` vypíná výchozí `*`)

**Rozhodnutí a kompromisy:**
Přetypování v SQL, ne převod na pole (jako `PagesPresenter`/`SectionsPresenter`): grid zůstává nad `Selection`
se stránkováním a callbacky s `ActiveRow`. `CASE WHEN` funguje v MariaDB (`tinyint`) i PostgreSQL (`boolean`).
Volby `true`/`false` místo `0`/`1` by rozbily MariaDB.

**Ověřeno:** dotazy všech gridů proti lokální PostgreSQL (`active` = `bool`, `active_status` = `int`), PHPStan nad
změněnými soubory, `composer test`. Na MariaDB a v prohlížeči neověřeno.

**Návaznost:**
- Update `docs/Architecture/*.md`? ne
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano, `gotchas.md` (PostgreSQL příznaky)
- DB migrace potřeba? ne
