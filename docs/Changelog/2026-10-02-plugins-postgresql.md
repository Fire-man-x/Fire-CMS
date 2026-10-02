# 2026-10-02 — Pluginy Stalker a Statistics na PostgreSQL

**Co:** Systémové pluginy `Stalker` a `Statistics` mají migrace a odinstalační skript pro MariaDB/MySQL i
PostgreSQL. Adresář migrací vybírá stejný parametr `migrations.driver` jako u jádra.

**Proč:** Jádro už běží na obou databázích, tyhle dva pluginy byly poslední částí `app/Plugins/`, která šla
nainstalovat jen na MariaDB (MySQL syntaxe v migracích i v `deactivate.sql`, `GROUP BY DATE()` ve `Statistics`).

**Dotčené soubory/oblasti:**
- `app/Plugins/{Stalker,Statistics}/data/migrations/mysql/` — původní migrace beze změny obsahu, přejmenované
  z `20260918000001.sql`/`20260918000002.sql` na `20261002000001.sql`/`20261002000002.sql`.
- `app/Plugins/{Stalker,Statistics}/data/migrations/pgsql/` — PostgreSQL dvojčata: identity sloupce, trigger
  `firecms_set_update_date()` pro `updateDate`, indexy s předponou tabulky, cizí klíč `Stalker` jako `DEFERRABLE`.
- `app/Plugins/{Stalker,Statistics}/data/deactivate/{mysql,pgsql}.sql` — dřív `data/deactivate.sql`.
- `app/Plugins/{Stalker,Statistics}/config.plugin.neon` — `directory: …/data/migrations/%migrations.driver%`.
- `app/Model/Plugin/PluginMigrator.php` — dosazuje `%migrations.driver%` podle Nextras driveru, hledá
  `data/deactivate/<driver>.sql` (na MariaDB/MySQL i starší `data/deactivate.sql`), mazání ze `migrations` přes
  obalené identifikátory (`group` je v PostgreSQL vyhrazené slovo).
- `app/Plugins/Statistics/Model/Statistics.php` — `getAvgPerDay()` přes `CAST(... AS DATE)` a `delimite()`,
  vrací `float`.

**Rozhodnutí a kompromisy:**
Migrace pluginů se musely přejmenovat. Původní data (`20260918…`) jsou starší než výchozí migrace jádra
(`20261001…`), takže Nextras plugin po instalaci jádra odmítl („must follow after the latest executed
migration“). Stejné omezení zůstává pro opětovné zapnutí vypnutého pluginu, jakmile jádro dostane novější
migraci (viz `AI-Context/gotchas.md`). Starší rozvržení pluginu jen pro MariaDB (`data/migrations/*.sql`,
`data/deactivate.sql`) `PluginMigrator` dál podporuje.

**Projekty, které už mají plugin zapnutý (MariaDB):**
Tabulka `migrations` obsahuje starý název souboru a `migrations:continue` skončí na „Previously executed
migration … is missing“. Obsah souboru se nezměnil, stačí tedy záznam přejmenovat:

```sql
UPDATE migrations SET file = '20261002000001.sql' WHERE `group` = 'stalker' AND file = '20260918000001.sql';
UPDATE migrations SET file = '20261002000002.sql' WHERE `group` = 'statistics' AND file = '20260918000002.sql';
```

**Návaznost:**
- Update `docs/Architecture/*.md`? ano — `plugins.md` (rozvržení `data/migrations/<driver>/`, vypnutí pluginu),
  `configuration.md` (pluginy už nejsou jen pro MariaDB); `doc/conventions.md`.
- Update `docs/AI-Context/gotchas.md` nebo `patterns.md`? ano — dvojčata migrací pluginů, pořadí migrací pluginů
  a opětovné zapnutí; vzor `config.plugin.neon` v `patterns.md`.
- DB migrace potřeba? ne (jen přejmenování záznamu v `migrations` u projektů se zapnutým pluginem, viz výše).
